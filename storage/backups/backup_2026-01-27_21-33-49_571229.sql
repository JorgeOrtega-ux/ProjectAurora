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
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `target_type` (`target_type`,`target_id`),
  KEY `action` (`action`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:20'),(2,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:35'),(3,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"sync\",\"new\":\"light\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:36'),(4,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"light\",\"new\":\"sync\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:39'),(5,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:17'),(6,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"extended_toast\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:19'),(7,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:22'),(8,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"extended_toast\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:22');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
INSERT INTO `security_logs` VALUES (1,'12345@gmail.com','register_attempt','192.168.1.159','2026-01-26 03:06:21'),(2,'12345@gmail.com','register_code_req','192.168.1.159','2026-01-26 03:06:24'),(3,'1','email_change_req','192.168.1.159','2026-01-26 03:06:44'),(4,'1','pref_update','192.168.1.159','2026-01-26 03:06:52'),(5,'1','2fa_init_attempt','192.168.1.159','2026-01-26 04:19:18'),(6,'1','2fa_init_attempt','192.168.1.159','2026-01-26 04:19:24'),(7,'1','pref_update','192.168.1.159','2026-01-26 05:00:33'),(8,'1','pref_update','192.168.1.159','2026-01-26 05:00:33'),(9,'1','pref_update','192.168.1.159','2026-01-26 05:00:33'),(10,'1','pref_update','192.168.1.159','2026-01-26 05:00:34'),(11,'1','pref_update','192.168.1.159','2026-01-26 05:00:34'),(12,'1','pref_update','192.168.1.159','2026-01-26 05:00:34'),(13,'1','pref_update','192.168.1.159','2026-01-26 05:00:35'),(14,'1','pref_update','192.168.1.159','2026-01-26 05:00:40'),(15,'1','pref_update','192.168.1.159','2026-01-26 05:00:42'),(16,'1','pref_update','192.168.1.159','2026-01-26 05:00:47'),(17,'1','pref_update','192.168.1.159','2026-01-26 19:43:43'),(18,'1','2fa_init_attempt','192.168.1.159','2026-01-26 19:47:02'),(19,'1','2fa_init_attempt','192.168.1.159','2026-01-26 19:47:37'),(20,'1','pref_update','192.168.1.159','2026-01-26 20:47:40'),(21,'1','pref_update','192.168.1.159','2026-01-26 20:47:43'),(22,'1','pref_update','192.168.1.159','2026-01-26 20:47:59'),(23,'1','pref_update','192.168.1.159','2026-01-26 20:54:11'),(24,'1','pref_update','192.168.1.159','2026-01-26 20:54:21'),(25,'12345@gmail.com','login_fail','192.168.1.159','2026-01-26 21:30:14'),(26,'1','2fa_init_attempt','192.168.1.159','2026-01-26 22:31:03'),(27,'Admin:1 | Created: backup_2026-01-27_22-02-34_a85dc4.sql','backup_create','192.168.1.157','2026-01-27 21:02:37'),(28,'Admin:1 | Created: backup_2026-01-27_21-26-19_570779.sql','backup_create','127.0.0.1','2026-01-28 03:26:23'),(29,'Admin:1 | Created: backup_2026-01-27_21-26-28_570788.sql','backup_create','127.0.0.1','2026-01-28 03:26:29'),(30,'Admin:1 | Deleted: backup_2026-01-27_21-26-28_570788.sql','backup_delete','192.168.1.157','2026-01-28 03:26:40'),(31,'Admin:1 | Deleted: backup_2026-01-27_21-26-19_570779.sql','backup_delete','192.168.1.157','2026-01-28 03:26:40'),(32,'Admin:1 | Deleted: backup_2026-01-16_21-50-49_1e8be4.sql','backup_delete','192.168.1.157','2026-01-28 03:26:40'),(33,'Admin:1 | Deleted: backup_2026-01-16_21-46-43_ea79f7.sql','backup_delete','192.168.1.157','2026-01-28 03:26:40'),(34,'Admin:1 | Deleted: backup_2026-01-16_21-25-26_6ed4a8.sql','backup_delete','192.168.1.157','2026-01-28 03:26:40'),(35,'Admin:1 | Deleted: backup_2026-01-27_22-02-34_a85dc4.sql','backup_delete','192.168.1.157','2026-01-28 03:26:40'),(36,'Admin:1 | Created: backup_2026-01-27_21-26-43_570803.sql','backup_create','127.0.0.1','2026-01-28 03:26:43'),(37,'Admin:1 | Created: backup_2026-01-27_21-30-32_571032.sql','backup_create','127.0.0.1','2026-01-28 03:30:33'),(38,'Admin:1 | Created: backup_2026-01-27_21-31-04_571064.sql','backup_create','127.0.0.1','2026-01-28 03:31:05'),(39,'Admin:1 | Created: backup_2026-01-27_21-32-01_571121.sql','backup_create','127.0.0.1','2026-01-28 03:32:02');
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
INSERT INTO `server_config` VALUES ('allow_login','1','2026-01-26 02:26:09'),('allow_registrations','1','2026-01-26 02:26:09'),('auth_reset_token_expiry','60','2026-01-26 02:26:09'),('auth_verification_code_expiry','15','2026-01-26 02:26:09'),('email_allowed_domains','*','2026-01-26 02:26:09'),('email_min_prefix_length','3','2026-01-26 02:26:09'),('maintenance_mode','0','2026-01-26 02:26:09'),('password_min_length','8','2026-01-26 02:26:09'),('security_block_duration','15','2026-01-26 02:26:09'),('security_general_rate_limit','10','2026-01-26 02:26:09'),('security_login_max_attempts','5','2026-01-26 02:26:09'),('security_register_max_attempts','10','2026-01-26 02:26:09'),('upload_avatar_max_dim','4096','2026-01-26 02:26:09'),('upload_avatar_max_size','2097152','2026-01-26 02:26:09'),('username_max_length','20','2026-01-26 02:26:09'),('username_min_length','4','2026-01-26 02:26:09');
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_tokens`
--

LOCK TABLES `user_auth_tokens` WRITE;
/*!40000 ALTER TABLE `user_auth_tokens` DISABLE KEYS */;
INSERT INTO `user_auth_tokens` VALUES (17,1,'df2dcd2ca7d821641ac3e066','e6cf816d5e4f7b16727ce9b2983bdf2d9cd6f911452ca02f49de460307df041a','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-25 22:34:31','2026-01-26 21:34:31'),(18,1,'3ccfe91c56a403c520899679','138576b7f64c6e1517c88820b75a13f3d4855f0d5fedead3e0a5635e1ee3087e','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-25 22:34:55','2026-01-26 21:34:55'),(19,1,'64ee5c4592debcb486d07551','25faf858f8d5c4c0f51419b628fa88de1bd66a32af7818a0acf377b2700eb798','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-26 21:50:47','2026-01-27 20:50:47'),(20,1,'25517f328248ab717d7cda29','43eb4160ebd84d3566fb7d5f184c96a44dae7c5b8a483fed0a65521668e0d147','192.168.1.153','Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/144.0.7559.95 Mobile/15E148 Safari/604.1','2026-02-26 22:06:12','2026-01-27 21:06:12');
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,1,'es-latam',0,'light',0,'2026-01-28 02:56:22');
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
INSERT INTO `users` VALUES (1,'0cbe9127-a3fb-4495-93da-979c72363b1a','User784122','12345@gmail.com','$2y$10$UbexKJOaUEjrAMHT2Cm4zuGv4YFw8q4PLk5ydqydfiuVU5aosZglG','founder','storage/profilePicture/default/0cbe9127-a3fb-4495-93da-979c72363b1a.png','active',NULL,NULL,'2026-01-26 03:06:39',NULL,0,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_codes`
--

LOCK TABLES `verification_codes` WRITE;
/*!40000 ALTER TABLE `verification_codes` DISABLE KEYS */;
INSERT INTO `verification_codes` VALUES (2,'12345@gmail.com','email_update_auth','225584','{}','2026-01-26 04:21:44','2026-01-26 03:06:44');
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
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

-- Dump completed on 2026-01-27 21:33:50
