-- 1. Crear la Base de Datos (si no existe aún)
CREATE DATABASE IF NOT EXISTS project_aurora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Seleccionar la Base de Datos
USE project_aurora_db;

-- 3. ELIMINAR LA TABLA (Para reiniciar de cero como pediste)
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS verification_codes;

-- 4. Crear la tabla 'users'
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Identificador único para el sistema',
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'administrator', 'founder') DEFAULT 'user' COMMENT 'Rol del usuario para permisos y UI',
    avatar_path VARCHAR(255) DEFAULT NULL COMMENT 'Ruta de la imagen guardada en storage',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Crear la tabla 'verification_codes' (NUEVO PARA ETAPAS)
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    code_type VARCHAR(50) NOT NULL DEFAULT 'account_activation',
    code CHAR(6) NOT NULL,
    payload JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (identifier),
    INDEX (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;