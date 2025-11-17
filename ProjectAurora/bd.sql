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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de CÓDIGOS DE VERIFICACIÓN (Temporal)
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(12) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    -- Índices para búsqueda rápida
    INDEX (email),
    INDEX (code)
);