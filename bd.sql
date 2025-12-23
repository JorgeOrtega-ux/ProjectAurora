CREATE DATABASE IF NOT EXISTS project_aurora_db;
USE project_aurora_db;

-- =========================================================
-- REINICIO DE TABLAS (Borrado en orden de dependencia)
-- =========================================================
DROP TABLE IF EXISTS user_auth_tokens;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS security_logs;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS users;

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- 2FA COLUMNS
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_recovery_codes JSON DEFAULT NULL
);

-- NUEVA TABLA PARA CÓDIGOS DE VERIFICACIÓN
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

-- TABLA DE LOGS DE SEGURIDAD (ANTI-BRUTEFORCE)
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL, -- Email intentado o 'unknown'
    action_type VARCHAR(50) NOT NULL,      -- 'login_fail' o 'recovery_fail'
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
    open_links_new_tab TINYINT(1) NOT NULL DEFAULT 1, -- 1 = True
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

-- === NUEVA TABLA: TOKENS DE SESIÓN ROTATIVOS ===
-- ACTUALIZADA CON IP Y USER AGENT PARA GESTIÓN DE DISPOSITIVOS
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