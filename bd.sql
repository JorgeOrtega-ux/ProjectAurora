-- bd.sql

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
    account_status ENUM('active', 'deleted', 'suspended') DEFAULT 'active',
    two_factor_secret VARCHAR(255) NULL DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_recovery_codes TEXT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Crear la tabla de registros de acceso
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

-- 8. Tabla de Seguridad (Rate Limiting)
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_ip_action (ip_address, action_type, created_at),
    INDEX idx_security_identifier_action (user_identifier, action_type, created_at)
);

-- 9. Tabla de Preferencias de Usuario
CREATE TABLE user_preferences (
    user_id INT PRIMARY KEY,
    language VARCHAR(10) DEFAULT 'en-US',
    open_links_new_tab BOOLEAN DEFAULT TRUE,
    theme VARCHAR(20) DEFAULT 'system',
    extended_alerts BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 10. Tabla Historial de cambios de perfil
CREATE TABLE user_profile_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    change_type VARCHAR(20) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_history_check (user_id, change_type, created_at)
);

-- 11. Tabla Sesiones Activas
CREATE TABLE IF NOT EXISTS active_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_lookup (session_id)
);

-- 12. Configuración del Servidor (ACTUALIZADA con dominios)
CREATE TABLE IF NOT EXISTS server_config (
    id INT PRIMARY KEY,
    maintenance_mode BOOLEAN DEFAULT 0,
    allow_registrations BOOLEAN DEFAULT 1,
    min_password_length INT DEFAULT 8,
    max_password_length INT DEFAULT 72,
    min_username_length INT DEFAULT 6,
    max_username_length INT DEFAULT 32,
    max_email_length INT DEFAULT 255,
    -- Nuevas columnas
    max_login_attempts INT DEFAULT 5,
    lockout_time_minutes INT DEFAULT 5,
    code_resend_cooldown INT DEFAULT 60,
    username_cooldown INT DEFAULT 30,
    email_cooldown INT DEFAULT 12,
    profile_picture_max_size INT DEFAULT 2,
    allowed_email_domains TEXT DEFAULT NULL, -- Nueva columna para dominios
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Configuración inicial por defecto
INSERT IGNORE INTO server_config 
(id, maintenance_mode, allow_registrations, min_password_length, max_password_length, min_username_length, max_username_length, max_email_length, max_login_attempts, lockout_time_minutes, code_resend_cooldown, username_cooldown, email_cooldown, profile_picture_max_size, allowed_email_domains) 
VALUES 
(1, 0, 1, 8, 72, 6, 32, 255, 5, 5, 60, 30, 12, 2, 'gmail.com,outlook.com,hotmail.com,icloud.com,yahoo.com');