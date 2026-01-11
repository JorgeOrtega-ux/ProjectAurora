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

    // --- VALIDACIONES DE REGLAS DE NEGOCIO (NUEVO) ---

    public function validateStep1($email, $password) {
        // 1. Validar Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'El formato del correo electrónico no es válido.'];
        }

        // Desglosar el correo
        $parts = explode('@', $email);
        $localPart = $parts[0];
        $domainPart = $parts[1];

        // Regla: Parte antes del @ (mínimo 6, máximo 64)
        $localLen = strlen($localPart);
        if ($localLen < 6 || $localLen > 64) {
            return ['status' => false, 'message' => 'La parte local del correo debe tener entre 6 y 64 caracteres.'];
        }

        // Regla: Parte después del @ (máximo 255)
        if (strlen($domainPart) > 255) {
            return ['status' => false, 'message' => 'El dominio del correo es demasiado largo (máx 255).'];
        }

        // Regla: Dominios permitidos
        $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com'];
        if (!in_array(strtolower($domainPart), $allowedDomains)) {
            return ['status' => false, 'message' => 'Solo se permiten correos de: Gmail, Outlook, Hotmail, Yahoo o iCloud.'];
        }

        // 2. Validar Contraseña
        // Regla: Mínimo 12 caracteres, Máximo 64
        $passLen = strlen($password);
        if ($passLen < 12 || $passLen > 64) {
            return ['status' => false, 'message' => 'La contraseña debe tener entre 12 y 64 caracteres.'];
        }

        return ['status' => true, 'message' => 'OK'];
    }

    public function validateUsername($username) {
        // Regla: Nombre de usuario mínimo 6 y máximo 32
        $userLen = strlen($username);
        if ($userLen < 6 || $userLen > 32) {
            return ['status' => false, 'message' => 'El nombre de usuario debe tener entre 6 y 32 caracteres.'];
        }
        return ['status' => true, 'message' => 'OK'];
    }

    // --------------------------------------------------

    // --- ETAPA 1: Validación Previa ---
    public function checkEmailExists($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // --- ETAPA 2 -> 3: Generar Código y Guardar Payload ---
    public function requestVerificationCode($email, $username, $password) {
        // 1. Verificar si el correo ya existe (doble check)
        if ($this->checkEmailExists($email)) {
            return ['status' => false, 'message' => __('auth.service.email_exists')];
        }

        // 2. Generar código de 6 dígitos
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // 3. Preparar el Payload (Datos a guardar temporalmente)
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $payload = json_encode([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => 'user' // Default role
        ]);

        // 4. Calcular Expiración (15 minutos)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // 5. Insertar en tabla de verificación
        // Limpiamos códigos previos para este email
        $delStmt = $this->db->prepare("DELETE FROM verification_codes WHERE identifier = :email AND code_type = 'account_activation'");
        $delStmt->bindParam(':email', $email);
        $delStmt->execute();

        $query = "INSERT INTO verification_codes (identifier, code, payload, expires_at) VALUES (:email, :code, :payload, :expiresAt)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':payload', $payload);
        $stmt->bindParam(':expiresAt', $expiresAt);

        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Código enviado']; 
        } else {
            return ['status' => false, 'message' => __('auth.service.register_db_error')];
        }
    }

    // --- ETAPA 3: Verificar y Crear Usuario ---
    public function verifyAndCreateUser($email, $inputCode) {
        // 1. Buscar el código válido
        $query = "SELECT * FROM verification_codes 
                  WHERE identifier = :email 
                  AND code = :code 
                  AND code_type = 'account_activation' 
                  AND expires_at > NOW() 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':code', $inputCode);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['status' => false, 'message' => __('auth.service.invalid_code')];
        }

        $verificationRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $payload = json_decode($verificationRow['payload'], true);

        // 2. Crear el Usuario Real
        $uuid = $this->generateUuid();
        $username = $payload['username'];
        $passwordHash = $payload['password_hash'];
        $avatarWebPath = $this->downloadAndSaveAvatar($username, $uuid);

        $insertQuery = "INSERT INTO users (uuid, username, email, password, profile_picture_url, account_role, created_at) 
                        VALUES (:uuid, :username, :email, :password, :profile_picture_url, 'user', NOW())";
        
        $insertStmt = $this->db->prepare($insertQuery);
        $insertStmt->bindParam(':uuid', $uuid);
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':password', $passwordHash);
        $insertStmt->bindParam(':profile_picture_url', $avatarWebPath);

        if ($insertStmt->execute()) {
            // 3. Borrar el código usado
            $delStmt = $this->db->prepare("DELETE FROM verification_codes WHERE id = :id");
            $delStmt->bindParam(':id', $verificationRow['id']);
            $delStmt->execute();

            // 4. Autologin
            return $this->forceLogin($email);
        } else {
            return ['status' => false, 'message' => __('auth.service.register_db_error')];
        }
    }

    // Función auxiliar para login forzado después del registro
    private function forceLogin($email) {
        $stmt = $this->db->prepare("SELECT id, uuid, username, profile_picture_url, account_role, account_status FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_uuid'] = $row['uuid'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['user_role'] = $row['account_role'];
        $_SESSION['user_pic'] = $row['profile_picture_url'];

        return ['status' => true, 'message' => __('auth.service.welcome')];
    }

    // Login normal
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return ['status' => false, 'message' => __('auth.service.login_empty')];
        }
        $stmt = $this->db->prepare("SELECT id, uuid, username, password, profile_picture_url, account_role, account_status FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                if ($row['account_status'] !== 'active') {
                    return ['status' => false, 'message' => __('auth.service.account_suspended')];
                }
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_uuid'] = $row['uuid'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_role'] = $row['account_role'];
                $_SESSION['user_pic'] = $row['profile_picture_url']; 
                return ['status' => true, 'message' => __('auth.service.welcome')];
            }
        }
        return ['status' => false, 'message' => __('auth.service.email_not_found') . ' o ' . __('auth.service.password_incorrect')];
    }

    public function refreshSessionData() {
        if (!isset($_SESSION['user_id'])) return;
        $id = $_SESSION['user_id'];
        $stmt = $this->db->prepare("SELECT username, profile_picture_url, account_role, account_status FROM users WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row['account_status'] !== 'active') {
                $this->logout();
                return;
            }
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_role'] = $row['account_role']; 
            $_SESSION['user_pic'] = $row['profile_picture_url'];
        } else {
            $this->logout();
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_unset();
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

    private function downloadAndSaveAvatar($username, $uuid) {
        $uploadDir = __DIR__ . '/../../public/assets/img/avatars/';
        $webBasePath = '/ProjectAurora/public/assets/img/avatars/';
        $fileName = $uuid . '.png';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $apiUrl = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random&color=fff&size=128&font-size=0.5";
        try {
            $imageData = file_get_contents($apiUrl);
            if ($imageData !== false) {
                file_put_contents($uploadDir . $fileName, $imageData);
                return $webBasePath . $fileName;
            }
        } catch (Exception $e) {}
        return null;
    }
}
?>