CREATE DATABASE IF NOT EXISTS project_aurora_db;
USE project_aurora_db;

-- =========================================================
-- REINICIO DE TABLAS
-- =========================================================
DROP TABLE IF EXISTS user_auth_tokens;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS security_logs;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS profile_changes;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS server_config;

-- =========================================================
-- CREACIÓN DE TABLAS
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'administrator', 'founder') DEFAULT 'user',
    avatar_path VARCHAR(255),
    account_status ENUM('active', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- 2FA COLUMNS
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_recovery_codes JSON DEFAULT NULL
);

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
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (token),
    INDEX (email)
);

CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_identifier),
    INDEX (ip_address),
    INDEX (created_at)
);

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    language VARCHAR(20) NOT NULL DEFAULT 'es-latam',
    open_links_new_tab TINYINT(1) NOT NULL DEFAULT 1,
    theme VARCHAR(20) NOT NULL DEFAULT 'sync',
    extended_toast TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

CREATE TABLE IF NOT EXISTS user_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    hashed_validator CHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (selector)
);

CREATE TABLE IF NOT EXISTS profile_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    change_type VARCHAR(50) NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (user_id),
    INDEX (change_type)
);

-- =========================================================
-- TABLA DE CONFIGURACIÓN DEL SERVIDOR
-- =========================================================
CREATE TABLE IF NOT EXISTS server_config (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- INSERCIÓN DE VALORES POR DEFECTO
INSERT IGNORE INTO server_config (config_key, config_value) VALUES 
-- Configuración General
('maintenance_mode', '0'),
('allow_registrations', '1'),
('allow_login', '1'),

-- Validaciones de Usuario
('password_min_length', '8'),
('username_min_length', '4'),
('username_max_length', '20'),
('email_min_prefix_length', '3'),
('email_allowed_domains', '*'),

-- 1. Límites de Subida (Uploads)
('upload_avatar_max_size', '2097152'), -- 2 MB en bytes
('upload_avatar_max_dim', '4096'),     -- Píxeles (ancho/alto)

-- 2. Seguridad y Rate Limiting
('security_login_max_attempts', '5'),    -- Intentos antes de bloqueo login
('security_block_duration', '15'),       -- Minutos de bloqueo
('security_register_max_attempts', '10'),-- Intentos de registro por IP
('security_general_rate_limit', '10'),   -- Límite genérico (ej. updates)

-- 3. Tiempos de Expiración
('auth_verification_code_expiry', '15'), -- Minutos validez códigos (6 dígitos)
('auth_reset_token_expiry', '60');       -- Minutos validez tokens reset (link)

-- Agrega esto al final de tu archivo bd.sql o ejecútalo en tu gestor

CREATE TABLE IF NOT EXISTS whiteboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT 'Nuevo Pizarrón',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (user_id)
);