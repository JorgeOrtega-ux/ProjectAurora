<?php
// includes/core/Auth.php

// Aseguramos que Database.php esté disponible
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;

    public function __construct() {
        // Aseguramos que la sesión esté iniciada, ya que auth-action.php
        // la usa pero no incluye Boot.php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Conectamos a la base de datos
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

        // 4. Insertar en la BD
        // Nota: Ajustado a tu tabla 'users' en bd.sql
        $query = "INSERT INTO users (uuid, username, email, password, account_role, created_at) 
                  VALUES (:uuid, :username, :email, :password, 'user', NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $passwordHash);

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
     * Generador de UUID v4 compatible con tu campo CHAR(36)
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
}
?>