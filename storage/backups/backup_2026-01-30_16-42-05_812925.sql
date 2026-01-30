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
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:20'),(2,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:35'),(3,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"sync\",\"new\":\"light\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:36'),(4,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"light\",\"new\":\"sync\"}','192.168.1.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-26 04:21:39'),(5,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:17'),(6,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"extended_toast\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:19'),(7,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:22'),(8,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"extended_toast\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 02:56:22'),(9,1,'server_config','username_min_length','UPDATE_CONFIG','{\"old\":\"4\",\"new\":\"6\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 19:11:21'),(10,1,'server_config','username_min_length','UPDATE_CONFIG','{\"old\":\"6\",\"new\":\"4\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-28 19:14:06'),(11,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:21:17'),(12,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:21:24'),(13,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:25:56'),(14,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:08'),(15,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:14'),(16,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"light\",\"new\":\"sync\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:19'),(17,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"sync\",\"new\":\"light\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:23'),(18,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:26'),(19,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"light\",\"new\":\"sync\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:34'),(20,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"theme\",\"old\":\"sync\",\"new\":\"light\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:26:38'),(21,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:29:21'),(22,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:29:26'),(23,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-latam\",\"new\":\"es-mx\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:29:30'),(24,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"language\",\"old\":\"es-mx\",\"new\":\"es-latam\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 19:29:32'),(25,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:39'),(26,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:39'),(27,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:40'),(28,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:40'),(29,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:41'),(30,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:41'),(31,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:41'),(32,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:41'),(33,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:41'),(34,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:41'),(35,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:42'),(36,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:42'),(37,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:42'),(38,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:42'),(39,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:42'),(40,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:42'),(41,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:43'),(42,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:43'),(43,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:43'),(44,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:43'),(45,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:43'),(46,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:43'),(47,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:44'),(48,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:44'),(49,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:44'),(50,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:44'),(51,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:44'),(52,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:45'),(53,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:45'),(54,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:45'),(55,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:45'),(56,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:45'),(57,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:46'),(58,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:46'),(59,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(60,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(61,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(62,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(63,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(64,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(65,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(66,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(67,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"0\",\"new\":\"1\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47'),(68,1,'user','1','UPDATE_PREFERENCE','{\"key\":\"open_links_new_tab\",\"old\":\"1\",\"new\":\"0\"}','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:26:47');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profile_changes`
--

LOCK TABLES `profile_changes` WRITE;
/*!40000 ALTER TABLE `profile_changes` DISABLE KEYS */;
INSERT INTO `profile_changes` VALUES (1,1,'2fa','disabled','enabled','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 06:01:04'),(2,1,'avatar_update','storage/profilePicture/default/0cbe9127-a3fb-4495-93da-979c72363b1a.png','storage/profilePicture/custom/0cbe9127-a3fb-4495-93da-979c72363b1a-1769755346.png','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 06:42:27'),(3,1,'avatar_delete','storage/profilePicture/custom/0cbe9127-a3fb-4495-93da-979c72363b1a-1769755346.png','storage/profilePicture/default/0cbe9127-a3fb-4495-93da-979c72363b1a-1769755897.png (Default)','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 06:51:38'),(4,1,'avatar_update','storage/profilePicture/default/0cbe9127-a3fb-4495-93da-979c72363b1a-1769755897.png','storage/profilePicture/custom/0cbe9127-a3fb-4495-93da-979c72363b1a-1769794479.png','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 17:34:39'),(5,1,'username','User784122','User784122t','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-01-30 22:21:51');
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
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
INSERT INTO `security_logs` VALUES (113,'1','email_change_req','192.168.1.157','2026-01-30 18:30:02'),(114,'1','email_change_req','192.168.1.157','2026-01-30 18:31:48'),(115,'1','email_change_req','192.168.1.157','2026-01-30 18:35:52'),(116,'1','pref_update','192.168.1.157','2026-01-30 18:37:29'),(117,'1','pref_update','192.168.1.157','2026-01-30 18:37:29'),(118,'1','pref_update','192.168.1.157','2026-01-30 18:37:30'),(119,'1','pref_update','192.168.1.157','2026-01-30 18:37:30'),(120,'1','email_change_req','192.168.1.157','2026-01-30 18:42:22'),(121,'1na@gmail.com','register_attempt','192.168.1.157','2026-01-30 18:44:30'),(122,'1na@gmail.com','register_code_req','192.168.1.157','2026-01-30 18:44:32'),(123,'1na@gmail.com','resend_code_req','192.168.1.157','2026-01-30 18:50:52'),(124,'1na@gmail.com','resend_code_req','192.168.1.157','2026-01-30 18:52:54'),(125,'1na@gmail.com','resend_code_req','192.168.1.157','2026-01-30 18:54:04'),(126,'12341@gmail.com','login_fail','192.168.1.157','2026-01-30 18:54:59'),(127,'1','pref_update','192.168.1.157','2026-01-30 18:55:33'),(128,'1','pref_update','192.168.1.157','2026-01-30 18:55:34'),(129,'12345@gmail.com','recovery_success','192.168.1.157','2026-01-30 18:58:51'),(130,'aguilar.131103jimena@gmail.com','register_attempt','192.168.1.157','2026-01-30 19:00:15'),(131,'aguilar.131103jimena@gmail.com','register_code_req','192.168.1.157','2026-01-30 19:00:16'),(132,'aguilar.131103jimena@gmail.com','resend_code_req','192.168.1.157','2026-01-30 19:02:49'),(133,'1111111a@gmail.com','register_attempt','192.168.1.157','2026-01-30 19:03:33'),(134,'1111111a@gmail.com','register_code_req','192.168.1.157','2026-01-30 19:03:36'),(135,'12345@gmail.com','recovery_success','192.168.1.157','2026-01-30 19:04:03'),(136,'al203280518190088@gmail.com','login_fail','192.168.1.157','2026-01-30 19:05:53'),(137,'1','email_change_req','192.168.1.157','2026-01-30 19:09:30'),(138,'1','pref_update','192.168.1.157','2026-01-30 19:12:09'),(139,'1','email_change_req','192.168.1.157','2026-01-30 19:13:07'),(140,'1','2fa_init_attempt','192.168.1.157','2026-01-30 19:51:21'),(141,'1','email_change_req','192.168.1.157','2026-01-30 19:51:41'),(142,'1','email_change_req','192.168.1.157','2026-01-30 21:24:32'),(143,'1','email_change_req','192.168.1.157','2026-01-30 21:32:13');
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
INSERT INTO `server_config` VALUES ('allow_login','1','2026-01-26 02:26:09'),('allow_registrations','1','2026-01-26 02:26:09'),('auth_reset_token_expiry','60','2026-01-26 02:26:09'),('auth_verification_code_expiry','15','2026-01-26 02:26:09'),('email_allowed_domains','*','2026-01-26 02:26:09'),('email_min_prefix_length','3','2026-01-26 02:26:09'),('maintenance_mode','0','2026-01-26 02:26:09'),('password_min_length','8','2026-01-26 02:26:09'),('security_block_duration','15','2026-01-26 02:26:09'),('security_general_rate_limit','10','2026-01-26 02:26:09'),('security_login_max_attempts','5','2026-01-26 02:26:09'),('security_register_max_attempts','10','2026-01-26 02:26:09'),('upload_avatar_max_dim','4096','2026-01-26 02:26:09'),('upload_avatar_max_size','2097152','2026-01-26 02:26:09'),('username_max_length','20','2026-01-26 02:26:09'),('username_min_length','4','2026-01-28 19:14:06');
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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_tokens`
--

LOCK TABLES `user_auth_tokens` WRITE;
/*!40000 ALTER TABLE `user_auth_tokens` DISABLE KEYS */;
INSERT INTO `user_auth_tokens` VALUES (30,1,'8f54893d70d165a4faeab279','e39fd1905c7b0d3af5bbf4177c214fbb617e68a9721b467f846e4da3d1ad6cf4','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-03-01 21:44:25','2026-01-30 20:44:25');
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
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,1,'es-latam',0,'light',0,'2026-01-30 22:26:47');
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
INSERT INTO `users` VALUES (1,'0cbe9127-a3fb-4495-93da-979c72363b1a','User784122t','12345@gmail.com','$2y$10$zhRjIQsstgGGHUlDnyGriuCQF/LBzpHhMUpoMS2xcXAArSWiKfiOq','founder','storage/profilePicture/custom/0cbe9127-a3fb-4495-93da-979c72363b1a-1769794479.png','active',NULL,NULL,'2026-01-26 03:06:39','OK4HO4W6TJ6CE7W',0,'[\"$2y$10$UXlTO0Nal01ZfpUNSM.N0.HChoAWPG\\/raAosJiBi6guWfMkrLszE6\",\"$2y$10$iUtCN3jpfKJIhlFFlHuBiOU0391JpCCkM4bjzYIZbrtfnNAk8Usiq\",\"$2y$10$zjreqBOVtITBDs4BEpkjBe9swJH65ZWzKChn6PPUYFy4VBUFVZfdS\",\"$2y$10$RwiyueK9mfX85S2y6apR5Ocabw9RsrYyjFuBFVDMTuu9NhGg1rxjm\",\"$2y$10$ofehks1Hdj58ku6Fw6q4Ju1HYNlQTdo80cYkDf0p2V9OKfhZE2pt2\",\"$2y$10$JBXeU7rjNj.v63fjPbv\\/rORIUKaI5XfMIFnUdUf1wdeKAbDjw0MDO\",\"$2y$10$rjMTUbK\\/ZEeSykbHO09EJuw921CDeB5PyKC8FUyijupWEtTf3h3Ee\",\"$2y$10$M3Cspv4an9Y\\/J4PNug0TwOAmwR0qq2WBk4sbQLzPXf8WUQfXKY5ba\",\"$2y$10$4HTAmfD8I.5UttNSYSaGMezpVuz4ZGTqMs1\\/Fc5DW1ubmqx7vNQbi\",\"$2y$10$GuZCIUtBo0x4xR0FhiXQFuP.NM\\/xrzZczbtVRycFbzxQi8DTdGLaK\"]');
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_codes`
--

LOCK TABLES `verification_codes` WRITE;
/*!40000 ALTER TABLE `verification_codes` DISABLE KEYS */;
INSERT INTO `verification_codes` VALUES (20,'12345@gmail.com','email_update_auth','665470','{}','2026-01-30 19:46:48','2026-01-30 18:31:48');
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

-- Dump completed on 2026-01-30 16:42:09
