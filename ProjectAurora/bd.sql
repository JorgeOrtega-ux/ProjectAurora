-- ==========================================
-- 1. LIMPIEZA INICIAL (RESET)
-- ==========================================
DROP DATABASE IF EXISTS project_aurora_db;

-- ==========================================
-- 2. CREACIÓN DE LA BASE DE DATOS
-- ==========================================
CREATE DATABASE IF NOT EXISTS project_aurora_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE project_aurora_db;

-- ==========================================
-- 3. CREACIÓN DE TABLAS
-- ==========================================

-- USUARIOS
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    role VARCHAR(20) DEFAULT 'user',
    account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    is_2fa_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CÓDIGOS DE VERIFICACIÓN
CREATE TABLE verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, 
    code_type VARCHAR(50) NOT NULL,   
    code VARCHAR(20) NOT NULL,
    payload JSON NULL,                
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (identifier),
    INDEX (code)
);

-- SEGURIDAD
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL,
    -- [MODIFICADO] Cambiado de ENUM a VARCHAR para permitir más tipos de acciones (ej: rate limiting)
    action_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_check (user_identifier, ip_address, created_at)
);

-- AMISTADES (Nueva)
CREATE TABLE IF NOT EXISTS friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (sender_id, receiver_id)
);

-- NOTIFICACIONES (Nueva)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- El dueño de la notificación
    type VARCHAR(50) NOT NULL, -- 'friend_request', 'friend_accepted'
    message TEXT NOT NULL,
    related_id INT NULL, -- ID del usuario que generó la acción (sender)
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);