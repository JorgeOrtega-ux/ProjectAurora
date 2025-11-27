-- bd.sql

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
    profile_picture VARCHAR(255) NULL,
    role VARCHAR(20) DEFAULT 'user',
    account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    suspension_reason TEXT NULL,
    suspension_end_date TIMESTAMP NULL,
    deletion_type ENUM('admin_decision', 'user_decision') NULL, 
    deletion_reason TEXT NULL,
    admin_comments TEXT NULL,
    is_2fa_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(255) NULL,
    backup_codes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CÓDIGOS DE VERIFICACIÓN
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

-- SEGURIDAD
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_check (user_identifier, ip_address, created_at)
);

-- AMISTADES
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

-- NOTIFICACIONES
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, 
    type VARCHAR(50) NOT NULL, 
    message TEXT NOT NULL,
    related_id INT NULL, 
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TOKENS DE AUTENTICACIÓN WS
CREATE TABLE IF NOT EXISTS ws_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL, 
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PREFERENCIAS DE USUARIO
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    usage_intent VARCHAR(50) DEFAULT 'personal',
    language VARCHAR(10) DEFAULT 'en-us',
    theme VARCHAR(20) DEFAULT 'system',
    open_links_in_new_tab TINYINT(1) DEFAULT 1, 
    extended_message_time TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- AUDITORÍA GENERAL
CREATE TABLE IF NOT EXISTS user_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    change_type ENUM('username', 'email', 'profile_picture', 'password', '2fa_disabled') NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by_ip VARCHAR(45) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_audit_check (user_id, change_type, changed_at)
);

-- SESIONES DE USUARIO
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (session_id)
);

-- LOGS DE SUSPENSIÓN
CREATE TABLE IF NOT EXISTS user_suspension_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NULL, 
    reason TEXT NOT NULL,
    duration_days INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at TIMESTAMP NULL, 
    lifted_by INT NULL, 
    lifted_at TIMESTAMP NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lifted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- AUDITORÍA DE ROLES
CREATE TABLE IF NOT EXISTS user_role_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NULL,
    old_role VARCHAR(50) NOT NULL,
    new_role VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_role_audit (user_id, admin_id, changed_at)
);

-- CONFIGURACIÓN DEL SERVIDOR (Extendida)
CREATE TABLE IF NOT EXISTS server_config (
    id INT PRIMARY KEY DEFAULT 1,
    maintenance_mode TINYINT(1) DEFAULT 0,
    allow_registrations TINYINT(1) DEFAULT 1,
    
    -- Nuevas configuraciones
    min_password_length INT DEFAULT 8,
    max_password_length INT DEFAULT 72,
    min_username_length INT DEFAULT 6,
    max_username_length INT DEFAULT 32,
    max_email_length INT DEFAULT 255,
    
    max_login_attempts INT DEFAULT 5,
    lockout_time_minutes INT DEFAULT 5,
    
    code_resend_cooldown INT DEFAULT 60, -- Segundos
    
    username_cooldown INT DEFAULT 30, -- Días
    email_cooldown INT DEFAULT 12,    -- Días
    
    profile_picture_max_size INT DEFAULT 2,    -- MB
    
    -- [NUEVO] Dominios permitidos (JSON)
    allowed_email_domains JSON DEFAULT NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inicializar configuración por defecto con dominios
INSERT IGNORE INTO server_config (id, allowed_email_domains) 
VALUES (1, '["gmail.com", "outlook.com", "hotmail.com", "yahoo.com", "icloud.com"]');


-- 1. Tabla de Comunidades
CREATE TABLE IF NOT EXISTS communities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    creator_id INT NOT NULL,
    community_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    access_code CHAR(14) NOT NULL UNIQUE, -- Formato XXXX-XXXX-XXXX
    privacy ENUM('public', 'private') DEFAULT 'public',
    member_count INT DEFAULT 1,
    profile_picture VARCHAR(255) NULL,
    banner_picture VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (privacy),
    INDEX (access_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Miembros
CREATE TABLE IF NOT EXISTS community_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'admin', 'moderator') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (community_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Datos de Prueba (5 Grupos)
INSERT IGNORE INTO communities (uuid, creator_id, community_name, description, access_code, privacy, member_count, profile_picture, banner_picture) VALUES
('comm-uuid-001', 1, 'Desarrolladores PHP', 'Comunidad para amantes del código backend.', 'PHP7-CODE-2025', 'public', 120, 'https://ui-avatars.com/api/?name=PHP&background=0D8ABC&color=fff', 'https://picsum.photos/seed/php/600/200'),
('comm-uuid-002', 1, 'Diseño UI/UX', 'Compartimos recursos de diseño e inspiración.', 'DSGN-2025-FREE', 'public', 45, 'https://ui-avatars.com/api/?name=UI&background=E91E63&color=fff', 'https://picsum.photos/seed/uiux/600/200'),
('comm-uuid-003', 1, 'Proyecto Aurora Secret', 'Solo personal autorizado del proyecto.', 'AURO-XH55-99ZZ', 'private', 5, 'https://ui-avatars.com/api/?name=PA&background=000000&color=fff', 'https://picsum.photos/seed/aurora/600/200'),
('comm-uuid-004', 1, 'Gaming Latam', 'Torneos y discusiones sobre videojuegos.', 'GAME-PLAY-NOW1', 'public', 890, 'https://ui-avatars.com/api/?name=GL&background=4CAF50&color=fff', 'https://picsum.photos/seed/gaming/600/200'),
('comm-uuid-005', 1, 'Club de Lectura VIP', 'Acceso solo con invitación para lectores.', 'READ-BOOK-CLUB', 'private', 12, 'https://ui-avatars.com/api/?name=CL&background=FF9800&color=fff', 'https://picsum.photos/seed/books/600/200');

-- Tabla para el historial y estado de alertas globales
CREATE TABLE IF NOT EXISTS system_alerts_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,          
    instance_id VARCHAR(50) NOT NULL,   
    status ENUM('active', 'stopped') DEFAULT 'active',
    admin_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stopped_at TIMESTAMP NULL,
    meta_data JSON NULL, -- [NUEVO] Para almacenar fecha, enlace, etc.
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;