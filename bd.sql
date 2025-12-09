-- 1. Eliminar la base de datos previa si existe (Reinicio total)
DROP DATABASE IF EXISTS project_aurora_db;

-- 2. Crear la base de datos
CREATE DATABASE project_aurora_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- 3. Seleccionar la base de datos para usarla
USE project_aurora_db;

-- 4. Crear la tabla de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'administrator', 'founder') DEFAULT 'user',
    uuid VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Crear la tabla de registros de acceso (Logs de éxito)
CREATE TABLE access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Crear la tabla de códigos de verificación
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, 
    code_type VARCHAR(50) NOT NULL,   
    code VARCHAR(64) NOT NULL,        
    payload JSON NULL,                
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (identifier),
    INDEX (code)
);

-- 7. Tabla para tokens de restablecimiento de contraseña
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (token),
    FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
);

-- 8. [NUEVO] Tabla de Seguridad (Rate Limiting)
-- Registra intentos fallidos o acciones sensibles para bloquear ataques de fuerza bruta.
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL, -- Puede ser Email, Username o IP
    action_type VARCHAR(50) NOT NULL,      -- Ej: 'login_fail', 'verify_fail', 'recovery_request'
    ip_address VARCHAR(45) NOT NULL,       -- Siempre registramos la IP
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para búsqueda rápida (CRÍTICO PARA RENDIMIENTO)
    INDEX idx_security_ip_action (ip_address, action_type, created_at),
    INDEX idx_security_identifier_action (user_identifier, action_type, created_at)
);