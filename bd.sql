CREATE DATABASE IF NOT EXISTS project_aurora;
USE project_aurora;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_picture_url VARCHAR(255) DEFAULT NULL,
    account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    account_role ENUM('user', 'moderator', 'administrator', 'founder') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);