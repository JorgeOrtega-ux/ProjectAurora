<?php
// api/services/AuthService.php

class AuthService {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($data) {
        // 1. Validar si el correo existe
        $query = "SELECT id FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':correo', $data->email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'El correo ya está registrado.'];
        }

        // 2. Generar UUID y Hash
        $uuid = bin2hex(random_bytes(16)); // Simple UUID v4 simulation
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

        // 3. Generar Avatar con UI Avatars
        $avatarFilename = $uuid . '.png';
        // Ruta absoluta del sistema de archivos para guardar
        $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
        // Ruta relativa web para guardar en BD
        $webPath = 'storage/profilePictures/default/' . $avatarFilename;

        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        // Obtener iniciales para el avatar
        $nameEncoded = urlencode($data->username);
        $avatarUrl = "https://ui-avatars.com/api/?name={$nameEncoded}&background=random&size=128&format=png";
        $avatarContent = file_get_contents($avatarUrl);
        
        if ($avatarContent) {
            file_put_contents($storageDir . $avatarFilename, $avatarContent);
        } else {
            $webPath = ''; // Fallback si falla
        }

        // 4. Insertar en BD
        $query = "INSERT INTO " . $this->table_name . " 
                 (uuid, nombre, correo, contrasena, avatar_path) 
                 VALUES (:uuid, :nombre, :correo, :contrasena, :avatar_path)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':nombre', $data->username);
        $stmt->bindParam(':correo', $data->email);
        $stmt->bindParam(':contrasena', $password_hash);
        $stmt->bindParam(':avatar_path', $webPath);

        if ($stmt->execute()) {
            // Iniciar sesión automáticamente
            $_SESSION['user_id'] = $this->conn->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_name'] = $data->username;
            $_SESSION['user_avatar'] = $webPath;

            return [
                'success' => true, 
                'message' => 'Usuario registrado correctamente.',
                'user' => [
                    'name' => $data->username,
                    'avatar' => $webPath
                ]
            ];
        }

        return ['success' => false, 'message' => 'Error al registrar el usuario.'];
    }

    public function login($email, $password) {
        $query = "SELECT id, uuid, nombre, contrasena, avatar_path FROM " . $this->table_name . " WHERE correo = :correo LIMIT 0,1";
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

                return [
                    'success' => true,
                    'message' => 'Inicio de sesión exitoso.',
                    'user' => [
                        'name' => $row['nombre'],
                        'avatar' => $row['avatar_path']
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