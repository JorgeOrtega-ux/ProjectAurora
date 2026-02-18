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
        $uuid = bin2hex(random_bytes(16));
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

        // 3. Configuración del Avatar (NUEVA LÓGICA)
        $avatarFilename = $uuid . '.png';
        $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
        $webPath = 'storage/profilePictures/default/' . $avatarFilename;

        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        // --- CAMBIOS SOLICITADOS ---
        // Lista de colores permitidos (sin el # para la URL)
        $allowedColors = ['2563eb', '16a34a', '7c3aed', 'dc2626', 'ea580c', '374151'];
        // Seleccionar uno al azar
        $randomColor = $allowedColors[array_rand($allowedColors)];
        
        $nameEncoded = urlencode($data->username);
        
        // URL con los nuevos parámetros:
        // background = color aleatorio de la lista
        // color = fff (blanco para que contraste con tus colores oscuros)
        // size = 512 (Alta calidad)
        // length = 1 (Solo 1 letra)
        $avatarUrl = "https://ui-avatars.com/api/?name={$nameEncoded}&background={$randomColor}&color=fff&size=512&length=1&format=png";
        
        $avatarContent = file_get_contents($avatarUrl);
        
        if ($avatarContent) {
            file_put_contents($storageDir . $avatarFilename, $avatarContent);
        } else {
            $webPath = ''; // Fallback
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
            $_SESSION['user_id'] = $this->conn->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_name'] = $data->username;
            $_SESSION['user_role'] = 'user';
            $_SESSION['user_avatar'] = $webPath;

            return [
                'success' => true, 
                'message' => 'Usuario registrado correctamente.',
                'user' => [
                    'name' => $data->username,
                    'avatar' => $webPath,
                    'role' => 'user'
                ]
            ];
        }

        return ['success' => false, 'message' => 'Error al registrar el usuario.'];
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