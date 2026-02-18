-- 1. Crear la Base de Datos (si no existe aún)
CREATE DATABASE IF NOT EXISTS project_aurora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Seleccionar la Base de Datos
USE project_aurora_db;

-- 3. ELIMINAR LA TABLA (Para reiniciar de cero como pediste)
DROP TABLE IF EXISTS users;

-- 4. Crear la tabla 'users'
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Identificador único para el sistema',
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL COMMENT 'Ruta de la imagen guardada en storage',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;