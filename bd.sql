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

-- 5. Crear la tabla de registros de acceso (Logs)
CREATE TABLE access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);