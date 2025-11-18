-- ==========================================
-- 1. LIMPIEZA INICIAL (RESET)
-- ==========================================

-- Eliminar la base de datos completa si ya existe
DROP DATABASE IF EXISTS project_aurora_db;

-- ==========================================
-- 2. CREACIÓN DE LA BASE DE DATOS
-- ==========================================

-- Crear la base de datos con soporte UTF-8 y emojis
CREATE DATABASE IF NOT EXISTS project_aurora_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Seleccionar la base de datos para usarla
USE project_aurora_db;

-- ==========================================
-- 3. LIMPIEZA DE TABLAS (Por seguridad)
-- ==========================================

-- Desactivar revisión de llaves foráneas temporalmente para evitar errores al borrar
SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar tablas si existen (útil si decides no borrar la BD completa arriba)
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS security_logs;

-- Reactivar revisión de llaves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 4. CREACIÓN DE TABLAS
-- ==========================================

-- Tabla de USUARIOS
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    role VARCHAR(20) DEFAULT 'user',
    account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE users ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0;
-- ==========================================
-- TABLA DE CÓDIGOS DE VERIFICACIÓN
-- ==========================================
CREATE TABLE verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, -- El correo o teléfono
    code_type VARCHAR(50) NOT NULL,   -- Ej: 'registration', 'login', 'recovery'
    code VARCHAR(20) NOT NULL,
    payload JSON NULL,                -- AQUÍ guardamos username y password_hash
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para búsqueda rápida
    INDEX (identifier),
    INDEX (code)
);

-- ==========================================
-- 5. TABLA DE SEGURIDAD (RATE LIMITING)
-- ==========================================
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL, -- Email o Username
    action_type ENUM('login_fail', 'recovery_fail', 'suspicious_activity') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices vitales para velocidad en la verificación
    INDEX idx_security_check (user_identifier, ip_address, created_at)
);