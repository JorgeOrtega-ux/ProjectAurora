-- 1. Crear la Base de Datos (si no existe aún)
CREATE DATABASE IF NOT EXISTS project_aurora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Seleccionar la Base de Datos
USE project_aurora_db;

-- 3. ELIMINAR LAS TABLAS (Para reiniciar de cero)
DROP TABLE IF EXISTS server_config;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS user_changes_log;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS rate_limits;

-- 4. Crear la tabla 'users'
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Unique identifier for the system',
    username VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'administrator', 'founder') DEFAULT 'user' COMMENT 'User role for permissions and UI',
    status ENUM('active', 'deleted') DEFAULT 'active' COMMENT 'Current account lifecycle status',
    is_suspended BOOLEAN DEFAULT FALSE COMMENT 'Access control flag',
    suspension_type ENUM('temporal', 'permanent') DEFAULT NULL COMMENT 'Type of suspension',
    suspension_expires_at DATETIME DEFAULT NULL COMMENT 'When temporal suspension ends',
    suspension_reason TEXT DEFAULT NULL COMMENT 'Reason for suspension',
    deletion_type VARCHAR(50) DEFAULT NULL COMMENT 'e.g., user_requested, admin_banned',
    deletion_reason TEXT DEFAULT NULL COMMENT 'Reason for deletion',
    avatar_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to the image saved in storage',
    two_factor_secret VARCHAR(255) DEFAULT NULL COMMENT 'Secret key for 2FA',
    two_factor_enabled BOOLEAN DEFAULT FALSE COMMENT '2FA status',
    two_factor_recovery_codes TEXT DEFAULT NULL COMMENT 'Recovery codes in JSON',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

-- 6. Crear la tabla 'rate_limits'
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'e.g., login, forgot_password',
    attempts INT DEFAULT 1,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until DATETIME DEFAULT NULL,
    UNIQUE KEY ip_action (ip_address, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Crear la tabla 'user_changes_log'
CREATE TABLE IF NOT EXISTS user_changes_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    modified_field VARCHAR(50) NOT NULL COMMENT 'e.g., avatar, username, email, password',
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Crear la tabla 'user_preferences'
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT PRIMARY KEY,
    language VARCHAR(10) DEFAULT 'en-us',
    open_links_new_tab BOOLEAN DEFAULT TRUE,
    theme VARCHAR(20) DEFAULT 'system',
    extended_alerts BOOLEAN DEFAULT FALSE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Crear la tabla 'user_sessions'
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Crear la tabla 'server_config'
CREATE TABLE IF NOT EXISTS server_config (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar valores de configuración por defecto
INSERT IGNORE INTO server_config (setting_key, setting_value, description) VALUES
('min_password_length', '12', 'Minimum password length'),
('max_password_length', '64', 'Maximum password length'),
('min_username_length', '3', 'Minimum username length'),
('max_username_length', '32', 'Maximum username length'),
('min_email_local_length', '4', 'Minimum email length before @'),
('max_email_local_length', '64', 'Maximum email length before @'),
('allowed_email_domains', 'gmail.com,outlook.com,icloud.com,hotmail.com,yahoo.com', 'Allowed email domains');