/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.18-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: if0_40736960_club
-- ------------------------------------------------------
-- Server version	10.11.18-MariaDB-0+deb12u1

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
-- Table structure for table `admin_balance_logs`
--

DROP TABLE IF EXISTS `admin_balance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_balance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `action` varchar(64) NOT NULL DEFAULT 'add',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_balance_logs`
--

LOCK TABLES `admin_balance_logs` WRITE;
/*!40000 ALTER TABLE `admin_balance_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_balance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `seen` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_receiver` (`receiver_id`),
  KEY `idx_seen` (`seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coin_history`
--

DROP TABLE IF EXISTS `coin_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coin_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `type` varchar(64) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `change` decimal(18,4) DEFAULT NULL,
  `source_name` varchar(191) DEFAULT NULL,
  `source_number` varchar(64) DEFAULT NULL,
  `source_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coin_history`
--

LOCK TABLES `coin_history` WRITE;
/*!40000 ALTER TABLE `coin_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `coin_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `type` varchar(32) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `used_by` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES
(1,'UNM-REG-VIP888',100.00,'apply','active',NULL,NULL,'2026-09-08 06:18:15'),
(2,'UNM-DEP-GOLD99',500.00,'deposit','active',NULL,NULL,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donations`
--

DROP TABLE IF EXISTS `donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `method` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donations`
--

LOCK TABLES `donations` WRITE;
/*!40000 ALTER TABLE `donations` DISABLE KEYS */;
/*!40000 ALTER TABLE `donations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `earn_buttons`
--

DROP TABLE IF EXISTS `earn_buttons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `earn_buttons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `link` varchar(500) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `earn_buttons`
--

LOCK TABLES `earn_buttons` WRITE;
/*!40000 ALTER TABLE `earn_buttons` DISABLE KEYS */;
INSERT INTO `earn_buttons` VALUES
(1,'Watch Sponsor Video','https://youtube.com','active','2026-09-08 06:18:15'),
(2,'Join Telegram Community','https://t.me','active','2026-09-08 06:18:15'),
(3,'Visit Partner Channel','https://google.com','active','2026-09-08 06:18:15');
/*!40000 ALTER TABLE `earn_buttons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_participants`
--

DROP TABLE IF EXISTS `event_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coins` decimal(12,2) NOT NULL DEFAULT 0.00,
  `joined_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_participants`
--

LOCK TABLES `event_participants` WRITE;
/*!40000 ALTER TABLE `event_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `coin_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `game_control`
--

DROP TABLE IF EXISTS `game_control`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `game_control` (
  `user_id` int(11) NOT NULL,
  `last_bet` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `plays` int(11) NOT NULL DEFAULT 0,
  `last_play` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `game_control`
--

LOCK TABLES `game_control` WRITE;
/*!40000 ALTER TABLE `game_control` DISABLE KEYS */;
/*!40000 ALTER TABLE `game_control` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `headtail_bets`
--

DROP TABLE IF EXISTS `headtail_bets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `headtail_bets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `choice` varchar(10) NOT NULL,
  `result` varchar(10) NOT NULL,
  `bet_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `profit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `headtail_bets`
--

LOCK TABLES `headtail_bets` WRITE;
/*!40000 ALTER TABLE `headtail_bets` DISABLE KEYS */;
/*!40000 ALTER TABLE `headtail_bets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_history`
--

DROP TABLE IF EXISTS `login_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_history`
--

LOCK TABLES `login_history` WRITE;
/*!40000 ALTER TABLE `login_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `market_price`
--

DROP TABLE IF EXISTS `market_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `market_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(32) NOT NULL DEFAULT 'UC',
  `price` decimal(18,4) NOT NULL DEFAULT 100.0000,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `market_price`
--

LOCK TABLES `market_price` WRITE;
/*!40000 ALTER TABLE `market_price` DISABLE KEYS */;
INSERT INTO `market_price` VALUES
(1,'UC',100.0000,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `market_price` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `market_state`
--

DROP TABLE IF EXISTS `market_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `market_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(32) NOT NULL DEFAULT 'UC',
  `price` decimal(18,4) NOT NULL DEFAULT 100.0000,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `market_state`
--

LOCK TABLES `market_state` WRITE;
/*!40000 ALTER TABLE `market_state` DISABLE KEYS */;
INSERT INTO `market_state` VALUES
(1,'UC',100.0000,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `market_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `market_ticks`
--

DROP TABLE IF EXISTS `market_ticks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `market_ticks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(32) NOT NULL DEFAULT 'UC',
  `price` decimal(18,4) NOT NULL,
  `mode` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `market_ticks`
--

LOCK TABLES `market_ticks` WRITE;
/*!40000 ALTER TABLE `market_ticks` DISABLE KEYS */;
/*!40000 ALTER TABLE `market_ticks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notices`
--

LOCK TABLES `notices` WRITE;
/*!40000 ALTER TABLE `notices` DISABLE KEYS */;
INSERT INTO `notices` VALUES
(1,'Welcome to Private Club! All systems are operational.','2026-09-08 06:18:15');
/*!40000 ALTER TABLE `notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(64) NOT NULL DEFAULT 'info',
  `is_read` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `method` varchar(64) DEFAULT NULL,
  `plan` varchar(64) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `proof` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `side` varchar(10) NOT NULL,
  `symbol` varchar(32) NOT NULL DEFAULT 'UC',
  `margin` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `leverage` decimal(10,2) NOT NULL DEFAULT 1.00,
  `size` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `entry_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `exit_price` decimal(18,4) DEFAULT NULL,
  `close_price` decimal(18,4) DEFAULT NULL,
  `sl` decimal(18,4) DEFAULT NULL,
  `stop_loss` decimal(18,4) DEFAULT NULL,
  `liq_price` decimal(18,4) DEFAULT NULL,
  `pnl` decimal(18,4) DEFAULT NULL,
  `close_reason` varchar(64) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `title` varchar(191) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_time` varchar(64) NOT NULL DEFAULT 'Instant',
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES
(1,'⚡ Power Click','⚡ Power Click','power-click','power',30.00,0.00,'Instant',1,'2026-09-08 06:18:15'),
(2,'🚀 Turbo Power Click','🚀 Turbo Power Click','turbo-click','turbo',10.00,0.00,'Instant',1,'2026-09-08 06:18:15'),
(3,'🔥 Raw Click','🔥 Raw Click','raw-click','raw',10.00,0.00,'Instant',1,'2026-09-08 06:18:15'),
(4,'🤝 Joint Click','🤝 Joint Click','joint-click','joint',5.00,0.00,'Instant',1,'2026-09-08 06:18:15'),
(5,'📄 Paper Click','📄 Paper Click','paper-click','paper',0.50,0.00,'Instant',1,'2026-09-08 06:18:15'),
(6,'Manta Large Click','Manta Large Click','manta-large-click','manta_large',70.00,0.00,'Instant',1,'2026-09-08 06:18:15'),
(7,'Manta Click','Manta Click','manta-click','manta',35.00,0.00,'Instant',1,'2026-09-08 06:18:15'),
(8,'💎 Premium VIP Plan','💎 Premium VIP Plan','premium-vip','premium',300.00,0.00,'Instant',1,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `k` varchar(64) NOT NULL,
  `v` text DEFAULT NULL,
  `id` int(11) NOT NULL DEFAULT 1,
  `maintenance` tinyint(4) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`k`),
  KEY `idx_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
('headtail_percent','80',1,0,'2026-09-08 06:18:15'),
('maintenance','0',1,0,'2026-09-08 06:18:15'),
('premium_price','300',1,0,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_ledger`
--

DROP TABLE IF EXISTS `system_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(64) NOT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `source` varchar(64) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_ledger`
--

LOCK TABLES `system_ledger` WRITE;
/*!40000 ALTER TABLE `system_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `value` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES
(1,'liquidity_floor','5000','2026-09-08 06:18:15','2026-09-08 06:18:15'),
(2,'premium_price','300','2026-09-08 06:18:15','2026-09-08 06:18:15'),
(3,'headtail_percent','80','2026-09-08 06:18:15','2026-09-08 06:18:15');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_history`
--

DROP TABLE IF EXISTS `trade_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trade_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `symbol` varchar(32) NOT NULL DEFAULT 'UC',
  `side` varchar(10) NOT NULL,
  `pnl` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reason` varchar(64) DEFAULT NULL,
  `closed_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_history`
--

LOCK TABLES `trade_history` WRITE;
/*!40000 ALTER TABLE `trade_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `trade_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_market`
--

DROP TABLE IF EXISTS `trade_market`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trade_market` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `price` decimal(18,4) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_market`
--

LOCK TABLES `trade_market` WRITE;
/*!40000 ALTER TABLE `trade_market` DISABLE KEYS */;
INSERT INTO `trade_market` VALUES
(1,100.0000,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `trade_market` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_positions`
--

DROP TABLE IF EXISTS `trade_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trade_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `margin` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `leverage` decimal(10,2) NOT NULL DEFAULT 1.00,
  `direction` varchar(10) NOT NULL,
  `entry_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `exit_price` decimal(18,4) DEFAULT NULL,
  `position_size` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `fee` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `liquidation_price` decimal(18,4) DEFAULT NULL,
  `pnl` decimal(18,4) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_positions`
--

LOCK TABLES `trade_positions` WRITE;
/*!40000 ALTER TABLE `trade_positions` DISABLE KEYS */;
/*!40000 ALTER TABLE `trade_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_prices`
--

DROP TABLE IF EXISTS `trade_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trade_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(32) NOT NULL DEFAULT 'UC',
  `price` decimal(18,4) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_symbol` (`symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_prices`
--

LOCK TABLES `trade_prices` WRITE;
/*!40000 ALTER TABLE `trade_prices` DISABLE KEYS */;
INSERT INTO `trade_prices` VALUES
(1,'UC',100.0000,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `trade_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trades`
--

DROP TABLE IF EXISTS `trades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `side` varchar(10) NOT NULL,
  `leverage` decimal(10,2) NOT NULL DEFAULT 1.00,
  `margin` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `stop_loss` decimal(18,4) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trades`
--

LOCK TABLES `trades` WRITE;
/*!40000 ALTER TABLE `trades` DISABLE KEYS */;
/*!40000 ALTER TABLE `trades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uc_liquidity`
--

DROP TABLE IF EXISTS `uc_liquidity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uc_liquidity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_balance` decimal(12,2) NOT NULL DEFAULT 50.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uc_liquidity`
--

LOCK TABLES `uc_liquidity` WRITE;
/*!40000 ALTER TABLE `uc_liquidity` DISABLE KEYS */;
INSERT INTO `uc_liquidity` VALUES
(1,50.00,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `uc_liquidity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uc_market`
--

DROP TABLE IF EXISTS `uc_market`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uc_market` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `price` decimal(18,4) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uc_market`
--

LOCK TABLES `uc_market` WRITE;
/*!40000 ALTER TABLE `uc_market` DISABLE KEYS */;
INSERT INTO `uc_market` VALUES
(1,100.0000,'2026-09-08 06:18:15');
/*!40000 ALTER TABLE `uc_market` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uc_positions`
--

DROP TABLE IF EXISTS `uc_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uc_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `side` varchar(10) NOT NULL,
  `margin` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `leverage` decimal(10,2) NOT NULL DEFAULT 1.00,
  `size` decimal(18,4) DEFAULT NULL,
  `entry_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `close_price` decimal(18,4) DEFAULT NULL,
  `exit_price` decimal(18,4) DEFAULT NULL,
  `liquidation_price` decimal(18,4) DEFAULT NULL,
  `stop_loss` decimal(18,4) DEFAULT NULL,
  `pnl` decimal(18,4) DEFAULT NULL,
  `close_reason` varchar(64) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uc_positions`
--

LOCK TABLES `uc_positions` WRITE;
/*!40000 ALTER TABLE `uc_positions` DISABLE KEYS */;
/*!40000 ALTER TABLE `uc_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'user',
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `apply_status` varchar(32) NOT NULL DEFAULT 'approved',
  `coins` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `locked_coins` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `purchase_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `photo` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `coin_cycle_start` datetime DEFAULT NULL,
  `last_coin_cut` datetime DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_apply_status` (`apply_status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'System Treasury','0000000000','system@privateclub.local','$2y$10$lqQLw25dPWDNZYTG6hg4d.V3zECgAe1mEFiYXuOrn9EtwJL4tvjeO','system','active','approved',1000000.0000,1000000.0000,0.0000,NULL,NULL,'2026-09-08 06:18:15',NULL,NULL,'2026-09-08 06:18:15','2026-09-08 06:18:53'),
(2,'Liquidity Pool','0000000001','liquidity@privateclub.local','$2y$10$lqQLw25dPWDNZYTG6hg4d.V3zECgAe1mEFiYXuOrn9EtwJL4tvjeO','liquidity','active','approved',500000.0000,500000.0000,0.0000,NULL,NULL,'2026-09-08 06:18:15',NULL,NULL,'2026-09-08 06:18:15','2026-09-08 06:18:53'),
(3,'Admin Angkur','01788674353','angkur490@gmail.com','$2y$10$lmWNPqoLqhZqzNXqYrGvcOVJTgXmlV.hY2zrkVUe5lydcTA6d9f7S','admin','active','approved',50000.0000,50000.0000,0.0000,NULL,NULL,'2026-09-08 06:18:15',NULL,NULL,'2026-09-08 06:18:15','2026-09-08 06:18:53'),
(4,'Main Admin','01700000000','admin@privateclub.local','$2y$10$lmWNPqoLqhZqzNXqYrGvcOVJTgXmlV.hY2zrkVUe5lydcTA6d9f7S','admin','active','approved',50000.0000,50000.0000,0.0000,NULL,NULL,'2026-09-08 06:18:15',NULL,NULL,'2026-09-08 06:18:15','2026-09-08 06:18:53'),
(5,'Senior Sub Admin','01711111111','subadmin@privateclub.local','$2y$10$V/sqvMeV5TUawteFha8aMeGucworTmEIarXegvTH96ufcec4uuS6q','sub_admin','active','approved',25000.0000,25000.0000,0.0000,NULL,NULL,'2026-09-08 06:18:15',NULL,NULL,'2026-09-08 06:18:15','2026-09-08 06:18:53'),
(6,'Demo Member','01722222222','user@privateclub.local','$2y$10$0HgJEPYK0xO517rQdAu32OvxIHXeYBECJT3QS47FosiWU035888OG','user','active','approved',1500.0000,1500.0000,0.0000,NULL,NULL,'2026-09-08 06:18:15',NULL,NULL,'2026-09-08 06:18:15','2026-09-08 06:18:53');
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

-- Dump completed on 2026-09-08  6:19:01
