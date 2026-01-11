<?php
// api/services/AuthServices.php

require_once __DIR__ . '/../../config/database/db.php';

class AuthServices {
    private $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // --- VALIDACIONES DE REGLAS DE NEGOCIO ---
    public function validateStep1($email, $password) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'El formato del correo electrónico no es válido.'];
        }
        $passLen = strlen($password);
        if ($passLen < 12 || $passLen > 64) {
            return ['status' => false, 'message' => 'La contraseña debe tener entre 12 y 64 caracteres.'];
        }
        return ['status' => true, 'message' => 'OK'];
    }

    public function validateUsername($username) {
        $userLen = strlen($username);
        if ($userLen < 6 || $userLen > 32) {
            return ['status' => false, 'message' => 'El nombre de usuario debe tener entre 6 y 32 caracteres.'];
        }
        return ['status' => true, 'message' => 'OK'];
    }

    public function checkEmailExists($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function requestVerificationCode($email, $username, $password) {
        if ($this->checkEmailExists($email)) {
            return ['status' => false, 'message' => 'El correo ya existe.'];
        }

        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $payload = json_encode([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => 'user'
        ]);

        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $delStmt = $this->db->prepare("DELETE FROM verification_codes WHERE identifier = :email AND code_type = 'account_activation'");
        $delStmt->bindParam(':email', $email);
        $delStmt->execute();

        $query = "INSERT INTO verification_codes (identifier, code, payload, expires_at) VALUES (:email, :code, :payload, :expiresAt)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':email'=>$email, ':code'=>$code, ':payload'=>$payload, ':expiresAt'=>$expiresAt]);

        return ['status' => true, 'message' => 'Código enviado'];
    }

    // --- ETAPA 3: Verificar y Crear Usuario ---
    public function verifyAndCreateUser($email, $inputCode) {
        $query = "SELECT * FROM verification_codes WHERE identifier = :email AND code = :code AND code_type = 'account_activation' AND expires_at > NOW() LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':email'=>$email, ':code'=>$inputCode]);

        if ($stmt->rowCount() === 0) {
            return ['status' => false, 'message' => 'Código inválido o expirado.'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $payload = json_decode($row['payload'], true);

        $uuid = $this->generateUuid();
        $username = $payload['username'];
        $passwordHash = $payload['password_hash'];
        
        // NUEVA LÓGICA DE AVATAR INICIAL (Guardar en defaults)
        $avatarUrl = $this->saveInitialAvatar($username, $uuid);

        $insertQuery = "INSERT INTO users (uuid, username, email, password, profile_picture_url, account_role, created_at) 
                        VALUES (:uuid, :username, :email, :password, :profile_picture_url, 'user', NOW())";
        
        $insertStmt = $this->db->prepare($insertQuery);
        $insertStmt->execute([
            ':uuid' => $uuid,
            ':username' => $username,
            ':email' => $email,
            ':password' => $passwordHash,
            ':profile_picture_url' => $avatarUrl
        ]);

        // Borrar código usado
        $this->db->prepare("DELETE FROM verification_codes WHERE id = :id")->execute([':id'=>$row['id']]);

        return $this->forceLogin($email);
    }

    // Helper privado nuevo para guardar en 'defaults'
    private function saveInitialAvatar($username, $uuid) {
        $targetDir = __DIR__ . '/../../public/assets/img/avatars/defaults/';
        $webPath = '/ProjectAurora/public/assets/img/avatars/defaults/';
        
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        $filename = 'default_' . $uuid . '.png';
        $apiUrl = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random&color=fff&size=128";
        
        $content = @file_get_contents($apiUrl);
        if ($content) {
            file_put_contents($targetDir . $filename, $content);
            return $webPath . $filename;
        }
        // Fallback
        return '/ProjectAurora/public/assets/img/default-user.png';
    }

    private function forceLogin($email) {
        $stmt = $this->db->prepare("SELECT id, uuid, username, profile_picture_url, account_role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email'=>$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['user_role'] = $row['account_role'];
        $_SESSION['user_pic'] = $row['profile_picture_url'];

        return ['status' => true, 'message' => 'Bienvenido'];
    }

    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, uuid, username, password, profile_picture_url, account_role, account_status FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email'=>$email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                if ($row['account_status'] !== 'active') {
                    return ['status' => false, 'message' => 'Cuenta suspendida'];
                }
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_role'] = $row['account_role'];
                $_SESSION['user_pic'] = $row['profile_picture_url']; 
                return ['status' => true, 'message' => 'Bienvenido'];
            }
        }
        return ['status' => false, 'message' => 'Credenciales inválidas'];
    }

    public function refreshSessionData() {
        if (!isset($_SESSION['user_id'])) return;
        $stmt = $this->db->prepare("SELECT username, profile_picture_url, account_role, account_status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id'=>$_SESSION['user_id']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
             $_SESSION['username'] = $row['username'];
             $_SESSION['user_role'] = $row['account_role'];
             $_SESSION['user_pic'] = $row['profile_picture_url'];
        } else {
            $this->logout();
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
    }

    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
?>