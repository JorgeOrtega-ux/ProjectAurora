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
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'e.g., login, forgot_password',
  `attempts` int(11) DEFAULT 1,
  `last_attempt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blocked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_action` (`ip_address`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES (1,'192.168.1.156','update_preference',1,'2026-02-20 23:04:53',NULL);
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `server_config`
--

DROP TABLE IF EXISTS `server_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_config` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `server_config`
--

LOCK TABLES `server_config` WRITE;
/*!40000 ALTER TABLE `server_config` DISABLE KEYS */;
INSERT INTO `server_config` VALUES ('allowed_email_domains','gmail.com,outlook.com,icloud.com,hotmail.com,yahoo.com','Allowed email domains','2026-02-20 23:01:17'),('max_email_local_length','64','Maximum email length before @','2026-02-20 23:01:17'),('max_password_length','64','Maximum password length','2026-02-20 23:01:17'),('max_username_length','32','Maximum username length','2026-02-20 23:01:17'),('min_email_local_length','4','Minimum email length before @','2026-02-20 23:01:17'),('min_password_length','13','Minimum password length','2026-02-21 21:28:41'),('min_username_length','3','Minimum username length','2026-02-20 23:01:17');
/*!40000 ALTER TABLE `server_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_changes_log`
--

DROP TABLE IF EXISTS `user_changes_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_changes_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `modified_field` varchar(50) NOT NULL COMMENT 'e.g., avatar, username, email, password',
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_changes_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_changes_log`
--

LOCK TABLES `user_changes_log` WRITE;
/*!40000 ALTER TABLE `user_changes_log` DISABLE KEYS */;
INSERT INTO `user_changes_log` VALUES (1,1,'avatar','storage/profilePictures/default/480fbe1db8eb1108867774bd6824bf33.png','storage/profilePictures/uploaded/e5c5e15100cc988f19550f40e6d88937_1771650299.jpg','2026-02-20 23:04:59'),(2,1,'username (by Admin ID: 1)','test','test-2','2026-02-21 13:50:15'),(3,1,'avatar (by Admin ID: 1)','storage/profilePictures/uploaded/e5c5e15100cc988f19550f40e6d88937_1771650299.jpg','storage/profilePictures/default/480fbe1db8eb1108867774bd6824bf33.png','2026-02-21 13:50:21'),(4,1,'pref_open_links_new_tab (by Admin ID: 1)','1','0','2026-02-21 13:57:10'),(5,1,'pref_theme (by Admin ID: 1)','light','dark','2026-02-21 14:27:27'),(6,1,'pref_theme (by Admin ID: 1)','dark','light','2026-02-21 14:27:31'),(7,1,'pref_extended_alerts (by Admin ID: 1)','0','1','2026-02-21 14:28:23'),(8,1,'pref_extended_alerts (by Admin ID: 1)','1','0','2026-02-21 14:28:24'),(9,1,'pref_theme (by Admin ID: 1)','light','system','2026-02-21 17:11:11'),(10,1,'pref_theme (by Admin ID: 1)','system','light','2026-02-21 17:11:15'),(11,1,'username (by Admin ID: 1)','test-2','test-3','2026-02-21 21:18:51');
/*!40000 ALTER TABLE `user_changes_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `language` varchar(10) DEFAULT 'en-us',
  `open_links_new_tab` tinyint(1) DEFAULT 1,
  `theme` varchar(20) DEFAULT 'system',
  `extended_alerts` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,'es-latam',0,'light',0,'2026-02-21 17:11:15');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES ('4f0co7lmr4hkd98hi1of0srgbr',1,'192.168.1.156','Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1','2026-02-21 14:22:45','2026-02-21 14:22:45'),('4lat5vb8ng2e77r3d6l2l7ok2g',1,'192.168.1.156','Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1','2026-02-21 16:48:49','2026-02-21 16:48:49'),('ajq92bloksff61q4m8odl6cd57',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 21:35:46','2026-02-21 21:08:39'),('at4fbg84u8vp7mrrkeidvi121c',1,'192.168.1.158','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 18:42:23','2026-02-21 12:43:37'),('dlp4n3hu6nhlq3d1ga2ae58th3',1,'192.168.1.156','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-20 23:15:55','2026-02-20 23:03:59'),('fdtdaki7b6f048lf6d5id0krib',1,'192.168.1.156','Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1','2026-02-21 16:48:04','2026-02-21 16:40:50'),('u6evi23cg250erq7rik61m07bj',1,'192.168.8.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-20 23:31:37','2026-02-20 23:23:40');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL COMMENT 'Unique identifier for the system',
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','moderator','administrator','founder') DEFAULT 'user' COMMENT 'User role for permissions and UI',
  `status` enum('active','deleted') DEFAULT 'active' COMMENT 'Current account lifecycle status',
  `is_suspended` tinyint(1) DEFAULT 0 COMMENT 'Access control flag',
  `suspension_type` enum('temporal','permanent') DEFAULT NULL COMMENT 'Type of suspension',
  `suspension_expires_at` datetime DEFAULT NULL COMMENT 'When temporal suspension ends',
  `suspension_reason` text DEFAULT NULL COMMENT 'Reason for suspension',
  `deletion_type` varchar(50) DEFAULT NULL COMMENT 'e.g., user_requested, admin_banned',
  `deletion_reason` text DEFAULT NULL COMMENT 'Reason for deletion',
  `avatar_path` varchar(255) DEFAULT NULL COMMENT 'Path to the image saved in storage',
  `two_factor_secret` varchar(255) DEFAULT NULL COMMENT 'Secret key for 2FA',
  `two_factor_enabled` tinyint(1) DEFAULT 0 COMMENT '2FA status',
  `two_factor_recovery_codes` text DEFAULT NULL COMMENT 'Recovery codes in JSON',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'480fbe1db8eb1108867774bd6824bf33','test-3','jorgeortega2405@gmail.com','$2y$10$akq.1na.KO.1WfvIHMNqg.BrDvKgUhOJiwsQHpeQI.A2TwQ01FqZu','founder','active',0,NULL,NULL,NULL,NULL,NULL,'storage/profilePictures/default/480fbe1db8eb1108867774bd6824bf33.png','EZLBLRDBTWJHS2UV',0,NULL,'2026-02-20 23:03:59');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_codes`
--

LOCK TABLES `verification_codes` WRITE;
/*!40000 ALTER TABLE `verification_codes` DISABLE KEYS */;
INSERT INTO `verification_codes` VALUES (2,'1','email_change','558480','{\"new_email\":\"jorgeortega24051@gmail.com\"}','2026-02-21 17:28:25','2026-02-21 23:13:25');
/*!40000 ALTER TABLE `verification_codes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-21 21:37:58
