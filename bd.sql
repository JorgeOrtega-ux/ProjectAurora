-- 1. Crear la Base de Datos (si no existe aún)
CREATE DATABASE IF NOT EXISTS project_aurora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Seleccionar la Base de Datos
USE project_aurora_db;

-- 3. ELIMINAR LAS TABLAS (Para reiniciar de cero)
DROP TABLE IF EXISTS user_changes_log;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS rate_limits;

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

-- 5. Crear la tabla 'verification_codes'
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

-- 6. Crear la tabla 'rate_limits' (NUEVO SISTEMA DE SEGURIDAD)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'Ej: login, forgot_password',
    attempts INT DEFAULT 1,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until DATETIME DEFAULT NULL,
    UNIQUE KEY ip_action (ip_address, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Crear la tabla 'user_changes_log' (SISTEMA DE AUDITORÍA)
CREATE TABLE IF NOT EXISTS user_changes_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    modified_field VARCHAR(50) NOT NULL COMMENT 'e.g., avatar, nombre, correo, contrasena',
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;