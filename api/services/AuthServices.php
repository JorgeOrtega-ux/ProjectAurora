<?php
// api/services/AuthServices.php

require_once __DIR__ . '/../../includes/core/Database.php';

class AuthServices {
    private $db;

    public function __construct() {
        // Asegurar que la sesión esté iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Conexión a la base de datos
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Registra un nuevo usuario
     */
    public function register($username, $email, $password) {
        // 1. Validar datos vacíos
        if (empty($username) || empty($email) || empty($password)) {
            return ['status' => false, 'message' => 'Por favor complete todos los campos.'];
        }

        // 2. Verificar si el correo ya existe
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['status' => false, 'message' => 'El correo electrónico ya está registrado.'];
        }

        // 3. Preparar datos (Hash y UUID)
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $uuid = $this->generateUuid();

        // Generar Avatar Automático
        $avatarWebPath = $this->downloadAndSaveAvatar($username, $uuid);

        // 4. Insertar en la BD
        $query = "INSERT INTO users (uuid, username, email, password, profile_picture_url, account_role, created_at) 
                  VALUES (:uuid, :username, :email, :password, :profile_picture_url, 'user', NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $passwordHash);
        $stmt->bindParam(':profile_picture_url', $avatarWebPath);

        if ($stmt->execute()) {
            // Iniciar sesión automáticamente tras el registro
            $this->login($email, $password);
            return ['status' => true, 'message' => 'Registro exitoso.'];
        } else {
            return ['status' => false, 'message' => 'Error al registrar el usuario en la base de datos.'];
        }
    }

    /**
     * Inicia sesión
     */
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return ['status' => false, 'message' => 'Ingrese correo y contraseña.'];
        }

        // Buscamos usuario por email
        $stmt = $this->db->prepare("SELECT id, uuid, username, password, profile_picture_url, account_role, account_status FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificamos contraseña
            if (password_verify($password, $row['password'])) {
                
                // Verificar si la cuenta está activa
                if ($row['account_status'] !== 'active') {
                    return ['status' => false, 'message' => 'Tu cuenta está suspendida o desactivada.'];
                }

                // Guardar datos en sesión
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_uuid'] = $row['uuid'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_role'] = $row['account_role'];
                $_SESSION['user_pic'] = $row['profile_picture_url']; 

                return ['status' => true, 'message' => 'Bienvenido.'];
            } else {
                return ['status' => false, 'message' => 'Contraseña incorrecta.'];
            }
        } else {
            return ['status' => false, 'message' => 'No existe una cuenta con ese correo.'];
        }
    }

    /**
     * NUEVO: Refresca los datos de la sesión desde la BD en cada carga
     */
    public function refreshSessionData() {
        // Si no hay usuario logueado, salir
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $id = $_SESSION['user_id'];

        // Consultamos los datos más recientes
        $stmt = $this->db->prepare("SELECT username, profile_picture_url, account_role, account_status FROM users WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // 1. Seguridad: Si la cuenta ya no está activa, forzar logout
            if ($row['account_status'] !== 'active') {
                $this->logout();
                return;
            }

            // 2. Actualizar variables de sesión con datos frescos
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_role'] = $row['account_role']; // Esto actualiza el borde del avatar
            $_SESSION['user_pic'] = $row['profile_picture_url'];
            
        } else {
            // El usuario fue borrado de la BD mientras navegaba
            $this->logout();
        }
    }

    /**
     * Cierra la sesión
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    /**
     * Generador de UUID v4
     */
    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Descarga y guarda el avatar
     */
    private function downloadAndSaveAvatar($username, $uuid) {
        $uploadDir = __DIR__ . '/../../public/assets/img/avatars/';
        $webBasePath = '/ProjectAurora/public/assets/img/avatars/';
        $fileName = $uuid . '.png';
        $savePath = $uploadDir . $fileName;

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) return null;
        }

        $apiUrl = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random&color=fff&size=128&font-size=0.5";

        try {
            $imageData = file_get_contents($apiUrl);
            if ($imageData !== false) {
                file_put_contents($savePath, $imageData);
                return $webBasePath . $fileName;
            }
        } catch (Exception $e) {
            return null;
        }
        return null;
    }
}
?>