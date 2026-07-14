-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: warehouse_system
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
-- Table structure for table `forecast`
--

DROP TABLE IF EXISTS `forecast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast` (
  `forecast_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `forecast_date` date DEFAULT NULL,
  `predicted_qty` decimal(10,2) DEFAULT NULL,
  `predicted_quantity` decimal(10,2) DEFAULT NULL,
  `lower_bound` decimal(10,2) DEFAULT NULL,
  `upper_bound` decimal(10,2) DEFAULT NULL,
  `model_used` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`forecast_id`),
  KEY `product_id` (`product_id`),
  KEY `forecast_date` (`forecast_date`),
  CONSTRAINT `forecast_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forecast`
--

LOCK TABLES `forecast` WRITE;
/*!40000 ALTER TABLE `forecast` DISABLE KEYS */;
/*!40000 ALTER TABLE `forecast` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (1,6,'User logged in','2025-11-07 10:45:05'),(2,6,'User logged in','2025-11-07 10:47:20'),(3,2,'User logged in','2025-11-09 06:09:54'),(4,2,'User logged in','2025-11-09 06:11:50'),(5,2,'User logged in','2025-11-09 06:12:56'),(6,2,'User logged in','2025-11-09 06:19:34');
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patterns`
--

DROP TABLE IF EXISTS `patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patterns` (
  `pattern_id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(255) DEFAULT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `support` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pattern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patterns`
--

LOCK TABLES `patterns` WRITE;
/*!40000 ALTER TABLE `patterns` DISABLE KEYS */;
/*!40000 ALTER TABLE `patterns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `reorder_point` int(11) DEFAULT 10,
  `unit` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uq_products_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `transaction_type` enum('stock_in','stock_out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) DEFAULT NULL,
  `new_stock` int(11) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_transactions_date` (`transaction_date`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','staff') NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'jdoe','ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f','admin','John Doe','IT Department','EMP001','jdoe@example.com','2025-10-29 09:27:24'),(2,'admin.msantos','$2y$10$yWBGL3obFcV4K7YVEgw61O27YIqMSavAsaQU9RLm4qDH7zSparNCW','admin','Milan Santos',NULL,'ADM-003','milanfrancosantos@gmail.com','2025-10-29 09:30:21'),(3,'admin.mone','$2y$10$L/OnydAIhfAybddf5y5Vme0WvtU.nj8E00QgdJ6Y10eAQGLU6reni','admin','mememe One',NULL,'ADM-003','milan@mail.com','2025-10-29 19:01:44'),(4,'manager.aone','$2y$10$YpjNR3j/z6keWW.PaKD2uu9tVdio8XrpWmubUa6Tqy2HEaRmOS...','manager','Ass One',NULL,'MNGR001','alexen@gmail.com','2025-10-29 19:05:18'),(5,'staff.aone','$2y$10$gdzji1V3DmbHWDFkdHhXWORo.Rv1AYtG0h71A9jhYYqjC/eJcTJm.','staff','Ashh One',NULL,'STF-01','prof1@mail.com','2025-10-29 19:06:58'),(6,'admin.rben','$2y$10$W2SXCmy83AnY4ISSh4fftOp2Jif4iNH9lcb7PFw34s3wm2.HGIVJe','admin','Rich Ben',NULL,'ADM-019','rich@gmail.com','2025-11-07 10:43:32');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_recommendations`
--

DROP TABLE IF EXISTS `product_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_recommendations` (
  `recommendation_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `avg_monthly_demand` decimal(10,2) DEFAULT NULL,
  `demand_std` decimal(10,2) DEFAULT NULL,
  `seasonal_strength` decimal(10,2) DEFAULT NULL,
  `recommended_stock` int(11) DEFAULT NULL,
  `restock_recommended` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`recommendation_id`),
  UNIQUE KEY `product_month` (`product_id`,`month`),
  KEY `product_id` (`product_id`),
  KEY `month` (`month`),
  CONSTRAINT `product_recommendations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_recommendations`
--

LOCK TABLES `product_recommendations` WRITE;
/*!40000 ALTER TABLE `product_recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forecast_runs`
--

DROP TABLE IF EXISTS `forecast_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast_runs` (
  `run_id` int(11) NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `status` enum('running','completed','error') DEFAULT 'running',
  `products_processed` int(11) DEFAULT NULL,
  `forecasts_generated` int(11) DEFAULT NULL,
  `anomalies_detected` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`run_id`),
  KEY `started_at` (`started_at`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forecast_runs`
--

LOCK TABLES `forecast_runs` WRITE;
/*!40000 ALTER TABLE `forecast_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `forecast_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `anomalies`
--

DROP TABLE IF EXISTS `anomalies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anomalies` (
  `anomaly_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `detection_date` date NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `anomaly_score` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`anomaly_id`),
  KEY `product_id` (`product_id`),
  KEY `detection_date` (`detection_date`),
  KEY `anomaly_score` (`anomaly_score`),
  CONSTRAINT `anomalies_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anomalies`
--

LOCK TABLES `anomalies` WRITE;
/*!40000 ALTER TABLE `anomalies` DISABLE KEYS */;
/*!40000 ALTER TABLE `anomalies` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-09 15:32:23
