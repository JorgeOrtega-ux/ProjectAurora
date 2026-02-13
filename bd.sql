CREATE DATABASE IF NOT EXISTS project_aurora_db;
USE project_aurora_db;

-- =========================================================
-- REINICIO DE TABLAS
-- =========================================================
-- (Mantenemos tu lógica de limpieza si reinicias la BD, agregamos las nuevas)
DROP TABLE IF EXISTS video_views;
DROP TABLE IF EXISTS video_interactions;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS audit_logs; 
DROP TABLE IF EXISTS ws_auth_tokens;
DROP TABLE IF EXISTS user_auth_tokens;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS security_logs;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS profile_changes;
DROP TABLE IF EXISTS videos; -- Videos debe borrarse antes que users
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS server_config;

-- =========================================================
-- CREACIÓN DE TABLAS (Existentes)
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'administrator', 'founder') DEFAULT 'user',
    avatar_path VARCHAR(255),
    account_status ENUM('active', 'deleted', 'suspended') DEFAULT 'active',
    suspension_ends_at DATETIME DEFAULT NULL,
    status_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_recovery_codes JSON DEFAULT NULL,
    -- [NUEVO] Contador persistente para suscriptores (Cache DB)
    subscribers_count INT DEFAULT 0
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
    expires_at DATETIME DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS server_config (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO server_config (config_key, config_value) VALUES 
('maintenance_mode', '0'),
('security_panic_mode', '0'),
('allow_registrations', '1'),
('allow_login', '1'),
('password_min_length', '8'),
('username_min_length', '4'),
('username_max_length', '20'),
('email_min_prefix_length', '3'),
('email_allowed_domains', '*'),
('upload_avatar_max_size', '2097152'),
('upload_avatar_max_dim', '4096'),
('security_login_max_attempts', '5'),
('security_block_duration', '15'),
('security_register_max_attempts', '10'),
('security_general_rate_limit', '10'),
('auth_verification_code_expiry', '15'),
('auth_reset_token_expiry', '60'),
('security_admin_require_2fa', '1'),
('upload_daily_limit', '10');

CREATE TABLE IF NOT EXISTS ws_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    session_id INT DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (token)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    target_type VARCHAR(50) NOT NULL, 
    target_id VARCHAR(100) DEFAULT NULL, 
    action VARCHAR(50) NOT NULL, 
    changes JSON DEFAULT NULL, 
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (target_type, target_id),
    INDEX (action)
);

CREATE TABLE IF NOT EXISTS `system_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(36) NOT NULL,
  `type` ENUM('performance', 'maintenance', 'policy') NOT NULL,
  `severity` ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
  `message` TEXT,
  `meta_data` JSON,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_active` (`is_active`)
);

CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` CHAR(36) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `batch_id` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) DEFAULT 'Sin título',
  `description` TEXT,
  `status` ENUM('uploading_chunks', 'assembling', 'queued', 'processing', 'waiting_for_metadata', 'published', 'error') DEFAULT 'uploading_chunks',
  `raw_file_path` VARCHAR(255) DEFAULT NULL,
  `hls_path` VARCHAR(255) DEFAULT NULL,
  `thumbnail_path` VARCHAR(255) DEFAULT NULL,
  `duration` INT DEFAULT 0,
  `processing_percentage` TINYINT DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `dominant_color` VARCHAR(7) DEFAULT '#000000',
  `generated_thumbnails` JSON DEFAULT NULL,
  `orientation` ENUM('landscape', 'portrait') DEFAULT 'landscape',
  `sprite_path` VARCHAR(255) DEFAULT NULL,
  `vtt_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- [NUEVO] Contadores persistentes para videos (Cache DB)
  `views_count` BIGINT DEFAULT 0,
  `likes_count` INT DEFAULT 0,
  `dislikes_count` INT DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_status` (`status`)
);

-- =========================================================
-- NUEVAS TABLAS (Project Aurora - Interaction Engine)
-- =========================================================

-- A. SUBSCRIPTIONS
-- Controla quién sigue a quién. Relación User -> User
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL, -- Usuario que se suscribe
    channel_id INT NOT NULL,    -- Usuario al que se suscriben
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES users(id) ON DELETE CASCADE,
    -- Restricción única para evitar doble suscripción
    UNIQUE KEY unique_subscription (subscriber_id, channel_id),
    -- Índice para contar suscriptores rápido
    INDEX idx_channel (channel_id)
);

-- B. VIDEO INTERACTIONS
-- Unificamos likes y dislikes en una sola tabla (Toggle logic)
CREATE TABLE IF NOT EXISTS video_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    type ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    -- Un usuario solo puede tener UNA interacción por video
    UNIQUE KEY unique_interaction (user_id, video_id)
);

-- C. VIDEO VIEWS (Historial y Seguridad)
-- Para contar visitas reales y prevenir spam (Log histórico)
CREATE TABLE IF NOT EXISTS video_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    user_id INT NULL, -- Puede ser NULL si es invitado
    ip_address VARCHAR(45) NOT NULL, -- Para limitar visitas por IP
    user_agent TEXT, -- Para detectar bots
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    -- Índice para limpiar logs viejos o análisis
    INDEX idx_video_date (video_id, viewed_at)
);