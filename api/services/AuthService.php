<?php
// api/services/AuthService.php
namespace App\Api\Services;

use PDO;
use App\Core\Utils;
use App\Core\Logger;

class AuthService {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function checkEmail($email) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':correo', $email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            Logger::database("Error al comprobar la existencia del email: $email", Logger::LEVEL_ERROR, $e);
            return false;
        }
    }

    public function requestRegistrationCode($data) {
        try {
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
                $html = "<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                            <h2>Bienvenido a Project Aurora</h2>
                            <p>Hola {$data->username},</p>
                            <p>Tu código de activación es:</p>
                            <h1 style='letter-spacing: 4px; background: #f5f5fa; padding: 12px; text-align: center; border-radius: 8px;'>{$code}</h1>
                            <p>Este código expirará en 15 minutos.</p>
                         </div>";

                if (Utils::sendEmail($data->email, 'Código de Activación - Project Aurora', $html)) {
                    Logger::system("Código de activación enviado correctamente al correo: {$data->email}", Logger::LEVEL_INFO);
                    return ['success' => true, 'message' => 'Código enviado a tu correo.'];
                }
                return ['success' => false, 'message' => 'Error al intentar enviar el correo. Revisa la configuración SMTP.'];
            }

            return ['success' => false, 'message' => 'Error al procesar la solicitud.'];
        } catch (\Throwable $e) {
            Logger::database("Fallo al solicitar código de registro para: {$data->email}", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error crítico al procesar la solicitud.'];
        }
    }

    public function register($data) {
        try {
            $query = "SELECT id, payload FROM verification_codes WHERE identifier = :identifier AND code = :code AND code_type = 'account_activation' AND expires_at > NOW() LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':identifier', $data->email);
            $stmt->bindParam(':code', $data->code);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                Logger::system("Intento de registro fallido: código inválido o expirado para {$data->email}", Logger::LEVEL_WARNING);
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

                $lang = $data->language ?? 'en-us';
                $openLinks = isset($data->open_links_new_tab) && $data->open_links_new_tab ? 1 : 0;
                $theme = $data->theme ?? 'system';
                $extendedAlerts = isset($data->extended_alerts) && $data->extended_alerts ? 1 : 0;

                $prefStmt = $this->conn->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab, theme, extended_alerts) VALUES (:uid, :lang, :links, :theme, :alerts)");
                $prefStmt->execute([
                    ':uid'   => $newUserId,
                    ':lang'  => $lang,
                    ':links' => $openLinks,
                    ':theme' => $theme,
                    ':alerts'=> $extendedAlerts
                ]);
                
                setcookie('aurora_lang', $lang, time() + 31536000, '/');

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

                Logger::system("Nuevo usuario registrado: $email (ID: $newUserId)", Logger::LEVEL_INFO);

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
        } catch (\Throwable $e) {
            Logger::database("Fallo al registrar la cuenta de usuario para: {$data->email}", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al procesar el registro.'];
        }
    }

    public function login($email, $password) {
        if (!Utils::checkRateLimit($this->conn, 'login', 15, 5)) {
            return ['success' => false, 'message' => 'Por seguridad, tu cuenta está bloqueada temporalmente. Intenta de nuevo en 15 minutos.'];
        }

        try {
            $query = "SELECT id, uuid, nombre, correo, contrasena, avatar_path, role, two_factor_enabled, two_factor_secret, two_factor_recovery_codes FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':correo', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $row['contrasena'])) {
                    
                    // --- INTEGRACIÓN 2FA ---
                    if (isset($row['two_factor_enabled']) && $row['two_factor_enabled'] == 1) {
                        $tempToken = bin2hex(random_bytes(32));
                        $_SESSION['temp_2fa_token'] = $tempToken;
                        $_SESSION['temp_2fa_user_id'] = $row['id'];
                        
                        Logger::system("Login requiere 2FA para el usuario ID: {$row['id']}", Logger::LEVEL_INFO);

                        return [
                            'success' => true,
                            'requires_2fa' => true,
                            'message' => 'Verificación en dos pasos requerida.',
                            'token' => $tempToken
                        ];
                    }
                    // -----------------------

                    Utils::resetAttempts($this->conn, 'login');

                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_uuid'] = $row['uuid'];
                    $_SESSION['user_name'] = $row['nombre'];
                    $_SESSION['user_email'] = $row['correo'];
                    $_SESSION['user_avatar'] = $row['avatar_path'];
                    $_SESSION['user_role'] = $row['role'];

                    $prefStmt = $this->conn->prepare("SELECT language FROM user_preferences WHERE user_id = :uid");
                    $prefStmt->execute([':uid' => $row['id']]);
                    $userLang = $prefStmt->fetchColumn() ?: 'es-latam';
                    setcookie('aurora_lang', $userLang, time() + 31536000, '/');

                    Logger::system("Inicio de sesión exitoso: $email (ID: {$row['id']})", Logger::LEVEL_INFO);

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
            
            Utils::recordActionAttempt($this->conn, 'login', 5, 15);
            Logger::system("Intento de inicio de sesión fallido para el correo: $email", Logger::LEVEL_WARNING);
            return ['success' => false, 'message' => 'Correo o contraseña incorrectos.'];
            
        } catch (\Throwable $e) {
            Logger::database("Fallo al consultar base de datos en login para el correo: $email", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al procesar el inicio de sesión.'];
        }
    }

    public function verify2FACode($token, $code) {
        if (!Utils::checkRateLimit($this->conn, 'login', 15, 5)) {
            return ['success' => false, 'message' => 'Por seguridad, tu cuenta está bloqueada temporalmente.'];
        }

        if (!isset($_SESSION['temp_2fa_token']) || !isset($_SESSION['temp_2fa_user_id']) || $_SESSION['temp_2fa_token'] !== $token) {
            Logger::system("Token de 2FA inválido o expirado", Logger::LEVEL_WARNING);
            return ['success' => false, 'message' => 'Token de sesión inválido o expirado. Vuelve a iniciar sesión.'];
        }

        $userId = $_SESSION['temp_2fa_user_id'];
        
        try {
            $query = "SELECT id, uuid, nombre, correo, avatar_path, role, two_factor_secret, two_factor_recovery_codes FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $isValid = false;
                $code = trim($code);

                // 1. Verificamos si es un código temporal de la App Authenticator (TOTP)
                if (strlen($code) === 6 && is_numeric($code)) {
                    $isValid = Utils::verifyTOTP($row['two_factor_secret'], $code);
                }
                
                // 2. Si falló el TOTP, validamos contra los códigos de respaldo (Backup Codes)
                if (!$isValid && !empty($row['two_factor_recovery_codes'])) {
                    $backupCodes = json_decode($row['two_factor_recovery_codes'], true);
                    if (is_array($backupCodes) && in_array($code, $backupCodes)) {
                        $isValid = true;
                        // Invalida el código de backup usado
                        $backupCodes = array_values(array_diff($backupCodes, [$code]));
                        $updStmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET two_factor_recovery_codes = :codes WHERE id = :id");
                        $updStmt->execute([':codes' => json_encode($backupCodes), ':id' => $userId]);
                        
                        Logger::system("Usuario ID: $userId utilizó un código de recuperación 2FA", Logger::LEVEL_INFO);
                    }
                }

                if ($isValid) {
                    Utils::resetAttempts($this->conn, 'login');
                    
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_uuid'] = $row['uuid'];
                    $_SESSION['user_name'] = $row['nombre'];
                    $_SESSION['user_email'] = $row['correo'];
                    $_SESSION['user_avatar'] = $row['avatar_path'];
                    $_SESSION['user_role'] = $row['role'];

                    unset($_SESSION['temp_2fa_token']);
                    unset($_SESSION['temp_2fa_user_id']);

                    $prefStmt = $this->conn->prepare("SELECT language FROM user_preferences WHERE user_id = :uid");
                    $prefStmt->execute([':uid' => $row['id']]);
                    $userLang = $prefStmt->fetchColumn() ?: 'es-latam';
                    setcookie('aurora_lang', $userLang, time() + 31536000, '/');

                    Logger::system("Inicio de sesión 2FA exitoso: {$row['correo']} (ID: $userId)", Logger::LEVEL_INFO);
                    return ['success' => true, 'message' => 'Inicio de sesión exitoso.'];
                }
            }
            
            Utils::recordActionAttempt($this->conn, 'login', 5, 15);
            Logger::system("Código 2FA incorrecto ingresado para el usuario ID: $userId", Logger::LEVEL_WARNING);
            return ['success' => false, 'message' => 'Código de autenticación incorrecto.'];

        } catch (\Throwable $e) {
            Logger::database("Fallo al consultar base de datos durante verify2FACode para usuario ID: $userId", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al procesar el código.'];
        }
    }

    public function logout() {
        $userId = $_SESSION['user_id'] ?? 'Guest';
        Logger::system("Cierre de sesión: Usuario ID: $userId", Logger::LEVEL_INFO);
        session_destroy();
        return ['success' => true];
    }

    public function requestPasswordReset($email) {
        if (!Utils::checkRateLimit($this->conn, 'forgot_password', 15, 5)) {
            return ['success' => false, 'message' => 'Demasiadas solicitudes. Por favor, intenta de nuevo en 15 minutos.'];
        }

        try {
            if (!$this->checkEmail($email)) {
                Utils::recordActionAttempt($this->conn, 'forgot_password', 5, 15);
                return ['success' => false, 'message' => 'El correo proporcionado no está registrado en el sistema.'];
            }

            Utils::resetAttempts($this->conn, 'forgot_password');

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
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $resetLink = $protocol . $host . "/ProjectAurora/reset-password?token=" . $token;

                $html = "<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                            <h2>Recuperación de Contraseña</h2>
                            <p>Has solicitado restablecer tu contraseña para Project Aurora.</p>
                            <p>Haz clic en el siguiente enlace para continuar:</p>
                            <p><a href='{$resetLink}' style='display: inline-block; padding: 12px 24px; background: #000; color: #fff; text-decoration: none; border-radius: 8px;'>Restablecer Contraseña</a></p>
                            <p style='font-size: 12px; color: #666; margin-top: 24px;'>Si no solicitaste este cambio, ignora este correo. El enlace expirará en 1 hora.</p>
                         </div>";

                if (Utils::sendEmail($email, 'Recuperación de Contraseña - Project Aurora', $html)) {
                    Logger::system("Enlace de recuperación de contraseña enviado a: $email", Logger::LEVEL_INFO);
                    return ['success' => true, 'message' => 'Enlace de recuperación enviado.'];
                }
                return ['success' => false, 'message' => 'Error al intentar enviar el correo.'];
            }

            return ['success' => false, 'message' => 'Error al procesar la solicitud.'];
        } catch (\Throwable $e) {
            Logger::database("Error al solicitar recuperación de contraseña para: $email", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error crítico al procesar la solicitud.'];
        }
    }

    public function resetPassword($token, $newPassword) {
        try {
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

                Logger::system("El usuario ID: $userId ($email) ha restablecido su contraseña con éxito", Logger::LEVEL_INFO);

                return ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
            }

            return ['success' => false, 'message' => 'Error al actualizar la contraseña.'];
        } catch (\Throwable $e) {
            Logger::database("Error en el proceso resetPassword para el token provisto", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al actualizar la contraseña.'];
        }
    }
}
?>