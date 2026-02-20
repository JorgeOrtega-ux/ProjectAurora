<?php
// api/services/AuthService.php
namespace App\Api\Services;

use PDO;
use App\Core\Utils;

class AuthService {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    private function getUserIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    private function checkRateLimit($action) {
        $ip = $this->getUserIP();
        $query = "SELECT attempts, blocked_until, (blocked_until > NOW()) as is_blocked FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ip' => $ip, ':action' => $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            if ($result['is_blocked']) {
                return false; 
            }
            if (!$result['is_blocked'] && $result['blocked_until'] !== null) {
                $this->resetAttempts($action);
            }
        }
        return true;
    }

    private function recordFailedAttempt($action) {
        $ip = $this->getUserIP();
        $query = "SELECT attempts FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ip' => $ip, ':action' => $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $attempts = $result['attempts'] + 1;
            $blocked_until = ($attempts >= 5) ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
            
            $update = "UPDATE rate_limits SET attempts = :attempts, last_attempt = NOW(), blocked_until = :blocked_until WHERE ip_address = :ip AND action = :action";
            $updStmt = $this->conn->prepare($update);
            $updStmt->execute([
                ':attempts' => $attempts, 
                ':blocked_until' => $blocked_until, 
                ':ip' => $ip, 
                ':action' => $action
            ]);
        } else {
            $insert = "INSERT INTO rate_limits (ip_address, action, attempts, last_attempt) VALUES (:ip, :action, 1, NOW())";
            $insStmt = $this->conn->prepare($insert);
            $insStmt->execute([':ip' => $ip, ':action' => $action]);
        }
    }

    private function resetAttempts($action) {
        $ip = $this->getUserIP();
        $query = "DELETE FROM rate_limits WHERE ip_address = :ip AND action = :action";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ip' => $ip, ':action' => $action]);
    }

    public function checkEmail($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':correo', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    public function requestRegistrationCode($data) {
        if ($this->checkEmail($data->email)) {
            return ['success' => false, 'message' => 'El correo ya está registrado.'];
        }

        $code = sprintf("%06d", mt_rand(1, 999999));
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT);
        
        $payload = json_encode([
            'username' => $data->username,
            'email' => $data->email,
            'password_hash' => $password_hash
        ]);

        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $delQuery = "DELETE FROM verification_codes WHERE identifier = :identifier AND code_type = 'account_activation'";
        $delStmt = $this->conn->prepare($delQuery);
        $delStmt->bindParam(':identifier', $data->email);
        $delStmt->execute();

        $query = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) 
                  VALUES (:identifier, 'account_activation', :code, :payload, :expires_at)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':identifier', $data->email);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':payload', $payload);
        $stmt->bindParam(':expires_at', $expires_at);

        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Código enviado.', 
                'dev_code' => $code 
            ];
        }

        return ['success' => false, 'message' => 'Error al procesar la solicitud.'];
    }

    public function register($data) {
        $query = "SELECT id, payload FROM verification_codes WHERE identifier = :identifier AND code = :code AND code_type = 'account_activation' AND expires_at > NOW() LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':identifier', $data->email);
        $stmt->bindParam(':code', $data->code);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'El código de verificación es inválido o ha expirado.'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $payload = json_decode($row['payload'], true);
        $codeId = $row['id'];

        $username = $payload['username'];
        $email = $payload['email'];
        $password_hash = $payload['password_hash'];

        $uuid = Utils::generateUUID();

        $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
        $webDir = 'storage/profilePictures/default/';
        $webPath = Utils::generateAndSaveAvatar($username, $uuid, $storageDir, $webDir);

        $query = "INSERT INTO " . $this->table_name . " 
                 (uuid, nombre, correo, contrasena, avatar_path) 
                 VALUES (:uuid, :nombre, :correo, :contrasena, :avatar_path)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':nombre', $username);
        $stmt->bindParam(':correo', $email);
        $stmt->bindParam(':contrasena', $password_hash);
        $stmt->bindParam(':avatar_path', $webPath);

        if ($stmt->execute()) {
            $newUserId = $this->conn->lastInsertId();

            // =================================================================================
            // NUEVO: GUARDAR EN BASE DE DATOS LAS PREFERENCIAS DE CUANDO ERA INVITADO
            // =================================================================================
            $lang = $data->language ?? 'en-us';
            // Validar que sea 1 o 0 (si viene boolean desde JS true/false)
            $openLinks = isset($data->open_links_new_tab) && $data->open_links_new_tab ? 1 : 0;

            $prefStmt = $this->conn->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab) VALUES (:uid, :lang, :links)");
            $prefStmt->execute([
                ':uid'   => $newUserId,
                ':lang'  => $lang,
                ':links' => $openLinks
            ]);
            // =================================================================================

            $delQuery = "DELETE FROM verification_codes WHERE id = :id";
            $delStmt = $this->conn->prepare($delQuery);
            $delStmt->bindParam(':id', $codeId);
            $delStmt->execute();

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_name'] = $username;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'user';
            $_SESSION['user_avatar'] = $webPath;

            return [
                'success' => true, 
                'message' => 'Usuario registrado correctamente.',
                'user' => [
                    'name' => $username,
                    'avatar' => $webPath,
                    'role' => 'user'
                ]
            ];
        }

        return ['success' => false, 'message' => 'Error crítico al crear la cuenta.'];
    }

    public function login($email, $password) {
        if (!$this->checkRateLimit('login')) {
            return ['success' => false, 'message' => 'Por seguridad, tu cuenta está bloqueada temporalmente. Intenta de nuevo en 15 minutos.'];
        }

        $query = "SELECT id, uuid, nombre, correo, contrasena, avatar_path, role FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':correo', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['contrasena'])) {
                
                $this->resetAttempts('login');

                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_uuid'] = $row['uuid'];
                $_SESSION['user_name'] = $row['nombre'];
                $_SESSION['user_email'] = $row['correo'];
                $_SESSION['user_avatar'] = $row['avatar_path'];
                $_SESSION['user_role'] = $row['role'];

                return [
                    'success' => true,
                    'message' => 'Inicio de sesión exitoso.',
                    'user' => [
                        'name' => $row['nombre'],
                        'avatar' => $row['avatar_path'],
                        'role' => $row['role']
                    ]
                ];
            }
        }
        
        $this->recordFailedAttempt('login');
        return ['success' => false, 'message' => 'Correo o contraseña incorrectos.'];
    }

    public function logout() {
        session_destroy();
        return ['success' => true];
    }

    public function requestPasswordReset($email) {
        if (!$this->checkRateLimit('forgot_password')) {
            return ['success' => false, 'message' => 'Demasiadas solicitudes. Por favor, intenta de nuevo en 15 minutos.'];
        }

        if (!$this->checkEmail($email)) {
            $this->recordFailedAttempt('forgot_password');
            return ['success' => false, 'message' => 'El correo proporcionado no está registrado en el sistema.'];
        }

        $this->resetAttempts('forgot_password');

        $token = bin2hex(random_bytes(32)); 
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); 

        $delQuery = "DELETE FROM verification_codes WHERE identifier = :identifier AND code_type = 'password_reset'";
        $delStmt = $this->conn->prepare($delQuery);
        $delStmt->bindParam(':identifier', $email);
        $delStmt->execute();

        $payload = json_encode(['email' => $email]);
        $query = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) 
                  VALUES (:identifier, 'password_reset', :code, :payload, :expires_at)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':identifier', $email);
        $stmt->bindParam(':code', $token);
        $stmt->bindParam(':payload', $payload);
        $stmt->bindParam(':expires_at', $expires_at);

        if ($stmt->execute()) {
            $resetLink = "/ProjectAurora/reset-password?token=" . $token;
            return [
                'success' => true, 
                'message' => 'Enlace de recuperación generado.',
                'dev_link' => $resetLink 
            ];
        }

        return ['success' => false, 'message' => 'Error al procesar la solicitud.'];
    }

    public function resetPassword($token, $newPassword) {
        $query = "SELECT id, identifier FROM verification_codes WHERE code = :code AND code_type = 'password_reset' AND expires_at > NOW() LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'El enlace de recuperación es inválido o ha expirado.'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $email = $row['identifier'];
        $codeId = $row['id'];

        $userStmt = $this->conn->prepare("SELECT id, contrasena FROM " . $this->table_name . " WHERE correo = :correo");
        $userStmt->execute([':correo' => $email]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userId = $userData['id'];
        $oldPasswordHash = $userData['contrasena'];

        $password_hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $updateQuery = "UPDATE " . $this->table_name . " SET contrasena = :contrasena WHERE correo = :correo";
        $updStmt = $this->conn->prepare($updateQuery);
        $updStmt->bindParam(':contrasena', $password_hash);
        $updStmt->bindParam(':correo', $email);

        if ($updStmt->execute()) {
            
            $logStmt = $this->conn->prepare("INSERT INTO user_changes_log (user_id, modified_field, old_value, new_value) VALUES (:user_id, 'contrasena', :old_val, :new_val)");
            $logStmt->execute([
                ':user_id' => $userId, 
                ':old_val' => $oldPasswordHash, 
                ':new_val' => $password_hash
            ]);

            $delStmt = $this->conn->prepare("DELETE FROM verification_codes WHERE id = :id");
            $delStmt->bindParam(':id', $codeId);
            $delStmt->execute();

            return ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
        }

        return ['success' => false, 'message' => 'Error al actualizar la contraseña.'];
    }
}
?>