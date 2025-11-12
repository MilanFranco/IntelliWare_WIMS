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

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Dairy Products','dairy-products','2025-11-09 12:47:07'),(3,'Beverages','beverages','2025-11-09 12:47:30'),(4,'Canned Goods','canned-goods','2025-11-09 12:47:30'),(5,'Dry Goods','dry-goods','2025-11-09 12:47:30'),(6,'Frozen Foods','frozen-foods','2025-11-09 12:47:30'),(7,'Bakery Products','bakery-products','2025-11-09 12:47:30'),(8,'Personal Care','personal-care','2025-11-09 12:47:30'),(9,'Household Items','household-items','2025-11-09 12:47:30'),(10,'Pet Supplies','pet-supplies','2025-11-09 12:47:30'),(11,'Stationery and Office Supplies','stationery-office-supplies','2025-11-09 12:47:30');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

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
  `predicted_qty` int(11) DEFAULT NULL,
  `predicted_quantity` decimal(10,2) DEFAULT NULL,
  `model_used` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lower_bound` decimal(10,2) DEFAULT NULL,
  `upper_bound` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`forecast_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `forecast_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forecast`
--

LOCK TABLES `forecast` WRITE;
/*!40000 ALTER TABLE `forecast` DISABLE KEYS */;
INSERT INTO `forecast` VALUES (1,1,'2025-12-09',69,NULL,'naive_avg_php','2025-11-09 13:57:21',NULL,NULL),(2,2,'2025-12-09',77,NULL,'naive_avg_php','2025-11-09 13:57:21',NULL,NULL),(3,3,'2025-12-09',71,NULL,'naive_avg_php','2025-11-09 13:57:21',NULL,NULL),(4,4,'2025-12-09',83,NULL,'naive_avg_php','2025-11-09 13:57:21',NULL,NULL),(5,5,'2025-12-09',74,NULL,'naive_avg_php','2025-11-09 13:57:21',NULL,NULL);
/*!40000 ALTER TABLE `forecast` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forecast_runs`
--

DROP TABLE IF EXISTS `forecast_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `started_at` datetime NOT NULL,
  `finished_at` datetime NOT NULL,
  `status` enum('running','completed','error') DEFAULT 'running',
  `products_processed` int(11) DEFAULT NULL,
  `forecasts_generated` int(11) DEFAULT NULL,
  `anomalies_detected` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `duration_ms` int(11) NOT NULL,
  `products_checked` int(11) NOT NULL DEFAULT 0,
  `forecasts_created` int(11) NOT NULL DEFAULT 0,
  `notifications_created` int(11) NOT NULL DEFAULT 0,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `output` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_forecast_runs_started` (`started_at`),
  KEY `started_at` (`started_at`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forecast_runs`
--

LOCK TABLES `forecast_runs` WRITE;
/*!40000 ALTER TABLE `forecast_runs` DISABLE KEYS */;
INSERT INTO `forecast_runs` VALUES (1,'2025-11-09 14:52:10','2025-11-09 14:52:10','running',NULL,NULL,NULL,NULL,1,0,0,0,1,'Starting PHP forecast (lookback=90 days, horizon=30 days)\nForecast run complete. Products checked: 0. Notifications created: 0.\n',NULL),(2,'2025-11-09 14:57:21','2025-11-09 14:57:21','running',NULL,NULL,NULL,NULL,20,5,5,1,1,'Starting PHP forecast (lookback=90 days, horizon=30 days)\nCreated notification for product_id=2: Oatside Oat Milk\nForecast run complete. Products checked: 5. Notifications created: 1.\n',NULL),(3,'2025-11-09 23:53:26','2025-11-09 23:53:26','error',NULL,NULL,NULL,'Python executable not found at: C:\\Python39\\python.exe. Please update the python_path in MLForecastManager.php',0,0,0,0,0,NULL,NULL),(4,'2025-11-10 00:04:41','2025-11-10 00:04:41','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(5,'2025-11-10 00:10:10','2025-11-10 00:10:10','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(6,'2025-11-10 00:34:30','2025-11-10 00:34:30','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(7,'2025-11-10 01:24:13','2025-11-10 01:24:13','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(8,'2025-11-10 01:28:06','2025-11-10 01:28:06','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(9,'2025-11-10 01:28:27','2025-11-10 01:28:27','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(10,'2025-11-10 01:30:48','2025-11-10 01:30:48','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(11,'2025-11-10 23:06:55','2025-11-10 23:06:55','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(12,'2025-11-11 13:21:10','2025-11-11 13:21:10','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(13,'2025-11-11 13:23:39','2025-11-11 13:23:40','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(14,'2025-11-11 13:39:13','2025-11-11 13:39:13','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(15,'2025-11-11 13:39:49','2025-11-11 13:39:49','error',NULL,NULL,NULL,'Python executable not found. Python 3.9 or later is required for AI forecast features.\n\nQUICK FIX:\n1. Download Python from: https://www.python.org/downloads/\n2. During installation, check \'Add Python to PATH\'\n3. Restart your web server after installation\n\nALTERNATIVE:\nIf Python is already installed, set the path in:\n- src/ml/MLForecastManager.php (line 18)\n- src/ml/ProductRecommendationManager.php (line 17)\n\nFor detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md',0,0,0,0,0,NULL,NULL),(16,'2025-11-11 13:57:27','2025-11-11 13:57:32','error',NULL,NULL,NULL,'No valid JSON output from Python script. Output: C:\\xampp\\htdocs\\IntelliWare\\src\\ml\\forecast_engine.py:61: UserWarning: pandas only supports SQLAlchemy connectable (engine/connection) or database string URI or sqlite3 DBAPI2 connection. Other DBAPI2 objects are not tested. Please consider using SQLAlchemy.\n  df = pd.read_sql(query, conn)\n2025-11-11 13:57:32,008 - ERROR - Error in forecast generation: Execution failed on sql \'\n        SELECT\n            t.transaction_date,\n            t.product_id,\n            p.product_name,\n            p.category,\n            t.quantity,\n            t.type\n        FROM transactions t\n        JOIN products p ON t.product_id = p.product_id\n        WHERE t.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)\n        ORDER BY t.transaction_date\n        \': 1054 (42S22): Unknown column \'t.type\' in \'field list\'\n{\n  \"status\": \"error\",\n  \"message\": \"Execution failed on sql \'\\n        SELECT \\n            t.transaction_date,\\n            t.product_id,\\n            p.product_name,\\n            p.category,\\n            t.quantity,\\n            t.type\\n        FROM transactions t\\n        JOIN products p ON t.product_id = p.product_id\\n        WHERE t.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)\\n        ORDER BY t.transaction_date\\n        \': 1054 (42S22): Unknown column \'t.type\' in \'field list\'\",\n  \"timestamp\": \"2025-11-11T13:57:32.008611\"\n}',0,0,0,0,0,NULL,NULL),(17,'2025-11-11 13:58:02','2025-11-11 13:58:06','error',NULL,NULL,NULL,'No valid JSON output from Python script. Output: C:\\xampp\\htdocs\\IntelliWare\\src\\ml\\forecast_engine.py:61: UserWarning: pandas only supports SQLAlchemy connectable (engine/connection) or database string URI or sqlite3 DBAPI2 connection. Other DBAPI2 objects are not tested. Please consider using SQLAlchemy.\n  df = pd.read_sql(query, conn)\n2025-11-11 13:58:06,035 - ERROR - Error in forecast generation: Execution failed on sql \'\n        SELECT\n            t.transaction_date,\n            t.product_id,\n            p.product_name,\n            p.category,\n            t.quantity,\n            t.type\n        FROM transactions t\n        JOIN products p ON t.product_id = p.product_id\n        WHERE t.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)\n        ORDER BY t.transaction_date\n        \': 1054 (42S22): Unknown column \'t.type\' in \'field list\'\n{\n  \"status\": \"error\",\n  \"message\": \"Execution failed on sql \'\\n        SELECT \\n            t.transaction_date,\\n            t.product_id,\\n            p.product_name,\\n            p.category,\\n            t.quantity,\\n            t.type\\n        FROM transactions t\\n        JOIN products p ON t.product_id = p.product_id\\n        WHERE t.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)\\n        ORDER BY t.transaction_date\\n        \': 1054 (42S22): Unknown column \'t.type\' in \'field list\'\",\n  \"timestamp\": \"2025-11-11T13:58:06.035707\"\n}',0,0,0,0,0,NULL,NULL),(18,'2025-11-11 14:00:20','2025-11-11 14:00:24','error',NULL,NULL,NULL,'No valid JSON output from Python script. Output: 2025-11-11 14:00:23,665 - ERROR - Error in forecast generation: \'Prophet\' object has no attribute \'stan_backend\'\n{\n  \"status\": \"error\",\n  \"message\": \"\'Prophet\' object has no attribute \'stan_backend\'\",\n  \"timestamp\": \"2025-11-11T14:00:23.665532\"\n}',0,0,0,0,0,NULL,NULL),(19,'2025-11-11 14:02:24','2025-11-11 14:02:27','error',NULL,NULL,NULL,'No valid JSON output from Python script. Output: 2025-11-11 14:02:27,547 - WARNING - Prophet not available, using simple forecast: \'Prophet\' object has no attribute \'stan_backend\'\n2025-11-11 14:02:27,547 - WARNING - Prophet not available, using simple forecast: \'Prophet\' object has no attribute \'stan_backend\'\n2025-11-11 14:02:27,547 - WARNING - Prophet not available, using simple forecast: \'Prophet\' object has no attribute \'stan_backend\'\n2025-11-11 14:02:27,547 - WARNING - Prophet not available, using simple forecast: \'Prophet\' object has no attribute \'stan_backend\'\n2025-11-11 14:02:27,547 - WARNING - Prophet not available, using simple forecast: \'Prophet\' object has no attribute \'stan_backend\'\n{\n  \"status\": \"success\",\n  \"forecasts_generated\": 0,\n  \"anomalies_detected\": 0,\n  \"timestamp\": \"2025-11-11T14:02:27.547282\"\n}',0,0,0,0,0,NULL,NULL),(20,'2025-11-11 14:04:33','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(21,'2025-11-11 14:04:47','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(22,'2025-11-11 14:09:57','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(23,'2025-11-11 15:13:49','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(24,'2025-11-11 17:33:27','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(25,'2025-11-11 20:29:18','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(26,'2025-11-11 21:03:05','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL),(27,'2025-11-12 17:59:48','0000-00-00 00:00:00','running',NULL,NULL,NULL,NULL,0,0,0,0,0,NULL,NULL);
/*!40000 ALTER TABLE `forecast_runs` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (1,6,'User logged in','2025-11-07 10:45:05'),(2,6,'User logged in','2025-11-07 10:47:20'),(3,2,'User logged in','2025-11-09 06:09:54'),(4,2,'User logged in','2025-11-09 06:11:50'),(5,2,'User logged in','2025-11-09 06:12:56'),(6,2,'User logged in','2025-11-09 06:19:34'),(7,8,'User logged in','2025-11-09 13:29:14'),(8,8,'User logged in','2025-11-09 13:38:40'),(9,8,'User logged in','2025-11-09 13:48:42'),(10,8,'User logged in','2025-11-09 17:27:57'),(11,8,'User logged in','2025-11-10 04:15:49'),(12,8,'User logged in','2025-11-10 10:55:42'),(13,8,'User logged in','2025-11-11 04:35:10'),(14,8,'User logged in','2025-11-11 05:39:39'),(15,8,'User logged in','2025-11-11 06:12:49'),(16,9,'Processed stock_out transaction for product ID 1','2025-11-11 12:27:25'),(17,9,'User logged in','2025-11-12 09:54:28'),(18,9,'User logged in','2025-11-12 10:00:33');
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL = global notification',
  `type` varchar(50) DEFAULT NULL,
  `message` varchar(1024) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_isread` (`user_id`,`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,NULL,'forecast_alert','Forecast: Oatside Oat Milk - predicted 77 units over next 30 days (avg 2.57/day); current stock: 60.','{\"product_id\":2,\"forecast_qty\":77,\"avg_daily\":2.567,\"horizon_days\":30}',0,'2025-11-09 13:57:21');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
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
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `reorder_point` int(11) DEFAULT 10,
  `unit` varchar(20) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `location` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Alaska Evap','Dairy Products','Alaska',120,24,'packs','2025-12-20','2025-11-09 12:29:30','Shelf A1',1),(2,'Oatside Oat Milk','Dairy Products','Oatside',60,12,'bottles','2026-01-03','2025-11-09 12:29:30','Shelf A2',1),(3,'Anchor Milk','Dairy Products','Anchor',90,18,'boxes','2025-11-15','2025-11-09 12:29:30','Shelf A3',1),(4,'Coca-Cola 330ml','Beverages','Coca-Cola',200,40,'cans','2026-06-30','2025-11-09 12:29:30','Shelf B1',3),(5,'Pepsi 330ml','Beverages','Pepsi',180,36,'cans','2026-06-30','2025-11-09 12:29:30','Shelf B2',3),(6,'Nescafé Classic','Beverages','Nescafé',95,19,'jars','2026-03-15','2025-11-09 12:29:30','Shelf B3',3),(7,'Century Tuna Flakes','Canned Goods','Century',140,28,'cans','2027-02-28','2025-11-09 12:29:30','Shelf C1',4),(8,'Argentina Corned Beef','Canned Goods','Argentina',110,22,'cans','2026-12-31','2025-11-09 12:29:30','Shelf C2',4),(9,'Dona Maria Rice 5kg','Dry Goods','Dona Maria',300,60,'bags','2027-08-01','2025-11-09 12:29:30','Shelf D1',5),(10,'Royal Pasta 500g','Dry Goods','Royal',250,50,'packs','2026-11-30','2025-11-09 12:29:30','Shelf D2',5),(11,'Purefoods Chicken Nuggets','Frozen Foods','Purefoods',160,32,'boxes','2026-05-20','2025-11-09 12:29:30','Freezer F1',6),(12,'Monterey Frozen Fish Fillet','Frozen Foods','Monterey',120,24,'packs','2026-04-10','2025-11-09 12:29:30','Freezer F2',6),(13,'Gardenia Bread Loaf','Bakery Products','Gardenia',220,44,'pieces','2025-11-20','2025-11-09 12:29:30','Shelf E1',7),(14,'Gardenia Cakes 6pcs','Bakery Products','Gardenia',80,16,'boxes','2025-12-05','2025-11-09 12:29:30','Shelf E2',7),(15,'Colgate Toothpaste 100g','Personal Care','Colgate',300,60,'tubes','2027-07-01','2025-11-09 12:29:30','Shelf P1',8),(16,'Dove Soap Bar','Personal Care','Dove',260,52,'bars','2027-01-15','2025-11-09 12:29:30','Shelf P2',8),(17,'Surf Detergent 3kg','Household Items','Surf',150,30,'packs',NULL,'2025-11-09 12:29:30','Shelf H1',9),(18,'All-Purpose Cleaner','Household Items','Generic',180,36,'bottles',NULL,'2025-11-09 12:29:30','Shelf H2',9),(19,'Whiskas Cat Food 1kg','Pet Supplies','Whiskas',130,26,'bags','2026-09-10','2025-11-09 12:29:30','Shelf S1',10),(20,'Pedigree Dog Food 2kg','Pet Supplies','Pedigree',140,28,'bags','2026-10-20','2025-11-09 12:29:30','Shelf S2',10),(21,'Pilot Gel Pen (Box)','Stationery and Office Supplies','Pilot',400,80,'boxes',NULL,'2025-11-09 12:29:30','Shelf O1',11),(22,'A4 Notebooks (set)','Stationery and Office Supplies','Generic',350,70,'sets',NULL,'2025-11-09 12:29:30','Shelf O2',11),(23,'Alaska Evaporated Milk 1L',NULL,'Alaska',120,10,NULL,'2025-12-20','2025-11-09 12:49:04','Shelf A1',1),(24,'Oatside Oat Milk 1L',NULL,'Oatside',60,10,NULL,'2026-01-03','2025-11-09 12:49:04','Shelf A2',1),(25,'Anchor Butter 200g',NULL,'Anchor',90,10,NULL,'2025-11-15','2025-11-09 12:49:04','Shelf A3',1),(26,'Coca-Cola 330ml',NULL,'Coca-Cola',200,10,NULL,'2026-06-30','2025-11-09 12:49:04','Shelf B1',3),(27,'Pepsi 330ml',NULL,'Pepsi',150,10,NULL,'2026-06-30','2025-11-09 12:49:04','Shelf B2',3),(28,'Nescafé Coffee 100g',NULL,'Nescafé',80,10,NULL,'2026-03-15','2025-11-09 12:49:04','Shelf B3',3),(29,'Century Tuna Hot & Spicy 180g',NULL,'Century',300,10,NULL,'2026-01-10','2025-11-09 12:49:04','Shelf C1',4),(30,'Argentina Corned Beef 150g',NULL,'Argentina',200,10,NULL,'2026-02-20','2025-11-09 12:49:04','Shelf C2',4),(31,'Dona Maria Rice 5kg',NULL,'Dona Maria',100,10,NULL,'2026-12-31','2025-11-09 12:49:04','Shelf D1',5),(32,'Royal Pasta 500g',NULL,'Royal',120,10,NULL,'2026-08-15','2025-11-09 12:49:04','Shelf D2',5),(33,'Purefoods Chicken Nuggets 1kg',NULL,'Purefoods',150,10,NULL,'2025-12-31','2025-11-09 12:49:04','Shelf E1',6),(34,'Monterey Frozen Fish 500g',NULL,'Monterey',80,10,NULL,'2025-12-31','2025-11-09 12:49:04','Shelf E2',6),(35,'Gardenia White Bread 400g',NULL,'Gardenia',200,10,NULL,'2025-11-10','2025-11-09 12:49:04','Shelf F1',7),(36,'Colgate Toothpaste 100g',NULL,'Colgate',100,10,NULL,'2026-05-30','2025-11-09 12:49:04','Shelf G1',8),(37,'Dove Soap 90g',NULL,'Dove',80,10,NULL,'2026-07-15','2025-11-09 12:49:04','Shelf G2',8),(38,'Surf Detergent 1kg',NULL,'Surf',120,10,NULL,'2026-12-31','2025-11-09 12:49:04','Shelf H1',9),(39,'Whiskas Cat Food 500g',NULL,'Whiskas',50,10,NULL,'2026-08-30','2025-11-09 12:49:04','Shelf I1',10),(40,'Pilot Pen Blue',NULL,'Pilot',300,10,NULL,'2028-12-31','2025-11-09 12:49:04','Shelf J1',11),(41,'Notebook A4',NULL,'National Bookstore',200,10,NULL,'2028-12-31','2025-11-09 12:49:04','Shelf J2',11);
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
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,1,NULL,'stock_out',3,'2025-08-10 16:00:00','Sample transaction for forecast testing'),(2,1,NULL,'stock_out',1,'2025-08-11 16:00:00','Sample transaction for forecast testing'),(3,1,NULL,'stock_out',3,'2025-08-12 16:00:00','Sample transaction for forecast testing'),(4,1,NULL,'stock_out',3,'2025-08-14 16:00:00','Sample transaction for forecast testing'),(5,1,NULL,'stock_out',1,'2025-08-15 16:00:00','Sample transaction for forecast testing'),(6,1,NULL,'stock_out',2,'2025-08-16 16:00:00','Sample transaction for forecast testing'),(7,1,NULL,'stock_out',5,'2025-08-17 16:00:00','Sample transaction for forecast testing'),(8,1,NULL,'stock_out',3,'2025-08-19 16:00:00','Sample transaction for forecast testing'),(9,1,NULL,'stock_out',4,'2025-08-20 16:00:00','Sample transaction for forecast testing'),(10,1,NULL,'stock_out',1,'2025-08-22 16:00:00','Sample transaction for forecast testing'),(11,1,NULL,'stock_out',2,'2025-08-23 16:00:00','Sample transaction for forecast testing'),(12,1,NULL,'stock_out',2,'2025-08-24 16:00:00','Sample transaction for forecast testing'),(13,1,NULL,'stock_out',2,'2025-08-27 16:00:00','Sample transaction for forecast testing'),(14,1,NULL,'stock_out',5,'2025-08-28 16:00:00','Sample transaction for forecast testing'),(15,1,NULL,'stock_out',2,'2025-08-29 16:00:00','Sample transaction for forecast testing'),(16,1,NULL,'stock_out',2,'2025-08-31 16:00:00','Sample transaction for forecast testing'),(17,1,NULL,'stock_out',2,'2025-09-01 16:00:00','Sample transaction for forecast testing'),(18,1,NULL,'stock_out',5,'2025-09-02 16:00:00','Sample transaction for forecast testing'),(19,1,NULL,'stock_out',2,'2025-09-03 16:00:00','Sample transaction for forecast testing'),(20,1,NULL,'stock_out',1,'2025-09-04 16:00:00','Sample transaction for forecast testing'),(21,1,NULL,'stock_out',5,'2025-09-05 16:00:00','Sample transaction for forecast testing'),(22,1,NULL,'stock_out',3,'2025-09-06 16:00:00','Sample transaction for forecast testing'),(23,1,NULL,'stock_out',3,'2025-09-08 16:00:00','Sample transaction for forecast testing'),(24,1,NULL,'stock_out',5,'2025-09-10 16:00:00','Sample transaction for forecast testing'),(25,1,NULL,'stock_out',3,'2025-09-11 16:00:00','Sample transaction for forecast testing'),(26,1,NULL,'stock_out',2,'2025-09-12 16:00:00','Sample transaction for forecast testing'),(27,1,NULL,'stock_out',2,'2025-09-13 16:00:00','Sample transaction for forecast testing'),(28,1,NULL,'stock_out',2,'2025-09-14 16:00:00','Sample transaction for forecast testing'),(29,1,NULL,'stock_out',4,'2025-09-16 16:00:00','Sample transaction for forecast testing'),(30,1,NULL,'stock_out',3,'2025-09-17 16:00:00','Sample transaction for forecast testing'),(31,1,NULL,'stock_out',1,'2025-09-18 16:00:00','Sample transaction for forecast testing'),(32,1,NULL,'stock_out',1,'2025-09-19 16:00:00','Sample transaction for forecast testing'),(33,1,NULL,'stock_out',1,'2025-09-20 16:00:00','Sample transaction for forecast testing'),(34,1,NULL,'stock_out',1,'2025-09-22 16:00:00','Sample transaction for forecast testing'),(35,1,NULL,'stock_out',5,'2025-09-23 16:00:00','Sample transaction for forecast testing'),(36,1,NULL,'stock_out',1,'2025-09-24 16:00:00','Sample transaction for forecast testing'),(37,1,NULL,'stock_out',3,'2025-09-25 16:00:00','Sample transaction for forecast testing'),(38,1,NULL,'stock_out',1,'2025-09-26 16:00:00','Sample transaction for forecast testing'),(39,1,NULL,'stock_out',5,'2025-09-27 16:00:00','Sample transaction for forecast testing'),(40,1,NULL,'stock_out',2,'2025-09-28 16:00:00','Sample transaction for forecast testing'),(41,1,NULL,'stock_out',4,'2025-09-29 16:00:00','Sample transaction for forecast testing'),(42,1,NULL,'stock_out',1,'2025-09-30 16:00:00','Sample transaction for forecast testing'),(43,1,NULL,'stock_out',3,'2025-10-01 16:00:00','Sample transaction for forecast testing'),(44,1,NULL,'stock_out',5,'2025-10-02 16:00:00','Sample transaction for forecast testing'),(45,1,NULL,'stock_out',5,'2025-10-03 16:00:00','Sample transaction for forecast testing'),(46,1,NULL,'stock_out',4,'2025-10-04 16:00:00','Sample transaction for forecast testing'),(47,1,NULL,'stock_out',2,'2025-10-05 16:00:00','Sample transaction for forecast testing'),(48,1,NULL,'stock_out',1,'2025-10-06 16:00:00','Sample transaction for forecast testing'),(49,1,NULL,'stock_out',4,'2025-10-07 16:00:00','Sample transaction for forecast testing'),(50,1,NULL,'stock_out',5,'2025-10-08 16:00:00','Sample transaction for forecast testing'),(51,1,NULL,'stock_out',4,'2025-10-09 16:00:00','Sample transaction for forecast testing'),(52,1,NULL,'stock_out',1,'2025-10-10 16:00:00','Sample transaction for forecast testing'),(53,1,NULL,'stock_out',5,'2025-10-11 16:00:00','Sample transaction for forecast testing'),(54,1,NULL,'stock_out',1,'2025-10-12 16:00:00','Sample transaction for forecast testing'),(55,1,NULL,'stock_out',3,'2025-10-13 16:00:00','Sample transaction for forecast testing'),(56,1,NULL,'stock_out',1,'2025-10-14 16:00:00','Sample transaction for forecast testing'),(57,1,NULL,'stock_out',2,'2025-10-15 16:00:00','Sample transaction for forecast testing'),(58,1,NULL,'stock_out',4,'2025-10-16 16:00:00','Sample transaction for forecast testing'),(59,1,NULL,'stock_out',1,'2025-10-17 16:00:00','Sample transaction for forecast testing'),(60,1,NULL,'stock_out',2,'2025-10-18 16:00:00','Sample transaction for forecast testing'),(61,1,NULL,'stock_out',3,'2025-10-19 16:00:00','Sample transaction for forecast testing'),(62,1,NULL,'stock_out',2,'2025-10-23 16:00:00','Sample transaction for forecast testing'),(63,1,NULL,'stock_out',5,'2025-10-24 16:00:00','Sample transaction for forecast testing'),(64,1,NULL,'stock_out',5,'2025-10-25 16:00:00','Sample transaction for forecast testing'),(65,1,NULL,'stock_out',3,'2025-10-26 16:00:00','Sample transaction for forecast testing'),(66,1,NULL,'stock_out',4,'2025-10-27 16:00:00','Sample transaction for forecast testing'),(67,1,NULL,'stock_out',2,'2025-10-28 16:00:00','Sample transaction for forecast testing'),(68,1,NULL,'stock_out',4,'2025-10-29 16:00:00','Sample transaction for forecast testing'),(69,1,NULL,'stock_out',3,'2025-10-30 16:00:00','Sample transaction for forecast testing'),(70,1,NULL,'stock_out',3,'2025-10-31 16:00:00','Sample transaction for forecast testing'),(71,1,NULL,'stock_out',2,'2025-11-01 16:00:00','Sample transaction for forecast testing'),(72,1,NULL,'stock_out',2,'2025-11-02 16:00:00','Sample transaction for forecast testing'),(73,1,NULL,'stock_out',3,'2025-11-04 16:00:00','Sample transaction for forecast testing'),(74,1,NULL,'stock_out',3,'2025-11-05 16:00:00','Sample transaction for forecast testing'),(75,1,NULL,'stock_out',2,'2025-11-07 16:00:00','Sample transaction for forecast testing'),(76,1,NULL,'stock_out',1,'2025-11-08 16:00:00','Sample transaction for forecast testing'),(77,2,NULL,'stock_out',3,'2025-08-10 16:00:00','Sample transaction for forecast testing'),(78,2,NULL,'stock_out',4,'2025-08-11 16:00:00','Sample transaction for forecast testing'),(79,2,NULL,'stock_out',4,'2025-08-13 16:00:00','Sample transaction for forecast testing'),(80,2,NULL,'stock_out',1,'2025-08-15 16:00:00','Sample transaction for forecast testing'),(81,2,NULL,'stock_out',2,'2025-08-16 16:00:00','Sample transaction for forecast testing'),(82,2,NULL,'stock_out',1,'2025-08-18 16:00:00','Sample transaction for forecast testing'),(83,2,NULL,'stock_out',2,'2025-08-19 16:00:00','Sample transaction for forecast testing'),(84,2,NULL,'stock_out',1,'2025-08-21 16:00:00','Sample transaction for forecast testing'),(85,2,NULL,'stock_out',1,'2025-08-22 16:00:00','Sample transaction for forecast testing'),(86,2,NULL,'stock_out',5,'2025-08-23 16:00:00','Sample transaction for forecast testing'),(87,2,NULL,'stock_out',5,'2025-08-24 16:00:00','Sample transaction for forecast testing'),(88,2,NULL,'stock_out',5,'2025-08-25 16:00:00','Sample transaction for forecast testing'),(89,2,NULL,'stock_out',3,'2025-08-26 16:00:00','Sample transaction for forecast testing'),(90,2,NULL,'stock_out',5,'2025-08-27 16:00:00','Sample transaction for forecast testing'),(91,2,NULL,'stock_out',1,'2025-08-28 16:00:00','Sample transaction for forecast testing'),(92,2,NULL,'stock_out',1,'2025-08-30 16:00:00','Sample transaction for forecast testing'),(93,2,NULL,'stock_out',2,'2025-08-31 16:00:00','Sample transaction for forecast testing'),(94,2,NULL,'stock_out',1,'2025-09-01 16:00:00','Sample transaction for forecast testing'),(95,2,NULL,'stock_out',1,'2025-09-02 16:00:00','Sample transaction for forecast testing'),(96,2,NULL,'stock_out',4,'2025-09-03 16:00:00','Sample transaction for forecast testing'),(97,2,NULL,'stock_out',5,'2025-09-04 16:00:00','Sample transaction for forecast testing'),(98,2,NULL,'stock_out',3,'2025-09-06 16:00:00','Sample transaction for forecast testing'),(99,2,NULL,'stock_out',3,'2025-09-07 16:00:00','Sample transaction for forecast testing'),(100,2,NULL,'stock_out',5,'2025-09-08 16:00:00','Sample transaction for forecast testing'),(101,2,NULL,'stock_out',4,'2025-09-11 16:00:00','Sample transaction for forecast testing'),(102,2,NULL,'stock_out',5,'2025-09-12 16:00:00','Sample transaction for forecast testing'),(103,2,NULL,'stock_out',1,'2025-09-13 16:00:00','Sample transaction for forecast testing'),(104,2,NULL,'stock_out',4,'2025-09-14 16:00:00','Sample transaction for forecast testing'),(105,2,NULL,'stock_out',1,'2025-09-15 16:00:00','Sample transaction for forecast testing'),(106,2,NULL,'stock_out',1,'2025-09-16 16:00:00','Sample transaction for forecast testing'),(107,2,NULL,'stock_out',5,'2025-09-17 16:00:00','Sample transaction for forecast testing'),(108,2,NULL,'stock_out',5,'2025-09-18 16:00:00','Sample transaction for forecast testing'),(109,2,NULL,'stock_out',2,'2025-09-19 16:00:00','Sample transaction for forecast testing'),(110,2,NULL,'stock_out',3,'2025-09-20 16:00:00','Sample transaction for forecast testing'),(111,2,NULL,'stock_out',2,'2025-09-21 16:00:00','Sample transaction for forecast testing'),(112,2,NULL,'stock_out',4,'2025-09-22 16:00:00','Sample transaction for forecast testing'),(113,2,NULL,'stock_out',5,'2025-09-23 16:00:00','Sample transaction for forecast testing'),(114,2,NULL,'stock_out',5,'2025-09-25 16:00:00','Sample transaction for forecast testing'),(115,2,NULL,'stock_out',1,'2025-09-26 16:00:00','Sample transaction for forecast testing'),(116,2,NULL,'stock_out',4,'2025-09-27 16:00:00','Sample transaction for forecast testing'),(117,2,NULL,'stock_out',3,'2025-09-28 16:00:00','Sample transaction for forecast testing'),(118,2,NULL,'stock_out',4,'2025-09-29 16:00:00','Sample transaction for forecast testing'),(119,2,NULL,'stock_out',5,'2025-09-30 16:00:00','Sample transaction for forecast testing'),(120,2,NULL,'stock_out',3,'2025-10-01 16:00:00','Sample transaction for forecast testing'),(121,2,NULL,'stock_out',2,'2025-10-03 16:00:00','Sample transaction for forecast testing'),(122,2,NULL,'stock_out',4,'2025-10-04 16:00:00','Sample transaction for forecast testing'),(123,2,NULL,'stock_out',5,'2025-10-05 16:00:00','Sample transaction for forecast testing'),(124,2,NULL,'stock_out',3,'2025-10-06 16:00:00','Sample transaction for forecast testing'),(125,2,NULL,'stock_out',1,'2025-10-07 16:00:00','Sample transaction for forecast testing'),(126,2,NULL,'stock_out',5,'2025-10-09 16:00:00','Sample transaction for forecast testing'),(127,2,NULL,'stock_out',3,'2025-10-10 16:00:00','Sample transaction for forecast testing'),(128,2,NULL,'stock_out',3,'2025-10-11 16:00:00','Sample transaction for forecast testing'),(129,2,NULL,'stock_out',5,'2025-10-12 16:00:00','Sample transaction for forecast testing'),(130,2,NULL,'stock_out',5,'2025-10-13 16:00:00','Sample transaction for forecast testing'),(131,2,NULL,'stock_out',5,'2025-10-14 16:00:00','Sample transaction for forecast testing'),(132,2,NULL,'stock_out',4,'2025-10-15 16:00:00','Sample transaction for forecast testing'),(133,2,NULL,'stock_out',4,'2025-10-16 16:00:00','Sample transaction for forecast testing'),(134,2,NULL,'stock_out',3,'2025-10-17 16:00:00','Sample transaction for forecast testing'),(135,2,NULL,'stock_out',1,'2025-10-18 16:00:00','Sample transaction for forecast testing'),(136,2,NULL,'stock_out',5,'2025-10-21 16:00:00','Sample transaction for forecast testing'),(137,2,NULL,'stock_out',1,'2025-10-22 16:00:00','Sample transaction for forecast testing'),(138,2,NULL,'stock_out',2,'2025-10-23 16:00:00','Sample transaction for forecast testing'),(139,2,NULL,'stock_out',4,'2025-10-24 16:00:00','Sample transaction for forecast testing'),(140,2,NULL,'stock_out',2,'2025-10-26 16:00:00','Sample transaction for forecast testing'),(141,2,NULL,'stock_out',3,'2025-10-27 16:00:00','Sample transaction for forecast testing'),(142,2,NULL,'stock_out',1,'2025-10-28 16:00:00','Sample transaction for forecast testing'),(143,2,NULL,'stock_out',3,'2025-10-30 16:00:00','Sample transaction for forecast testing'),(144,2,NULL,'stock_out',2,'2025-10-31 16:00:00','Sample transaction for forecast testing'),(145,2,NULL,'stock_out',2,'2025-11-02 16:00:00','Sample transaction for forecast testing'),(146,2,NULL,'stock_out',4,'2025-11-03 16:00:00','Sample transaction for forecast testing'),(147,2,NULL,'stock_out',4,'2025-11-04 16:00:00','Sample transaction for forecast testing'),(148,2,NULL,'stock_out',4,'2025-11-05 16:00:00','Sample transaction for forecast testing'),(149,2,NULL,'stock_out',3,'2025-11-06 16:00:00','Sample transaction for forecast testing'),(150,2,NULL,'stock_out',2,'2025-11-07 16:00:00','Sample transaction for forecast testing'),(151,2,NULL,'stock_out',4,'2025-11-08 16:00:00','Sample transaction for forecast testing'),(152,3,NULL,'stock_out',4,'2025-08-10 16:00:00','Sample transaction for forecast testing'),(153,3,NULL,'stock_out',5,'2025-08-11 16:00:00','Sample transaction for forecast testing'),(154,3,NULL,'stock_out',1,'2025-08-12 16:00:00','Sample transaction for forecast testing'),(155,3,NULL,'stock_out',5,'2025-08-13 16:00:00','Sample transaction for forecast testing'),(156,3,NULL,'stock_out',4,'2025-08-15 16:00:00','Sample transaction for forecast testing'),(157,3,NULL,'stock_out',1,'2025-08-16 16:00:00','Sample transaction for forecast testing'),(158,3,NULL,'stock_out',2,'2025-08-18 16:00:00','Sample transaction for forecast testing'),(159,3,NULL,'stock_out',4,'2025-08-19 16:00:00','Sample transaction for forecast testing'),(160,3,NULL,'stock_out',3,'2025-08-20 16:00:00','Sample transaction for forecast testing'),(161,3,NULL,'stock_out',5,'2025-08-21 16:00:00','Sample transaction for forecast testing'),(162,3,NULL,'stock_out',2,'2025-08-22 16:00:00','Sample transaction for forecast testing'),(163,3,NULL,'stock_out',2,'2025-08-23 16:00:00','Sample transaction for forecast testing'),(164,3,NULL,'stock_out',3,'2025-08-24 16:00:00','Sample transaction for forecast testing'),(165,3,NULL,'stock_out',3,'2025-08-25 16:00:00','Sample transaction for forecast testing'),(166,3,NULL,'stock_out',5,'2025-08-27 16:00:00','Sample transaction for forecast testing'),(167,3,NULL,'stock_out',1,'2025-08-28 16:00:00','Sample transaction for forecast testing'),(168,3,NULL,'stock_out',3,'2025-09-01 16:00:00','Sample transaction for forecast testing'),(169,3,NULL,'stock_out',5,'2025-09-06 16:00:00','Sample transaction for forecast testing'),(170,3,NULL,'stock_out',3,'2025-09-07 16:00:00','Sample transaction for forecast testing'),(171,3,NULL,'stock_out',1,'2025-09-09 16:00:00','Sample transaction for forecast testing'),(172,3,NULL,'stock_out',2,'2025-09-10 16:00:00','Sample transaction for forecast testing'),(173,3,NULL,'stock_out',4,'2025-09-12 16:00:00','Sample transaction for forecast testing'),(174,3,NULL,'stock_out',2,'2025-09-13 16:00:00','Sample transaction for forecast testing'),(175,3,NULL,'stock_out',4,'2025-09-14 16:00:00','Sample transaction for forecast testing'),(176,3,NULL,'stock_out',5,'2025-09-16 16:00:00','Sample transaction for forecast testing'),(177,3,NULL,'stock_out',2,'2025-09-17 16:00:00','Sample transaction for forecast testing'),(178,3,NULL,'stock_out',4,'2025-09-18 16:00:00','Sample transaction for forecast testing'),(179,3,NULL,'stock_out',3,'2025-09-19 16:00:00','Sample transaction for forecast testing'),(180,3,NULL,'stock_out',1,'2025-09-20 16:00:00','Sample transaction for forecast testing'),(181,3,NULL,'stock_out',4,'2025-09-22 16:00:00','Sample transaction for forecast testing'),(182,3,NULL,'stock_out',5,'2025-09-23 16:00:00','Sample transaction for forecast testing'),(183,3,NULL,'stock_out',5,'2025-09-24 16:00:00','Sample transaction for forecast testing'),(184,3,NULL,'stock_out',4,'2025-09-26 16:00:00','Sample transaction for forecast testing'),(185,3,NULL,'stock_out',4,'2025-09-27 16:00:00','Sample transaction for forecast testing'),(186,3,NULL,'stock_out',1,'2025-09-28 16:00:00','Sample transaction for forecast testing'),(187,3,NULL,'stock_out',5,'2025-09-29 16:00:00','Sample transaction for forecast testing'),(188,3,NULL,'stock_out',2,'2025-09-30 16:00:00','Sample transaction for forecast testing'),(189,3,NULL,'stock_out',5,'2025-10-01 16:00:00','Sample transaction for forecast testing'),(190,3,NULL,'stock_out',2,'2025-10-02 16:00:00','Sample transaction for forecast testing'),(191,3,NULL,'stock_out',5,'2025-10-03 16:00:00','Sample transaction for forecast testing'),(192,3,NULL,'stock_out',2,'2025-10-04 16:00:00','Sample transaction for forecast testing'),(193,3,NULL,'stock_out',1,'2025-10-06 16:00:00','Sample transaction for forecast testing'),(194,3,NULL,'stock_out',3,'2025-10-07 16:00:00','Sample transaction for forecast testing'),(195,3,NULL,'stock_out',5,'2025-10-08 16:00:00','Sample transaction for forecast testing'),(196,3,NULL,'stock_out',1,'2025-10-09 16:00:00','Sample transaction for forecast testing'),(197,3,NULL,'stock_out',4,'2025-10-10 16:00:00','Sample transaction for forecast testing'),(198,3,NULL,'stock_out',3,'2025-10-11 16:00:00','Sample transaction for forecast testing'),(199,3,NULL,'stock_out',4,'2025-10-12 16:00:00','Sample transaction for forecast testing'),(200,3,NULL,'stock_out',3,'2025-10-13 16:00:00','Sample transaction for forecast testing'),(201,3,NULL,'stock_out',2,'2025-10-14 16:00:00','Sample transaction for forecast testing'),(202,3,NULL,'stock_out',1,'2025-10-16 16:00:00','Sample transaction for forecast testing'),(203,3,NULL,'stock_out',2,'2025-10-18 16:00:00','Sample transaction for forecast testing'),(204,3,NULL,'stock_out',4,'2025-10-20 16:00:00','Sample transaction for forecast testing'),(205,3,NULL,'stock_out',2,'2025-10-21 16:00:00','Sample transaction for forecast testing'),(206,3,NULL,'stock_out',3,'2025-10-22 16:00:00','Sample transaction for forecast testing'),(207,3,NULL,'stock_out',1,'2025-10-23 16:00:00','Sample transaction for forecast testing'),(208,3,NULL,'stock_out',2,'2025-10-24 16:00:00','Sample transaction for forecast testing'),(209,3,NULL,'stock_out',2,'2025-10-25 16:00:00','Sample transaction for forecast testing'),(210,3,NULL,'stock_out',2,'2025-10-26 16:00:00','Sample transaction for forecast testing'),(211,3,NULL,'stock_out',5,'2025-10-27 16:00:00','Sample transaction for forecast testing'),(212,3,NULL,'stock_out',5,'2025-10-28 16:00:00','Sample transaction for forecast testing'),(213,3,NULL,'stock_out',4,'2025-10-29 16:00:00','Sample transaction for forecast testing'),(214,3,NULL,'stock_out',5,'2025-10-31 16:00:00','Sample transaction for forecast testing'),(215,3,NULL,'stock_out',2,'2025-11-01 16:00:00','Sample transaction for forecast testing'),(216,3,NULL,'stock_out',5,'2025-11-02 16:00:00','Sample transaction for forecast testing'),(217,3,NULL,'stock_out',3,'2025-11-03 16:00:00','Sample transaction for forecast testing'),(218,3,NULL,'stock_out',2,'2025-11-04 16:00:00','Sample transaction for forecast testing'),(219,3,NULL,'stock_out',1,'2025-11-05 16:00:00','Sample transaction for forecast testing'),(220,3,NULL,'stock_out',3,'2025-11-06 16:00:00','Sample transaction for forecast testing'),(221,3,NULL,'stock_out',3,'2025-11-08 16:00:00','Sample transaction for forecast testing'),(222,4,NULL,'stock_out',1,'2025-08-10 16:00:00','Sample transaction for forecast testing'),(223,4,NULL,'stock_out',2,'2025-08-11 16:00:00','Sample transaction for forecast testing'),(224,4,NULL,'stock_out',5,'2025-08-14 16:00:00','Sample transaction for forecast testing'),(225,4,NULL,'stock_out',3,'2025-08-15 16:00:00','Sample transaction for forecast testing'),(226,4,NULL,'stock_out',3,'2025-08-16 16:00:00','Sample transaction for forecast testing'),(227,4,NULL,'stock_out',5,'2025-08-17 16:00:00','Sample transaction for forecast testing'),(228,4,NULL,'stock_out',4,'2025-08-18 16:00:00','Sample transaction for forecast testing'),(229,4,NULL,'stock_out',2,'2025-08-19 16:00:00','Sample transaction for forecast testing'),(230,4,NULL,'stock_out',2,'2025-08-20 16:00:00','Sample transaction for forecast testing'),(231,4,NULL,'stock_out',2,'2025-08-21 16:00:00','Sample transaction for forecast testing'),(232,4,NULL,'stock_out',4,'2025-08-22 16:00:00','Sample transaction for forecast testing'),(233,4,NULL,'stock_out',5,'2025-08-23 16:00:00','Sample transaction for forecast testing'),(234,4,NULL,'stock_out',4,'2025-08-24 16:00:00','Sample transaction for forecast testing'),(235,4,NULL,'stock_out',4,'2025-08-25 16:00:00','Sample transaction for forecast testing'),(236,4,NULL,'stock_out',3,'2025-08-26 16:00:00','Sample transaction for forecast testing'),(237,4,NULL,'stock_out',3,'2025-08-27 16:00:00','Sample transaction for forecast testing'),(238,4,NULL,'stock_out',5,'2025-08-28 16:00:00','Sample transaction for forecast testing'),(239,4,NULL,'stock_out',3,'2025-08-29 16:00:00','Sample transaction for forecast testing'),(240,4,NULL,'stock_out',2,'2025-08-30 16:00:00','Sample transaction for forecast testing'),(241,4,NULL,'stock_out',1,'2025-09-01 16:00:00','Sample transaction for forecast testing'),(242,4,NULL,'stock_out',4,'2025-09-03 16:00:00','Sample transaction for forecast testing'),(243,4,NULL,'stock_out',5,'2025-09-04 16:00:00','Sample transaction for forecast testing'),(244,4,NULL,'stock_out',5,'2025-09-05 16:00:00','Sample transaction for forecast testing'),(245,4,NULL,'stock_out',4,'2025-09-07 16:00:00','Sample transaction for forecast testing'),(246,4,NULL,'stock_out',4,'2025-09-08 16:00:00','Sample transaction for forecast testing'),(247,4,NULL,'stock_out',1,'2025-09-09 16:00:00','Sample transaction for forecast testing'),(248,4,NULL,'stock_out',4,'2025-09-10 16:00:00','Sample transaction for forecast testing'),(249,4,NULL,'stock_out',4,'2025-09-11 16:00:00','Sample transaction for forecast testing'),(250,4,NULL,'stock_out',4,'2025-09-12 16:00:00','Sample transaction for forecast testing'),(251,4,NULL,'stock_out',4,'2025-09-13 16:00:00','Sample transaction for forecast testing'),(252,4,NULL,'stock_out',1,'2025-09-14 16:00:00','Sample transaction for forecast testing'),(253,4,NULL,'stock_out',1,'2025-09-15 16:00:00','Sample transaction for forecast testing'),(254,4,NULL,'stock_out',4,'2025-09-16 16:00:00','Sample transaction for forecast testing'),(255,4,NULL,'stock_out',5,'2025-09-17 16:00:00','Sample transaction for forecast testing'),(256,4,NULL,'stock_out',3,'2025-09-19 16:00:00','Sample transaction for forecast testing'),(257,4,NULL,'stock_out',2,'2025-09-20 16:00:00','Sample transaction for forecast testing'),(258,4,NULL,'stock_out',5,'2025-09-21 16:00:00','Sample transaction for forecast testing'),(259,4,NULL,'stock_out',3,'2025-09-22 16:00:00','Sample transaction for forecast testing'),(260,4,NULL,'stock_out',3,'2025-09-23 16:00:00','Sample transaction for forecast testing'),(261,4,NULL,'stock_out',4,'2025-09-25 16:00:00','Sample transaction for forecast testing'),(262,4,NULL,'stock_out',4,'2025-09-27 16:00:00','Sample transaction for forecast testing'),(263,4,NULL,'stock_out',4,'2025-09-28 16:00:00','Sample transaction for forecast testing'),(264,4,NULL,'stock_out',5,'2025-09-29 16:00:00','Sample transaction for forecast testing'),(265,4,NULL,'stock_out',3,'2025-09-30 16:00:00','Sample transaction for forecast testing'),(266,4,NULL,'stock_out',1,'2025-10-01 16:00:00','Sample transaction for forecast testing'),(267,4,NULL,'stock_out',1,'2025-10-02 16:00:00','Sample transaction for forecast testing'),(268,4,NULL,'stock_out',3,'2025-10-03 16:00:00','Sample transaction for forecast testing'),(269,4,NULL,'stock_out',4,'2025-10-04 16:00:00','Sample transaction for forecast testing'),(270,4,NULL,'stock_out',4,'2025-10-06 16:00:00','Sample transaction for forecast testing'),(271,4,NULL,'stock_out',1,'2025-10-07 16:00:00','Sample transaction for forecast testing'),(272,4,NULL,'stock_out',3,'2025-10-08 16:00:00','Sample transaction for forecast testing'),(273,4,NULL,'stock_out',1,'2025-10-09 16:00:00','Sample transaction for forecast testing'),(274,4,NULL,'stock_out',4,'2025-10-11 16:00:00','Sample transaction for forecast testing'),(275,4,NULL,'stock_out',1,'2025-10-12 16:00:00','Sample transaction for forecast testing'),(276,4,NULL,'stock_out',4,'2025-10-14 16:00:00','Sample transaction for forecast testing'),(277,4,NULL,'stock_out',2,'2025-10-15 16:00:00','Sample transaction for forecast testing'),(278,4,NULL,'stock_out',3,'2025-10-16 16:00:00','Sample transaction for forecast testing'),(279,4,NULL,'stock_out',5,'2025-10-17 16:00:00','Sample transaction for forecast testing'),(280,4,NULL,'stock_out',2,'2025-10-18 16:00:00','Sample transaction for forecast testing'),(281,4,NULL,'stock_out',1,'2025-10-20 16:00:00','Sample transaction for forecast testing'),(282,4,NULL,'stock_out',4,'2025-10-21 16:00:00','Sample transaction for forecast testing'),(283,4,NULL,'stock_out',5,'2025-10-22 16:00:00','Sample transaction for forecast testing'),(284,4,NULL,'stock_out',1,'2025-10-23 16:00:00','Sample transaction for forecast testing'),(285,4,NULL,'stock_out',3,'2025-10-24 16:00:00','Sample transaction for forecast testing'),(286,4,NULL,'stock_out',2,'2025-10-25 16:00:00','Sample transaction for forecast testing'),(287,4,NULL,'stock_out',4,'2025-10-26 16:00:00','Sample transaction for forecast testing'),(288,4,NULL,'stock_out',5,'2025-10-27 16:00:00','Sample transaction for forecast testing'),(289,4,NULL,'stock_out',5,'2025-10-28 16:00:00','Sample transaction for forecast testing'),(290,4,NULL,'stock_out',2,'2025-10-29 16:00:00','Sample transaction for forecast testing'),(291,4,NULL,'stock_out',4,'2025-10-31 16:00:00','Sample transaction for forecast testing'),(292,4,NULL,'stock_out',3,'2025-11-02 16:00:00','Sample transaction for forecast testing'),(293,4,NULL,'stock_out',4,'2025-11-03 16:00:00','Sample transaction for forecast testing'),(294,4,NULL,'stock_out',1,'2025-11-04 16:00:00','Sample transaction for forecast testing'),(295,4,NULL,'stock_out',5,'2025-11-05 16:00:00','Sample transaction for forecast testing'),(296,4,NULL,'stock_out',3,'2025-11-06 16:00:00','Sample transaction for forecast testing'),(297,4,NULL,'stock_out',4,'2025-11-07 16:00:00','Sample transaction for forecast testing'),(298,4,NULL,'stock_out',5,'2025-11-08 16:00:00','Sample transaction for forecast testing'),(299,5,NULL,'stock_out',1,'2025-08-10 16:00:00','Sample transaction for forecast testing'),(300,5,NULL,'stock_out',4,'2025-08-11 16:00:00','Sample transaction for forecast testing'),(301,5,NULL,'stock_out',1,'2025-08-12 16:00:00','Sample transaction for forecast testing'),(302,5,NULL,'stock_out',2,'2025-08-13 16:00:00','Sample transaction for forecast testing'),(303,5,NULL,'stock_out',2,'2025-08-14 16:00:00','Sample transaction for forecast testing'),(304,5,NULL,'stock_out',5,'2025-08-15 16:00:00','Sample transaction for forecast testing'),(305,5,NULL,'stock_out',2,'2025-08-16 16:00:00','Sample transaction for forecast testing'),(306,5,NULL,'stock_out',5,'2025-08-17 16:00:00','Sample transaction for forecast testing'),(307,5,NULL,'stock_out',2,'2025-08-18 16:00:00','Sample transaction for forecast testing'),(308,5,NULL,'stock_out',4,'2025-08-19 16:00:00','Sample transaction for forecast testing'),(309,5,NULL,'stock_out',2,'2025-08-20 16:00:00','Sample transaction for forecast testing'),(310,5,NULL,'stock_out',2,'2025-08-21 16:00:00','Sample transaction for forecast testing'),(311,5,NULL,'stock_out',1,'2025-08-22 16:00:00','Sample transaction for forecast testing'),(312,5,NULL,'stock_out',3,'2025-08-23 16:00:00','Sample transaction for forecast testing'),(313,5,NULL,'stock_out',1,'2025-08-24 16:00:00','Sample transaction for forecast testing'),(314,5,NULL,'stock_out',3,'2025-08-25 16:00:00','Sample transaction for forecast testing'),(315,5,NULL,'stock_out',2,'2025-08-27 16:00:00','Sample transaction for forecast testing'),(316,5,NULL,'stock_out',4,'2025-08-28 16:00:00','Sample transaction for forecast testing'),(317,5,NULL,'stock_out',5,'2025-08-29 16:00:00','Sample transaction for forecast testing'),(318,5,NULL,'stock_out',3,'2025-08-30 16:00:00','Sample transaction for forecast testing'),(319,5,NULL,'stock_out',2,'2025-08-31 16:00:00','Sample transaction for forecast testing'),(320,5,NULL,'stock_out',5,'2025-09-01 16:00:00','Sample transaction for forecast testing'),(321,5,NULL,'stock_out',1,'2025-09-02 16:00:00','Sample transaction for forecast testing'),(322,5,NULL,'stock_out',4,'2025-09-04 16:00:00','Sample transaction for forecast testing'),(323,5,NULL,'stock_out',3,'2025-09-05 16:00:00','Sample transaction for forecast testing'),(324,5,NULL,'stock_out',1,'2025-09-08 16:00:00','Sample transaction for forecast testing'),(325,5,NULL,'stock_out',1,'2025-09-09 16:00:00','Sample transaction for forecast testing'),(326,5,NULL,'stock_out',1,'2025-09-11 16:00:00','Sample transaction for forecast testing'),(327,5,NULL,'stock_out',2,'2025-09-12 16:00:00','Sample transaction for forecast testing'),(328,5,NULL,'stock_out',4,'2025-09-14 16:00:00','Sample transaction for forecast testing'),(329,5,NULL,'stock_out',5,'2025-09-15 16:00:00','Sample transaction for forecast testing'),(330,5,NULL,'stock_out',4,'2025-09-16 16:00:00','Sample transaction for forecast testing'),(331,5,NULL,'stock_out',3,'2025-09-17 16:00:00','Sample transaction for forecast testing'),(332,5,NULL,'stock_out',2,'2025-09-18 16:00:00','Sample transaction for forecast testing'),(333,5,NULL,'stock_out',1,'2025-09-19 16:00:00','Sample transaction for forecast testing'),(334,5,NULL,'stock_out',4,'2025-09-20 16:00:00','Sample transaction for forecast testing'),(335,5,NULL,'stock_out',2,'2025-09-21 16:00:00','Sample transaction for forecast testing'),(336,5,NULL,'stock_out',3,'2025-09-22 16:00:00','Sample transaction for forecast testing'),(337,5,NULL,'stock_out',1,'2025-09-23 16:00:00','Sample transaction for forecast testing'),(338,5,NULL,'stock_out',4,'2025-09-24 16:00:00','Sample transaction for forecast testing'),(339,5,NULL,'stock_out',5,'2025-09-25 16:00:00','Sample transaction for forecast testing'),(340,5,NULL,'stock_out',1,'2025-09-26 16:00:00','Sample transaction for forecast testing'),(341,5,NULL,'stock_out',1,'2025-09-27 16:00:00','Sample transaction for forecast testing'),(342,5,NULL,'stock_out',4,'2025-09-28 16:00:00','Sample transaction for forecast testing'),(343,5,NULL,'stock_out',5,'2025-10-02 16:00:00','Sample transaction for forecast testing'),(344,5,NULL,'stock_out',2,'2025-10-04 16:00:00','Sample transaction for forecast testing'),(345,5,NULL,'stock_out',3,'2025-10-05 16:00:00','Sample transaction for forecast testing'),(346,5,NULL,'stock_out',2,'2025-10-06 16:00:00','Sample transaction for forecast testing'),(347,5,NULL,'stock_out',2,'2025-10-07 16:00:00','Sample transaction for forecast testing'),(348,5,NULL,'stock_out',4,'2025-10-08 16:00:00','Sample transaction for forecast testing'),(349,5,NULL,'stock_out',1,'2025-10-09 16:00:00','Sample transaction for forecast testing'),(350,5,NULL,'stock_out',3,'2025-10-10 16:00:00','Sample transaction for forecast testing'),(351,5,NULL,'stock_out',1,'2025-10-11 16:00:00','Sample transaction for forecast testing'),(352,5,NULL,'stock_out',4,'2025-10-12 16:00:00','Sample transaction for forecast testing'),(353,5,NULL,'stock_out',2,'2025-10-13 16:00:00','Sample transaction for forecast testing'),(354,5,NULL,'stock_out',5,'2025-10-14 16:00:00','Sample transaction for forecast testing'),(355,5,NULL,'stock_out',1,'2025-10-15 16:00:00','Sample transaction for forecast testing'),(356,5,NULL,'stock_out',4,'2025-10-16 16:00:00','Sample transaction for forecast testing'),(357,5,NULL,'stock_out',5,'2025-10-17 16:00:00','Sample transaction for forecast testing'),(358,5,NULL,'stock_out',4,'2025-10-18 16:00:00','Sample transaction for forecast testing'),(359,5,NULL,'stock_out',3,'2025-10-19 16:00:00','Sample transaction for forecast testing'),(360,5,NULL,'stock_out',1,'2025-10-20 16:00:00','Sample transaction for forecast testing'),(361,5,NULL,'stock_out',5,'2025-10-21 16:00:00','Sample transaction for forecast testing'),(362,5,NULL,'stock_out',5,'2025-10-22 16:00:00','Sample transaction for forecast testing'),(363,5,NULL,'stock_out',2,'2025-10-23 16:00:00','Sample transaction for forecast testing'),(364,5,NULL,'stock_out',4,'2025-10-24 16:00:00','Sample transaction for forecast testing'),(365,5,NULL,'stock_out',4,'2025-10-26 16:00:00','Sample transaction for forecast testing'),(366,5,NULL,'stock_out',5,'2025-10-30 16:00:00','Sample transaction for forecast testing'),(367,5,NULL,'stock_out',4,'2025-10-31 16:00:00','Sample transaction for forecast testing'),(368,5,NULL,'stock_out',5,'2025-11-02 16:00:00','Sample transaction for forecast testing'),(369,5,NULL,'stock_out',5,'2025-11-04 16:00:00','Sample transaction for forecast testing'),(370,5,NULL,'stock_out',5,'2025-11-05 16:00:00','Sample transaction for forecast testing'),(371,5,NULL,'stock_out',2,'2025-11-06 16:00:00','Sample transaction for forecast testing'),(372,5,NULL,'stock_out',2,'2025-11-07 16:00:00','Sample transaction for forecast testing'),(373,5,NULL,'stock_out',4,'2025-11-08 16:00:00','Sample transaction for forecast testing'),(374,1,9,'stock_out',1234,'2025-11-11 12:27:25','Stock issued');
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'admin.msantos','$2y$10$yWBGL3obFcV4K7YVEgw61O27YIqMSavAsaQU9RLm4qDH7zSparNCW','admin','Milan Santos',NULL,'ADM-003','milanfrancosantos@gmail.com','2025-10-29 09:30:21'),(3,'admin.mone','$2y$10$L/OnydAIhfAybddf5y5Vme0WvtU.nj8E00QgdJ6Y10eAQGLU6reni','admin','mememe One',NULL,'ADM-003','milan@mail.com','2025-10-29 19:01:44'),(4,'manager.aone','$2y$10$YpjNR3j/z6keWW.PaKD2uu9tVdio8XrpWmubUa6Tqy2HEaRmOS...','manager','Ass One',NULL,'MNGR001','alexen@gmail.com','2025-10-29 19:05:18'),(5,'staff.aone','$2y$10$gdzji1V3DmbHWDFkdHhXWORo.Rv1AYtG0h71A9jhYYqjC/eJcTJm.','staff','Ashh One',NULL,'STF-01','prof1@mail.com','2025-10-29 19:06:58'),(6,'admin.rben','$2y$10$W2SXCmy83AnY4ISSh4fftOp2Jif4iNH9lcb7PFw34s3wm2.HGIVJe','admin','Rich Ben',NULL,'ADM-019','rich@gmail.com','2025-11-07 10:43:32'),(7,'mlnfranco','$2y$10$R/p5GBKPdvL9tvn219ng2O66DUwD9F7FnTUN5QV9xuGJa0UQxbNty','admin','milan franco',NULL,'0111','prof1@mail.com','2025-11-09 11:05:06'),(8,'jdow','$2y$10$ScU5i4WR.g3K9qIXd36PJ.YQhiZcMyKPhY3k82jj50vb8baSV7nOm','admin','j dow',NULL,'ADM-019','jdow@mail.com','2025-11-09 11:20:01'),(9,'admin1','$2y$10$J9MtMMOaGYHCNdtu6zfj.OeqfD1jwVOIo/hc4UauYy9HX2eknJbS6','admin','Admin One',NULL,'ADM-001','adminone@gmail.com','2025-11-11 09:16:50');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-12 18:43:30
