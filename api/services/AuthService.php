<?php
// api/services/AuthService.php

class AuthService {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
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

            $delQuery = "DELETE FROM verification_codes WHERE id = :id";
            $delStmt = $this->conn->prepare($delQuery);
            $delStmt->bindParam(':id', $codeId);
            $delStmt->execute();

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_name'] = $username;
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
        $query = "SELECT id, uuid, nombre, contrasena, avatar_path, role FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':correo', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['contrasena'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_uuid'] = $row['uuid'];
                $_SESSION['user_name'] = $row['nombre'];
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
        return ['success' => false, 'message' => 'Correo o contraseña incorrectos.'];
    }

    public function logout() {
        session_destroy();
        return ['success' => true];
    }

    // ==========================================
    // MÉTODOS PARA RECUPERACIÓN DE CONTRASEÑA
    // ==========================================

    public function requestPasswordReset($email) {
        // 1. Validar que el correo exista
        if (!$this->checkEmail($email)) {
            return ['success' => false, 'message' => 'El correo proporcionado no está registrado en el sistema.'];
        }

        // 2. Generar Token Seguro Largo
        $token = bin2hex(random_bytes(32)); // 64 caracteres
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expira en 1 hora

        // 3. Limpiar tokens antiguos de recuperación
        $delQuery = "DELETE FROM verification_codes WHERE identifier = :identifier AND code_type = 'password_reset'";
        $delStmt = $this->conn->prepare($delQuery);
        $delStmt->bindParam(':identifier', $email);
        $delStmt->execute();

        // 4. Insertar nuevo Token
        $payload = json_encode(['email' => $email]);
        $query = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) 
                  VALUES (:identifier, 'password_reset', :code, :payload, :expires_at)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':identifier', $email);
        $stmt->bindParam(':code', $token);
        $stmt->bindParam(':payload', $payload);
        $stmt->bindParam(':expires_at', $expires_at);

        if ($stmt->execute()) {
            // El dominio base deberia venir del archivo env, pero lo emulamos como pediste:
            $resetLink = "/ProjectAurora/reset-password?token=" . $token;
            return [
                'success' => true, 
                'message' => 'Enlace de recuperación generado.',
                'dev_link' => $resetLink // Simulamos el correo mostrándolo en UI
            ];
        }

        return ['success' => false, 'message' => 'Error al procesar la solicitud.'];
    }

    public function resetPassword($token, $newPassword) {
        // 1. Validar que el token exista y no haya expirado
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

        // 2. Encriptar nueva contraseña
        $password_hash = password_hash($newPassword, PASSWORD_BCRYPT);

        // 3. Actualizar la tabla users
        $updateQuery = "UPDATE " . $this->table_name . " SET contrasena = :contrasena WHERE correo = :correo";
        $updStmt = $this->conn->prepare($updateQuery);
        $updStmt->bindParam(':contrasena', $password_hash);
        $updStmt->bindParam(':correo', $email);

        if ($updStmt->execute()) {
            // 4. Eliminar el token para que no pueda ser usado nuevamente
            $delStmt = $this->conn->prepare("DELETE FROM verification_codes WHERE id = :id");
            $delStmt->bindParam(':id', $codeId);
            $delStmt->execute();

            return ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
        }

        return ['success' => false, 'message' => 'Error al actualizar la contraseña.'];
    }
}
?>