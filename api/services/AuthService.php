<?php
// api/services/AuthService.php

class AuthService {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Valida si un correo ya existe en la tabla users
    public function checkEmail($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':correo', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Genera el código y almacena el payload temporalmente
    public function requestRegistrationCode($data) {
        if ($this->checkEmail($data->email)) {
            return ['success' => false, 'message' => 'El correo ya está registrado.'];
        }

        // Generar código de 6 dígitos
        $code = sprintf("%06d", mt_rand(1, 999999));
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT);
        
        // Guardar todos los datos necesarios para cuando se verifique
        $payload = json_encode([
            'username' => $data->username,
            'email' => $data->email,
            'password_hash' => $password_hash
        ]);

        // Expira en 15 minutos
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Limpiar códigos anteriores de este correo para evitar basura en la DB
        $delQuery = "DELETE FROM verification_codes WHERE identifier = :identifier AND code_type = 'account_activation'";
        $delStmt = $this->conn->prepare($delQuery);
        $delStmt->bindParam(':identifier', $data->email);
        $delStmt->execute();

        // Insertar nuevo código temporal
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
                'dev_code' => $code // Devuelto al JS para simular que llega al correo y mostrarlo
            ];
        }

        return ['success' => false, 'message' => 'Error al procesar la solicitud.'];
    }

    // Verifica el código e inserta el usuario final
    public function register($data) {
        // 1. Validar si el código existe, es para ese correo y no ha expirado
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

        // Extraer datos validados del payload temporal
        $username = $payload['username'];
        $email = $payload['email'];
        $password_hash = $payload['password_hash'];

        // 2. Generar UUID usando Utils
        $uuid = Utils::generateUUID();

        // 3. Configuración del Avatar usando Utils
        $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
        $webDir = 'storage/profilePictures/default/';
        $webPath = Utils::generateAndSaveAvatar($username, $uuid, $storageDir, $webDir);

        // 4. Insertar en BD Final
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

            // 5. Destruir el código utilizado para que no se re-use
            $delQuery = "DELETE FROM verification_codes WHERE id = :id";
            $delStmt = $this->conn->prepare($delQuery);
            $delStmt->bindParam(':id', $codeId);
            $delStmt->execute();

            // 6. Iniciar sesión automática
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
}
?>