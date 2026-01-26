-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: project_aurora_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profile_changes`
--

DROP TABLE IF EXISTS `profile_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profile_changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `change_type` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `change_type` (`change_type`),
  CONSTRAINT `profile_changes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profile_changes`
--

LOCK TABLES `profile_changes` WRITE;
/*!40000 ALTER TABLE `profile_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `profile_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_logs`
--

DROP TABLE IF EXISTS `security_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_identifier` varchar(255) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_identifier` (`user_identifier`),
  KEY `ip_address` (`ip_address`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
INSERT INTO `security_logs` VALUES (1,'12345@gmail.com','login_fail','192.168.1.157','2026-01-14 06:23:02'),(2,'12345@gmail.com','register_attempt','192.168.1.157','2026-01-14 06:23:09'),(3,'12345@gmail.com','register_code_req','192.168.1.157','2026-01-14 06:23:12'),(4,'1','pref_update','192.168.8.2','2026-01-16 18:25:13'),(5,'1','pref_update','192.168.8.2','2026-01-16 18:54:22'),(6,'1','2fa_init_attempt','192.168.8.2','2026-01-16 18:55:41'),(7,'1','2fa_regen_codes','192.168.8.2','2026-01-16 19:35:35'),(8,'1','2fa_init_attempt','192.168.8.2','2026-01-16 19:35:53'),(9,'1','2fa_init_attempt','192.168.8.2','2026-01-16 19:36:08'),(11,'Admin:1 | Deleted: backup_2026-01-16_21-25-16_418d2a.sql','backup_delete','192.168.8.2','2026-01-16 20:25:25'),(12,'Admin:1 | Restored: backup_2026-01-16_21-25-26_6ed4a8.sql','backup_restore','192.168.8.2','2026-01-16 20:25:33'),(14,'Admin:1 | Deleted: backup_2026-01-16_21-25-48_c6fa77.sql','backup_delete','192.168.8.2','2026-01-16 20:27:59');
/*!40000 ALTER TABLE `security_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `server_config`
--

DROP TABLE IF EXISTS `server_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_config` (
  `config_key` varchar(50) NOT NULL,
  `config_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `server_config`
--

LOCK TABLES `server_config` WRITE;
/*!40000 ALTER TABLE `server_config` DISABLE KEYS */;
INSERT INTO `server_config` VALUES ('allow_login','1','2026-01-14 06:19:08'),('allow_registrations','1','2026-01-14 06:19:08'),('auth_reset_token_expiry','60','2026-01-14 06:19:08'),('auth_verification_code_expiry','15','2026-01-14 06:19:08'),('auto_backup_enabled','1','2026-01-16 20:42:39'),('auto_backup_frequency','1','2026-01-16 20:42:39'),('auto_backup_retention','10','2026-01-16 20:38:21'),('email_allowed_domains','*','2026-01-14 06:19:08'),('email_min_prefix_length','3','2026-01-14 06:19:08'),('maintenance_mode','0','2026-01-16 19:19:11'),('password_min_length','8','2026-01-14 06:19:08'),('security_block_duration','15','2026-01-14 06:19:08'),('security_general_rate_limit','10','2026-01-14 06:19:08'),('security_login_max_attempts','5','2026-01-14 06:19:08'),('security_register_max_attempts','10','2026-01-14 06:19:08'),('upload_avatar_max_dim','4096','2026-01-14 06:19:08'),('upload_avatar_max_size','2097152','2026-01-14 06:19:08'),('username_max_length','20','2026-01-14 06:19:08'),('username_min_length','4','2026-01-14 06:19:08');
/*!40000 ALTER TABLE `server_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_tokens`
--

DROP TABLE IF EXISTS `user_auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `selector` char(24) NOT NULL,
  `hashed_validator` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `selector` (`selector`),
  CONSTRAINT `user_auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_tokens`
--

LOCK TABLES `user_auth_tokens` WRITE;
/*!40000 ALTER TABLE `user_auth_tokens` DISABLE KEYS */;
INSERT INTO `user_auth_tokens` VALUES (11,1,'6fde1dd2f3affa6af07f4bc2','903a4c9a4abaf7b086f56a226aa1d9f52046aaaf693f467913e2a53dbeeaaccd','192.168.8.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-02-15 19:55:10','2026-01-16 18:55:10');
/*!40000 ALTER TABLE `user_auth_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `language` varchar(20) NOT NULL DEFAULT 'es-latam',
  `open_links_new_tab` tinyint(1) NOT NULL DEFAULT 1,
  `theme` varchar(20) NOT NULL DEFAULT 'sync',
  `extended_toast` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,1,'es-latam',0,'light',1,'2026-01-16 18:54:22');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','moderator','administrator','founder') DEFAULT 'user',
  `avatar_path` varchar(255) DEFAULT NULL,
  `account_status` enum('active','deleted','suspended') DEFAULT 'active',
  `suspension_ends_at` datetime DEFAULT NULL,
  `status_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_recovery_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`two_factor_recovery_codes`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'90b0ada3-903a-42f4-9997-3f89a4ce7036','User792282','12345@gmail.com','$2y$10$9DQheyY9vDk.JK/k7.qiZehSXbZl7oPGz370owgoUON70nYXuahGe','founder','storage/profilePicture/default/90b0ada3-903a-42f4-9997-3f89a4ce7036.png','active',NULL,NULL,'2026-01-14 06:23:24','0',0,'[\"$2y$10$lOsnE2ZjouBfuYEqGFkpF.ZAqsmJXwr5NDDzFcY3Wb1O.9FVwXlwu\",\"$2y$10$pQ3rFnPDUgfzA9CswlqRIOVJM0FE4XkHl55L7.iIfyFzWMy5dhAhe\",\"$2y$10$r1YEPPXicVnAzpoRhn3EJu7RaOUL5qqd4XzJExySKmPMRSn.nbqWK\",\"$2y$10$u8wQPuk7ocHqVzsnHcMnju5YfoTG9DdHpZQUv.SyF0vBuz7e\\/T6DC\",\"$2y$10$eh7hm5k9IUps4vUmWWZ.luO75cN8zgya9E.nXKGqwvhnKYjePkq7y\",\"$2y$10$FM.21NTvC0zYJIK6IjnRn.HXndZOSTSWTSgA0CK.qR7NWAlCFN9UC\",\"$2y$10$yqLjFeg1E8vWWtmyM07Y4ujANDDiJ1lEH7S6Q5bgfONef4QFNzTGq\",\"$2y$10$JdcGsZHVKcCUCgLHuAYUfuKfLx8dZ3rOUu2mPgHRvwJgXp4Xslbqe\",\"$2y$10$m5FxZNIQGdYPBJ.9hohVK.oFyVPFQ1DroFF0u9aU7Omd.9wLg9N0e\",\"$2y$10$IMYYR6U7j13RY5ntYSEz\\/e2TTPnCvy\\/krZfnK9G1t.eiqgJbrRNSq\"]');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_codes`
--

DROP TABLE IF EXISTS `verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL,
  `code_type` varchar(50) NOT NULL DEFAULT 'account_activation',
  `code` char(6) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`),
  KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_codes`
--

LOCK TABLES `verification_codes` WRITE;
/*!40000 ALTER TABLE `verification_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `verification_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ws_auth_tokens`
--

DROP TABLE IF EXISTS `ws_auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ws_auth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `token_2` (`token`),
  CONSTRAINT `ws_auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ws_auth_tokens`
--

LOCK TABLES `ws_auth_tokens` WRITE;
/*!40000 ALTER TABLE `ws_auth_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `ws_auth_tokens` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-16 14:46:44
