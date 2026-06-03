-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: monkeystraming_2
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
-- Current Database: `monkeystraming_2`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `monkeystraming_2` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `monkeystraming_2`;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `color` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'🎬 Películas',1,'Las mejores películas en streaming','fas fa-film','#12aaff','2025-12-07 11:03:03'),(2,'📺 Series',1,'Series completas y temporadas','fas fa-tv','#0de0c9','2025-12-07 11:03:03'),(3,'🎵 Música',1,'Plataformas de música streaming','fas fa-music','#9d4edd','2025-12-07 11:03:03'),(4,'🎮 Juegos',1,'Juegos y suscripciones gaming','fas fa-gamepad','#ff6d00','2025-12-07 11:03:03'),(5,'🤖 IA',1,'Herramientas de inteligencia artificial','fas fa-robot','#ff0054','2025-12-07 11:03:03'),(6,'💻 Software',1,'Software y aplicaciones','fas fa-laptop-code','#00c853','2025-12-07 11:03:03');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compra_items`
--

DROP TABLE IF EXISTS `compra_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compra_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `tipo_venta` enum('PERFIL','CUENTA_COMPLETA') NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `cuenta_id` int(11) DEFAULT NULL,
  `perfil_id` int(11) DEFAULT NULL,
  `vence_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `compra_id` (`compra_id`) USING BTREE,
  KEY `producto_id` (`producto_id`) USING BTREE,
  KEY `cuenta_id` (`cuenta_id`) USING BTREE,
  KEY `perfil_id` (`perfil_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compra_items`
--

LOCK TABLES `compra_items` WRITE;
/*!40000 ALTER TABLE `compra_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `compra_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cuenta_id` int(11) DEFAULT NULL,
  `perfil_id` int(11) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `comision_admin` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_vendedor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','completada','cancelada') DEFAULT 'pendiente',
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` datetime DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `usuario_id` (`usuario_id`) USING BTREE,
  KEY `producto_id` (`producto_id`) USING BTREE,
  KEY `idx_compras_cliente` (`cliente_id`),
  KEY `idx_compras_vendedor` (`vendedor_id`),
  KEY `idx_compras_producto_vendedor` (`producto_id`,`vendedor_id`),
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
INSERT INTO `compras` VALUES (1,7,1,7,2,NULL,NULL,8.99,0.00,8.99,'completada','2025-12-30 19:28:39','0000-00-00 00:00:00',NULL),(2,7,1,7,3,NULL,NULL,11.99,0.00,11.99,'completada','2025-12-30 19:30:29','0000-00-00 00:00:00',NULL),(3,7,1,7,2,NULL,NULL,8.99,0.00,8.99,'completada','2025-12-30 19:46:44','0000-00-00 00:00:00',NULL),(4,7,1,7,2,NULL,NULL,8.99,0.00,8.99,'completada','2025-12-31 14:33:08','0000-00-00 00:00:00',NULL),(6,7,1,7,2,NULL,NULL,8.99,0.00,8.99,'completada','2026-01-24 21:31:07','0000-00-00 00:00:00',NULL),(7,7,1,7,34,NULL,NULL,20.00,0.00,20.00,'completada','2026-01-24 23:35:42','0000-00-00 00:00:00',NULL),(8,7,1,7,2,NULL,NULL,8.99,0.00,8.99,'completada','2026-01-24 23:39:01',NULL,NULL),(9,7,1,7,2,NULL,NULL,8.99,0.00,8.99,'completada','2026-01-24 23:42:52','2026-02-23 18:42:52',NULL),(10,7,1,7,28,NULL,NULL,8.00,0.00,8.00,'completada','2026-01-25 01:42:27','2026-02-23 20:42:27',NULL),(11,7,1,7,31,NULL,NULL,15.00,0.00,15.00,'completada','2026-01-25 01:42:52','2026-02-23 20:42:52',NULL),(12,7,1,7,35,NULL,NULL,10.00,0.00,10.00,'completada','2026-01-27 01:54:59','2026-02-25 20:54:59',NULL),(13,15,1,15,35,15,40,10.00,0.00,10.00,'completada','2026-05-21 14:46:54','2026-06-20 09:46:54',NULL),(14,15,1,15,2,14,35,8.99,0.00,8.99,'completada','2026-05-21 14:47:00','2026-06-20 09:47:00',NULL),(17,8,1,8,2,14,36,8.99,0.00,8.99,'completada','2026-05-22 15:14:56','2026-06-21 10:14:56',NULL),(18,16,14,16,36,16,44,10.00,0.00,10.00,'completada','2026-05-22 19:17:48','2026-06-21 14:17:48',NULL),(19,16,14,16,36,16,45,10.00,0.00,10.00,'completada','2026-05-22 19:18:00','2026-06-21 14:18:00',NULL),(20,16,14,16,36,16,46,10.00,0.00,10.00,'completada','2026-05-22 19:18:07','2026-06-21 14:18:07',NULL),(21,16,14,16,36,16,47,10.00,0.00,10.00,'completada','2026-05-22 19:18:13','2026-06-21 14:18:13',NULL),(22,16,14,16,36,16,48,10.00,0.00,10.00,'completada','2026-05-22 19:18:16','2026-06-21 14:18:16',NULL),(23,16,14,16,40,19,51,5.00,0.00,5.00,'completada','2026-05-26 05:37:56','2026-06-25 00:37:56',NULL),(24,16,14,16,40,19,52,5.00,0.00,5.00,'completada','2026-05-26 06:14:01','2026-06-25 01:14:01',NULL),(25,16,14,16,40,19,53,5.00,0.00,5.00,'completada','2026-05-26 06:14:05','2026-06-25 01:14:05',NULL),(26,16,14,16,40,19,54,5.00,0.00,5.00,'completada','2026-05-26 06:14:07','2026-06-25 01:14:07',NULL),(27,16,14,16,40,19,55,5.00,0.00,5.00,'completada','2026-05-26 06:14:10','2026-06-25 01:14:10',NULL),(28,16,14,16,41,20,56,10.00,0.00,10.00,'completada','2026-05-29 05:01:56','2026-06-28 00:01:56',NULL),(29,16,14,16,42,21,61,7.00,0.00,7.00,'completada','2026-05-30 16:11:52','2026-06-29 11:11:52',NULL),(30,16,14,16,42,21,62,7.00,0.00,7.00,'completada','2026-05-30 16:14:56','2026-06-29 11:14:56',NULL),(31,16,14,16,42,21,63,7.00,0.00,7.00,'completada','2026-05-31 00:39:27','2026-06-29 19:39:27',NULL),(32,16,14,16,42,21,64,7.00,0.00,7.00,'completada','2026-05-31 00:44:54','2026-06-29 19:44:54',NULL),(33,16,1,16,35,15,41,10.00,0.00,10.00,'completada','2026-05-31 00:45:18','2026-06-29 19:45:18',NULL),(34,16,14,16,42,21,65,7.00,0.00,7.00,'completada','2026-05-31 00:45:27','2026-06-29 19:45:27',NULL),(35,16,14,16,42,21,66,7.00,0.00,7.00,'completada','2026-05-31 00:45:30','2026-06-29 19:45:30',NULL),(36,16,14,16,42,21,67,7.00,0.00,7.00,'completada','2026-05-31 00:45:33','2026-06-29 19:45:33',NULL),(37,16,1,16,35,15,42,10.00,0.00,10.00,'completada','2026-05-31 01:01:19','2026-06-29 20:01:19',NULL);
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuenta_perfiles`
--

DROP TABLE IF EXISTS `cuenta_perfiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuenta_perfiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cuenta_id` int(11) NOT NULL,
  `perfil_nombre` varchar(120) NOT NULL DEFAULT 'Perfil',
  `estado` enum('DISPONIBLE','RESERVADO','VENDIDO','BLOQUEADO') NOT NULL DEFAULT 'DISPONIBLE',
  `vendido_a_usuario_id` int(11) DEFAULT NULL,
  `compra_item_id` int(11) DEFAULT NULL,
  `vence_at` datetime DEFAULT NULL,
  `vendido_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `cuenta_id` (`cuenta_id`) USING BTREE,
  KEY `estado` (`estado`) USING BTREE,
  CONSTRAINT `fk_cp_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuenta_perfiles`
--

LOCK TABLES `cuenta_perfiles` WRITE;
/*!40000 ALTER TABLE `cuenta_perfiles` DISABLE KEYS */;
INSERT INTO `cuenta_perfiles` VALUES (1,1,'Perfil 1','VENDIDO',7,NULL,'2026-01-30 12:18:21','2025-12-31 12:18:21','2025-12-31 11:01:40'),(2,1,'Perfil 2','VENDIDO',7,NULL,'2026-01-30 14:13:25','2025-12-31 14:13:25','2025-12-31 11:01:40'),(3,1,'Perfil 3','VENDIDO',7,NULL,'2026-01-30 14:17:54','2025-12-31 14:17:54','2025-12-31 11:01:40'),(4,1,'Perfil 4','VENDIDO',7,NULL,'2026-01-30 14:24:21','2025-12-31 14:24:21','2025-12-31 11:01:40'),(5,1,'Perfil 5','VENDIDO',7,NULL,'2026-01-30 14:24:30','2025-12-31 14:24:30','2025-12-31 11:01:40'),(6,2,'Perfil 1','VENDIDO',7,NULL,'2026-02-08 22:12:37','2026-01-09 22:12:37','2026-01-09 22:12:14'),(7,2,'Perfil 2','VENDIDO',7,NULL,'2026-02-08 22:12:43','2026-01-09 22:12:43','2026-01-09 22:12:14'),(8,2,'Perfil 3','VENDIDO',7,NULL,'2026-02-08 22:12:48','2026-01-09 22:12:48','2026-01-09 22:12:14'),(9,2,'Perfil 4','VENDIDO',7,NULL,'2026-02-08 22:12:54','2026-01-09 22:12:54','2026-01-09 22:12:14'),(10,3,'Perfil 1','VENDIDO',7,NULL,'2026-02-19 08:30:58','2026-01-20 08:30:58','2026-01-20 08:27:05'),(11,4,'Perfil 1','VENDIDO',7,11,'2026-02-23 20:42:52','2026-01-24 20:42:52','2026-01-20 08:27:41'),(12,5,'Perfil 1','VENDIDO',7,NULL,'2026-02-19 08:30:07','2026-01-20 08:30:07','2026-01-20 08:28:12'),(13,5,'Perfil 2','VENDIDO',7,NULL,'2026-02-19 08:30:33','2026-01-20 08:30:33','2026-01-20 08:28:12'),(14,5,'Perfil 3','VENDIDO',7,NULL,'2026-02-19 08:30:40','2026-01-20 08:30:40','2026-01-20 08:28:12'),(15,5,'Perfil 4','VENDIDO',7,NULL,'2026-02-19 08:30:46','2026-01-20 08:30:46','2026-01-20 08:28:12'),(16,6,'Perfil 1','VENDIDO',7,10,'2026-02-23 20:42:27','2026-01-24 20:42:27','2026-01-20 08:28:40'),(17,7,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:52:32','2026-01-24 15:52:32','2026-01-20 08:29:00'),(18,8,'Perfil 1','VENDIDO',10,NULL,'2026-02-23 14:10:01','2026-01-24 14:10:01','2026-01-24 14:09:47'),(19,8,'Perfil 2','VENDIDO',10,NULL,'2026-02-23 14:21:06','2026-01-24 14:21:06','2026-01-24 14:09:47'),(20,8,'Perfil 3','VENDIDO',10,NULL,'2026-02-23 14:21:16','2026-01-24 14:21:16','2026-01-24 14:09:47'),(21,8,'Perfil 4','VENDIDO',10,NULL,'2026-02-23 15:43:51','2026-01-24 15:43:51','2026-01-24 14:09:47'),(22,9,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:46:33','2026-01-24 15:46:33','2026-01-24 15:46:22'),(23,9,'Perfil 2','VENDIDO',7,NULL,'2026-02-23 15:47:03','2026-01-24 15:47:03','2026-01-24 15:46:22'),(24,9,'Perfil 3','VENDIDO',7,NULL,'2026-02-23 15:47:06','2026-01-24 15:47:06','2026-01-24 15:46:22'),(25,9,'Perfil 4','VENDIDO',7,NULL,'2026-02-23 15:47:08','2026-01-24 15:47:08','2026-01-24 15:46:22'),(26,9,'Perfil 5','VENDIDO',7,NULL,'2026-02-23 15:51:36','2026-01-24 15:51:36','2026-01-24 15:46:22'),(27,10,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:51:42','2026-01-24 15:51:42','2026-01-24 15:51:19'),(28,11,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:56:58','2026-01-24 15:56:58','2026-01-24 15:56:14'),(29,12,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:56:52','2026-01-24 15:56:52','2026-01-24 15:56:41'),(30,12,'Perfil 2','VENDIDO',7,NULL,'2026-02-23 15:56:55','2026-01-24 15:56:55','2026-01-24 15:56:41'),(31,12,'Perfil 3','VENDIDO',7,6,'2026-02-23 16:31:07','2026-01-24 16:31:07','2026-01-24 15:56:41'),(32,12,'Perfil 4','VENDIDO',7,8,'2026-02-23 18:39:01','2026-01-24 18:39:01','2026-01-24 15:56:41'),(33,13,'Perfil 1','VENDIDO',7,7,'2026-02-23 18:35:42','2026-01-24 18:35:42','2026-01-24 18:34:25'),(34,14,'Perfil 1','VENDIDO',7,9,'2026-02-23 18:42:52','2026-01-24 18:42:52','2026-01-24 18:34:51'),(35,14,'Perfil 2','VENDIDO',15,14,'2026-06-20 09:47:00','2026-05-21 09:47:00','2026-01-24 18:34:51'),(36,14,'Perfil 3','VENDIDO',8,17,'2026-06-21 10:14:56','2026-05-22 10:14:56','2026-01-24 18:34:51'),(37,14,'Perfil 4','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-24 18:34:51'),(38,14,'Perfil 5','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-24 18:34:51'),(39,15,'Perfil 1','VENDIDO',7,12,'2026-02-25 20:54:59','2026-01-26 20:54:59','2026-01-26 20:54:32'),(40,15,'Perfil 2','VENDIDO',15,13,'2026-06-20 09:46:54','2026-05-21 09:46:54','2026-01-26 20:54:32'),(41,15,'Perfil 3','VENDIDO',16,33,'2026-06-29 19:45:18','2026-05-30 19:45:18','2026-01-26 20:54:32'),(42,15,'Perfil 4','VENDIDO',16,37,'2026-06-29 20:01:19','2026-05-30 20:01:19','2026-01-26 20:54:32'),(43,15,'Perfil 5','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-26 20:54:32'),(44,16,'Perfil 1','VENDIDO',16,18,'2026-06-21 14:17:48','2026-05-22 14:17:48','2026-05-21 11:02:25'),(45,16,'Perfil 2','VENDIDO',16,19,'2026-06-21 14:18:00','2026-05-22 14:18:00','2026-05-21 11:02:25'),(46,16,'Perfil 3','VENDIDO',16,20,'2026-06-21 14:18:07','2026-05-22 14:18:07','2026-05-21 11:02:25'),(47,16,'Perfil 4','VENDIDO',16,21,'2026-06-21 14:18:13','2026-05-22 14:18:13','2026-05-21 11:02:25'),(48,16,'Perfil 5','VENDIDO',16,22,'2026-06-21 14:18:16','2026-05-22 14:18:16','2026-05-21 11:02:25'),(51,19,'Perfil 1','VENDIDO',16,23,'2026-06-25 00:37:56','2026-05-26 00:37:56','2026-05-26 00:37:03'),(52,19,'Perfil 2','VENDIDO',16,24,'2026-06-25 01:14:01','2026-05-26 01:14:01','2026-05-26 00:37:03'),(53,19,'Perfil 3','VENDIDO',16,25,'2026-06-25 01:14:05','2026-05-26 01:14:05','2026-05-26 00:37:03'),(54,19,'Perfil 4','VENDIDO',16,26,'2026-06-25 01:14:07','2026-05-26 01:14:07','2026-05-26 00:37:03'),(55,19,'Perfil 5','VENDIDO',16,27,'2026-06-25 01:14:10','2026-05-26 01:14:10','2026-05-26 00:37:03'),(56,20,'Perfil 1','VENDIDO',16,28,'2026-06-28 00:01:56','2026-05-29 00:01:56','2026-05-29 00:01:02'),(57,20,'Perfil 2','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-05-29 00:01:02'),(58,20,'Perfil 3','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-05-29 00:01:02'),(59,20,'Perfil 4','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-05-29 00:01:02'),(60,20,'Perfil 5','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-05-29 00:01:02'),(61,21,'Perfil 1','VENDIDO',16,29,'2026-06-29 11:11:52','2026-05-30 11:11:52','2026-05-30 11:08:45'),(62,21,'Perfil 2','VENDIDO',16,30,'2026-06-29 11:14:56','2026-05-30 11:14:56','2026-05-30 11:08:45'),(63,21,'Perfil 3','VENDIDO',16,31,'2026-06-29 19:39:27','2026-05-30 19:39:27','2026-05-30 11:08:45'),(64,21,'Perfil 4','VENDIDO',16,32,'2026-06-29 19:44:54','2026-05-30 19:44:54','2026-05-30 11:08:45'),(65,21,'Perfil 5','VENDIDO',16,34,'2026-06-29 19:45:27','2026-05-30 19:45:27','2026-05-30 11:08:45'),(66,21,'Perfil 6','VENDIDO',16,35,'2026-06-29 19:45:30','2026-05-30 19:45:30','2026-05-30 11:08:45'),(67,21,'Perfil 7','VENDIDO',16,36,'2026-06-29 19:45:33','2026-05-30 19:45:33','2026-05-30 11:08:45');
/*!40000 ALTER TABLE `cuenta_perfiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas`
--

DROP TABLE IF EXISTS `cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuentas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendedor_id` int(11) DEFAULT NULL,
  `producto_id` int(11) NOT NULL,
  `modo_venta` enum('PERFIL','CUENTA_COMPLETA') DEFAULT NULL,
  `login_user` varchar(190) NOT NULL,
  `login_pass` varchar(190) NOT NULL,
  `pin` varchar(50) DEFAULT NULL,
  `max_perfiles` int(11) NOT NULL DEFAULT 1,
  `estado` enum('DISPONIBLE','VENDIDA_COMPLETA','BLOQUEADA') NOT NULL DEFAULT 'DISPONIBLE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `producto_id` (`producto_id`) USING BTREE,
  KEY `estado` (`estado`) USING BTREE,
  KEY `idx_cuentas_vendedor` (`vendedor_id`),
  KEY `idx_cuentas_producto_vendedor` (`producto_id`,`vendedor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas`
--

LOCK TABLES `cuentas` WRITE;
/*!40000 ALTER TABLE `cuentas` DISABLE KEYS */;
INSERT INTO `cuentas` VALUES (1,1,1,'PERFIL','otracosa@gmail.com','123','12',5,'DISPONIBLE','2025-12-31 11:01:40'),(2,1,2,'PERFIL','max@gmail.com','123','1234',4,'DISPONIBLE','2026-01-09 22:12:14'),(3,1,32,'PERFIL','youprueba@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-20 08:27:05'),(4,1,31,'PERFIL','VAXZ-ASDC-ASCW-ASCC','SIN CONTRA',NULL,1,'DISPONIBLE','2026-01-20 08:27:41'),(5,1,30,'PERFIL','vix@gmail.com','123','123',4,'DISPONIBLE','2026-01-20 08:28:12'),(6,1,28,'PERFIL','surf@gmail.com','123123',NULL,1,'DISPONIBLE','2026-01-20 08:28:40'),(7,1,4,'PERFIL','spoty@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-20 08:29:00'),(8,1,2,'PERFIL','habvoassod@gmail.com','123124',NULL,4,'DISPONIBLE','2026-01-24 14:09:47'),(9,1,2,'PERFIL','otrademax@gmail.com','123412',NULL,5,'DISPONIBLE','2026-01-24 15:46:22'),(10,1,2,'PERFIL','masdafsa@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-24 15:51:19'),(11,1,34,'PERFIL','dfasfa@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-24 15:56:14'),(12,1,2,'PERFIL','sdfsfsdghh@gmail.com','123',NULL,4,'DISPONIBLE','2026-01-24 15:56:41'),(13,1,34,'PERFIL','maxpruevba@gmail.com','12341',NULL,1,'DISPONIBLE','2026-01-24 18:34:25'),(14,1,2,'PERFIL','maxperfilmqmq@gmail.com','123',NULL,5,'DISPONIBLE','2026-01-24 18:34:51'),(15,1,35,'PERFIL','comunicate con el proveedor','9612341234',NULL,5,'DISPONIBLE','2026-01-26 20:54:32'),(16,14,36,'PERFIL','otracosa@gmail.com','casuty','123',5,'VENDIDA_COMPLETA','2026-05-21 11:02:25'),(19,14,40,'PERFIL','otracosa@gmail.com','casuty',NULL,5,'VENDIDA_COMPLETA','2026-05-26 00:37:03'),(20,14,41,'PERFIL','pene@gmail.com','123','1234',5,'DISPONIBLE','2026-05-29 00:01:02'),(21,14,42,'PERFIL','otrac12osa@gmail.com','casuty5102',NULL,7,'VENDIDA_COMPLETA','2026-05-30 11:08:45');
/*!40000 ALTER TABLE `cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metodos_pago`
--

DROP TABLE IF EXISTS `metodos_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metodos_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `icono` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#12aaff',
  `comision_porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `comision_fija` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comision` varchar(100) NOT NULL DEFAULT '0%',
  `tiempo` varchar(100) DEFAULT 'Inmediato',
  `imagen` varchar(255) DEFAULT NULL,
  `instrucciones` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `clave` (`clave`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metodos_pago`
--

LOCK TABLES `metodos_pago` WRITE;
/*!40000 ALTER TABLE `metodos_pago` DISABLE KEYS */;
INSERT INTO `metodos_pago` VALUES (1,'yape','Yape','Recarga desde Yape','fas fa-mobile-alt','#7C3AED',0.00,0.00,'0%','Instantáneo','uploads/metodos/metodo_40718154c232fed40feda385.png','Envía el monto al número 9xx xxx xxx y sube la captura del pago.',1,1),(2,'plin','Plin','Recarga por Plin','fas fa-wallet','#10B981',0.00,0.00,'0%','Instantáneo','assets/img/metodos/plin.png','Envía el monto al número 9xx xxx xxx y adjunta el voucher.',1,2),(3,'binance','Binance','Pagos internacionales','fab fa-paypal','#0EA5E9',0.00,0.30,'0','Hasta 24h','assets/img/metodos/paypal.png','Envía el pago a tu-correo@paypal.com y sube el comprobante.',1,3),(4,'chupadas','Chupadita','Pago oral','','#12aaff',0.00,0.00,'0%','Inmediato',NULL,NULL,0,0);
/*!40000 ALTER TABLE `metodos_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendedor_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 1,
  `destacado` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `estado_revision` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado',
  `rechazo_motivo` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contraseña` varchar(20) DEFAULT NULL,
  `tipo_venta` enum('PERFIL','CUENTA_COMPLETA') NOT NULL DEFAULT 'PERFIL',
  `duracion_dias` int(11) NOT NULL DEFAULT 30,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `categoria_id` (`categoria_id`) USING BTREE,
  KEY `idx_productos_vendedor` (`vendedor_id`),
  KEY `idx_productos_estado_revision` (`estado_revision`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,1,'Netflix 4K Premium','4 pantallas simultáneas, contenido 4K',9.99,1,'uploads/productos/prod_0557220aade1a8eb39943966.png',50,1,1,'aprobado',NULL,'2025-12-07 11:03:03',NULL,'PERFIL',30),(2,1,'HBO MAX PERFIL','Series exclusivas y estrenos',8.99,1,'uploads/productos/prod_e911917c3bf653da1e0ca8cd.jpg',2,1,1,'aprobado',NULL,'2025-12-07 11:03:03',NULL,'PERFIL',30),(3,1,'Disney+','Disney, Marvel, Star Wars, Pixar',11.99,1,'uploads/productos/prod_068af2a597ec4bba0ffc0811.jpg',29,1,1,'aprobado',NULL,'2025-12-07 11:03:03',NULL,'PERFIL',30),(4,1,'Spotify Premium','Música sin anuncios, modo offline',9.99,3,'uploads/productos/prod_0f0b85f0d502aba785972bf3.jpg',1,1,1,'aprobado',NULL,'2025-12-07 11:03:03',NULL,'PERFIL',30),(5,1,'Xbox Game Pass','+100 juegos en PC y consola',14.99,4,'assets/img/productos/xbox.png',25,0,1,'aprobado',NULL,'2025-12-07 11:03:03',NULL,'PERFIL',30),(6,1,'SETSP','ADADA',11.99,4,'',0,0,0,'aprobado',NULL,'2025-12-26 16:16:36',NULL,'PERFIL',30),(7,1,'Adobe Creative Cloud','',10.00,6,'uploads/productos/prod_8228f7c19641adad8e95b5ad.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:13:18',NULL,'PERFIL',30),(8,1,'Autodesck','',10.00,6,'uploads/productos/prod_3cb24ecc05f121518a626a29.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:13:51',NULL,'PERFIL',30),(9,1,'Canva','',5.00,6,'uploads/productos/prod_fb68ec2c1f8af47003cc14f1.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:14:09',NULL,'PERFIL',30),(10,1,'Canva 1 año','',15.00,NULL,'uploads/productos/prod_c8e9f0ff12b7106b256d3d58.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:14:39',NULL,'PERFIL',30),(11,1,'Canva 2 años','',25.00,6,'uploads/productos/prod_6cf8269912f733402f7e12d5.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:14:58',NULL,'PERFIL',30),(12,1,'Chat GPT','',12.00,5,'uploads/productos/prod_3a8c9a49cb2bed2c67af03f6.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:15:23',NULL,'PERFIL',30),(13,1,'Crunchyrroll','',4.00,2,'uploads/productos/prod_554f3189cc327e893d9f3360.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:15:45',NULL,'PERFIL',30),(14,1,'Deezer','',10.00,3,'uploads/productos/prod_ea5bc237b3b762711fee012f.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:16:05',NULL,'PERFIL',30),(15,1,'DGO','',10.00,4,'uploads/productos/prod_5bc7cf267d5cba9886b292f3.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:16:25',NULL,'PERFIL',30),(16,1,'DGO + LIGA 1 MAX','',20.00,4,'uploads/productos/prod_630e267104ae1d0b27199098.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:16:51',NULL,'PERFIL',30),(17,1,'ESET','',10.00,6,'uploads/productos/prod_7cdbbad2d9f1a7af78ec814e.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:17:14',NULL,'PERFIL',30),(18,1,'Express VPN','',5.00,6,'uploads/productos/prod_92fb2c344e8f7e9cc8bb0b62.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:17:31',NULL,'PERFIL',30),(19,1,'Gemini PRO','',10.00,5,'uploads/productos/prod_3a653e97f89261ac8749a8cf.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:18:09',NULL,'PERFIL',30),(20,1,'IPTV','',5.00,NULL,'uploads/productos/prod_bb261eeba6c5699d54744987.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:18:24',NULL,'PERFIL',30),(21,1,'Kaspersky','',10.00,6,'uploads/productos/prod_878cfe796a6256dc2eee9dd5.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:18:43',NULL,'PERFIL',30),(22,1,'Macfee','',5.00,NULL,'uploads/productos/prod_f6c4f06a153541f15d1f1b31.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:19:01',NULL,'PERFIL',30),(23,1,'Nord VPN','',6.00,6,'uploads/productos/prod_1538886c8f9d5fe8eaba6708.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:19:25',NULL,'PERFIL',30),(24,1,'Oiffice 365','',12.00,NULL,'uploads/productos/prod_9184fe10e9920a805e23afd3.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:19:49',NULL,'PERFIL',30),(25,1,'Perplexity','',12.00,5,'uploads/productos/prod_066b601b9c7e469088a519d5.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:20:07',NULL,'PERFIL',30),(26,1,'Prime Video','',5.00,2,'uploads/productos/prod_34a01caa261c9966244ac44b.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:20:25',NULL,'PERFIL',30),(27,1,'Pure VPN','',6.00,NULL,'uploads/productos/prod_b96547b981496460b54ee15d.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:20:41',NULL,'PERFIL',30),(28,1,'Surf Shack VPN','',8.00,6,'uploads/productos/prod_7e3cca32a150bb9940b04c5a.jpg',1,0,1,'aprobado',NULL,'2026-01-20 13:21:58',NULL,'PERFIL',30),(29,1,'Tidal Music','',6.00,3,'uploads/productos/prod_4966d2dac691b52ef91c26ba.jpg',0,0,1,'aprobado',NULL,'2026-01-20 13:22:18',NULL,'PERFIL',30),(30,1,'Vix','',5.00,2,'uploads/productos/prod_4fe56cb77f705c8e0e982a60.jpg',4,0,1,'aprobado',NULL,'2026-01-20 13:22:49',NULL,'PERFIL',30),(31,1,'Windows 11 PRO','',15.00,6,'uploads/productos/prod_2c9de059de9b21953490741d.jpg',1,0,1,'aprobado',NULL,'2026-01-20 13:23:10',NULL,'PERFIL',30),(32,1,'YouTube Premiun','',8.00,3,'uploads/productos/prod_a2148ce8c50e133f7c7c0a63.jpg',1,0,1,'aprobado',NULL,'2026-01-20 13:23:28',NULL,'PERFIL',30),(33,1,'porno','adadasda',10.00,1,'uploads/productos/prod_06df3c03aaf6dec3d0fc9e2d.jpg',0,0,0,'aprobado',NULL,'2026-01-24 19:12:48',NULL,'PERFIL',30),(34,1,'HBO MAX Completo','',20.00,1,'uploads/productos/prod_b75ee843e6d17c632680ece0.jpg',1,0,1,'aprobado',NULL,'2026-01-24 20:54:40',NULL,'PERFIL',30),(35,1,'vix a pedido','',10.00,1,'uploads/productos/prod_62b763d57676b446e7f5bd40.png',1,0,1,'aprobado',NULL,'2026-01-27 01:52:58',NULL,'PERFIL',30),(36,14,'Netflix 4K Premium','netflix premoun',10.00,1,'uploads/productos/prod_1e8f9930f26e016deeea8122.png',0,0,0,'aprobado',NULL,'2026-05-21 16:01:15',NULL,'PERFIL',30),(37,14,'Netflix 4K Premium','netflix premoun',10.00,1,'uploads/productos/prod_87aab84e3215873d2abf7060.png',0,0,0,'aprobado',NULL,'2026-05-21 16:01:42',NULL,'PERFIL',30),(40,14,'VIX','VIX PREMIUN',5.00,2,'uploads/productos/prod_5496dd7cce6e05ec583248c9.jpg',0,0,1,'aprobado',NULL,'2026-05-26 05:35:41',NULL,'PERFIL',30),(41,14,'pene','como te gusta',10.00,5,'uploads/productos/prod_e241a307d8abf3c22a2e725f.png',4,0,1,'aprobado',NULL,'2026-05-29 05:00:12',NULL,'PERFIL',30),(42,14,'disney','asdasd',7.00,1,'uploads/productos/prod_e045691d18945215d84492d2.jpg',0,0,1,'aprobado',NULL,'2026-05-30 16:08:03',NULL,'PERFIL',30);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recargas`
--

DROP TABLE IF EXISTS `recargas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recargas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `metodo` varchar(50) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `comision` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comprobante_url` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` timestamp NULL DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `rechazo_motivo` text DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `usuario_id` (`usuario_id`) USING BTREE,
  KEY `aprobado_por` (`aprobado_por`) USING BTREE,
  CONSTRAINT `recargas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `recargas_ibfk_2` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recargas`
--

LOCK TABLES `recargas` WRITE;
/*!40000 ALTER TABLE `recargas` DISABLE KEYS */;
INSERT INTO `recargas` VALUES (1,7,'yape',50.00,0.00,'assets/comprobantes/recarga_1_1767122823.jfif','aprobada','2025-12-30 19:27:03','2025-12-30 19:27:52',8,NULL),(2,7,'yape',200.00,0.00,'assets/comprobantes/recarga_2_1767197015.jfif','aprobada','2025-12-31 16:03:35','2025-12-31 16:05:21',8,NULL),(3,7,'plin',500.00,0.00,'assets/comprobantes/recarga_3_1768014490.jpg','aprobada','2026-01-10 03:08:10','2026-01-10 03:10:04',8,NULL),(4,7,'yape',500.00,0.00,'assets/comprobantes/recarga_4_1768020875.jpg','aprobada','2026-01-10 04:54:35','2026-01-19 21:00:47',8,NULL),(5,7,'yape',500.00,0.00,'assets/comprobantes/recarga_5_1769178187.png','aprobada','2026-01-23 14:23:07','2026-01-23 14:23:17',8,NULL),(6,7,'yape',1000.00,0.00,'assets/comprobantes/recarga_6_1769201351.png','aprobada','2026-01-23 20:49:11','2026-01-23 20:49:27',8,NULL),(7,7,'yape',50.00,0.00,'assets/comprobantes/recarga_7_1769204558.png','rechazada','2026-01-23 21:42:38','2026-01-23 21:43:10',8,'es un sonso se cree vivo'),(8,10,'yape',50.00,0.00,'assets/comprobantes/recarga_8_1769281709.png','aprobada','2026-01-24 19:08:29','2026-01-24 19:08:51',8,NULL),(9,8,'yape',500.00,0.00,'assets/comprobantes/recarga_9_1769479152.png','aprobada','2026-01-27 01:59:12','2026-01-27 01:59:39',8,NULL),(10,15,'yape',999.99,0.00,'assets/comprobantes/recarga_10_1779374638.jfif','aprobada','2026-05-21 14:43:58','2026-05-21 14:45:01',8,NULL),(11,16,'yape',1900.00,0.00,'assets/comprobantes/recarga_11_20460f357f32aa84.png','aprobada','2026-05-22 19:14:30','2026-05-22 19:15:35',8,NULL),(12,16,'yape',500.00,0.00,'assets/comprobantes/recarga_12_7e065c6b8ffd4520.png','aprobada','2026-05-29 04:56:38','2026-05-29 04:58:38',8,NULL),(13,16,'yape',20.00,0.00,'assets/comprobantes/recarga_13_fc6d1730beb237d6.png','aprobada','2026-05-30 16:13:29','2026-05-30 16:14:15',8,NULL);
/*!40000 ALTER TABLE `recargas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recuperaciones_pendientes`
--

DROP TABLE IF EXISTS `recuperaciones_pendientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recuperaciones_pendientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `nombre_usuario` varchar(100) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `enlace` text NOT NULL,
  `estado` enum('pendiente','enviado','expirado') DEFAULT 'pendiente',
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `fecha_envio` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperaciones_pendientes`
--

LOCK TABLES `recuperaciones_pendientes` WRITE;
/*!40000 ALTER TABLE `recuperaciones_pendientes` DISABLE KEYS */;
INSERT INTO `recuperaciones_pendientes` VALUES (3,7,'+51937401236','maminiti','e7d6598eb571bb0bd030e04e3c20050e5f712755832048c045c7a82200647beb','http://localhost/monkydos/restablecer.php?token=e7d6598eb571bb0bd030e04e3c20050e5f712755832048c045c7a82200647beb','enviado','2026-01-24 20:07:51','2026-01-24 20:38:04'),(4,7,'+51937401236','maminiti','ad932281906f003a7d523fd08292194b0827bd7d601ecaecdf5db6466b60e249','http://localhost/monkydos/restablecer.php?token=ad932281906f003a7d523fd08292194b0827bd7d601ecaecdf5db6466b60e249','enviado','2026-01-24 20:26:03','2026-01-24 20:37:34'),(5,7,'+51937401236','maminiti','debaa7e7db01995803f27e210dd9440c743692f0b463cab4091dac004c51e7a1','http://localhost/monkydos/restablecer.php?token=debaa7e7db01995803f27e210dd9440c743692f0b463cab4091dac004c51e7a1','enviado','2026-01-26 09:41:47','2026-01-26 09:47:49');
/*!40000 ALTER TABLE `recuperaciones_pendientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saldo_movimientos`
--

DROP TABLE IF EXISTS `saldo_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saldo_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `tipo` enum('recarga','compra','ajuste_admin','venta','comision','reembolso') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `saldo_anterior` decimal(10,2) DEFAULT NULL,
  `saldo_nuevo` decimal(10,2) DEFAULT NULL,
  `referencia_tipo` varchar(50) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_saldo_movimientos_usuario` (`usuario_id`),
  KEY `idx_saldo_movimientos_actor` (`actor_id`),
  KEY `idx_saldo_movimientos_ref` (`referencia_tipo`,`referencia_id`),
  CONSTRAINT `fk_saldo_movimientos_actor` FOREIGN KEY (`actor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_saldo_movimientos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saldo_movimientos`
--

LOCK TABLES `saldo_movimientos` WRITE;
/*!40000 ALTER TABLE `saldo_movimientos` DISABLE KEYS */;
INSERT INTO `saldo_movimientos` VALUES (1,15,15,'compra',10.00,1049.99,1039.99,'compras',13,'Compra de vix a pedido','2026-05-21 14:46:54'),(2,15,15,'compra',8.99,1039.99,1031.00,'compras',14,'Compra de HBO MAX PERFIL','2026-05-21 14:47:00'),(5,8,8,'compra',8.99,525.00,516.01,'compras',17,'Compra de HBO MAX PERFIL','2026-05-22 15:14:56'),(6,16,16,'compra',10.00,1995.00,1985.00,'compras',18,'Compra de Netflix 4K Premium','2026-05-22 19:17:48'),(7,16,16,'compra',10.00,1985.00,1975.00,'compras',19,'Compra de Netflix 4K Premium','2026-05-22 19:18:00'),(8,16,16,'compra',10.00,1975.00,1965.00,'compras',20,'Compra de Netflix 4K Premium','2026-05-22 19:18:07'),(9,16,16,'compra',10.00,1965.00,1955.00,'compras',21,'Compra de Netflix 4K Premium','2026-05-22 19:18:13'),(10,16,16,'compra',10.00,1955.00,1945.00,'compras',22,'Compra de Netflix 4K Premium','2026-05-22 19:18:16'),(11,16,16,'compra',5.00,1945.00,1940.00,'compras',23,'Compra de VIX','2026-05-26 05:37:56'),(12,16,16,'compra',5.00,1940.00,1935.00,'compras',24,'Compra de VIX','2026-05-26 06:14:01'),(13,16,16,'compra',5.00,1935.00,1930.00,'compras',25,'Compra de VIX','2026-05-26 06:14:05'),(14,16,16,'compra',5.00,1930.00,1925.00,'compras',26,'Compra de VIX','2026-05-26 06:14:07'),(15,16,16,'compra',5.00,1925.00,1920.00,'compras',27,'Compra de VIX','2026-05-26 06:14:10'),(16,16,16,'compra',10.00,2445.00,2435.00,'compras',28,'Compra de pene','2026-05-29 05:01:56'),(17,16,16,'compra',7.00,2435.00,2428.00,'compras',29,'Compra de disney','2026-05-30 16:11:52'),(18,16,16,'compra',7.00,2448.00,2441.00,'compras',30,'Compra de disney','2026-05-30 16:14:56'),(19,16,16,'compra',7.00,2441.00,2434.00,'compras',31,'Compra de disney','2026-05-31 00:39:27'),(20,16,16,'compra',7.00,2434.00,2427.00,'compras',32,'Compra de disney','2026-05-31 00:44:54'),(21,16,16,'compra',10.00,2427.00,2417.00,'compras',33,'Compra de vix a pedido','2026-05-31 00:45:18'),(22,16,16,'compra',7.00,2417.00,2410.00,'compras',34,'Compra de disney','2026-05-31 00:45:27'),(23,16,16,'compra',7.00,2410.00,2403.00,'compras',35,'Compra de disney','2026-05-31 00:45:30'),(24,16,16,'compra',7.00,2403.00,2396.00,'compras',36,'Compra de disney','2026-05-31 00:45:33'),(25,16,16,'compra',10.00,2396.00,2386.00,'compras',37,'Compra de vix a pedido','2026-05-31 01:01:19');
/*!40000 ALTER TABLE `saldo_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_auto_messages`
--

DROP TABLE IF EXISTS `ticket_auto_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_auto_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensaje` text NOT NULL,
  `tipo` enum('creacion','respuesta','cierre') DEFAULT 'creacion',
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_auto_messages`
--

LOCK TABLES `ticket_auto_messages` WRITE;
/*!40000 ALTER TABLE `ticket_auto_messages` DISABLE KEYS */;
INSERT INTO `ticket_auto_messages` VALUES (1,'✅ Ticket creado. Hola {nombre}, nos comunicaremos contigo por WhatsApp ({whatsapp}) en las próximas 24 horas. Tu ticket #{ticket_id} ha sido registrado.','creacion',1,'2026-01-23 15:32:54');
/*!40000 ALTER TABLE `ticket_auto_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_messages`
--

DROP TABLE IF EXISTS `ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `sender_role` enum('USER','ADMIN') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `ticket_id` (`ticket_id`) USING BTREE,
  KEY `sender_role` (`sender_role`) USING BTREE,
  KEY `sender_id` (`sender_id`) USING BTREE,
  CONSTRAINT `fk_ticket_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_messages`
--

LOCK TABLES `ticket_messages` WRITE;
/*!40000 ALTER TABLE `ticket_messages` DISABLE KEYS */;
INSERT INTO `ticket_messages` VALUES (1,1,'USER',7,'mi cuenta dejo de funcionar','2026-01-20 09:54:02'),(2,2,'USER',9,'no se que paso que dejo de entrar','2026-01-23 12:28:06'),(3,2,'',0,'✅ Ticket #2 creado exitosamente. Hola anal, nos comunicaremos contigo por WhatsApp (+51964279873) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-23 12:28:06'),(4,2,'ADMIN',8,'oño','2026-01-23 13:45:02'),(5,3,'USER',7,'<<<zzzzz','2026-01-23 14:55:50'),(6,3,'',0,'✅ Ticket #3 creado exitosamente. Hola maminiti, nos comunicaremos contigo por WhatsApp (+51937401236) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-23 14:55:50'),(7,4,'USER',10,'estafador de mrd dame mi plata','2026-01-24 14:10:41'),(8,4,'',0,'✅ Ticket #4 creado exitosamente. Hola monochoro, nos comunicaremos contigo por WhatsApp (+51952261472) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-24 14:10:41'),(9,4,'ADMIN',8,'hola andino de mrd ahorita te atiendo','2026-01-24 14:11:22'),(10,4,'ADMIN',8,'oño','2026-01-24 15:48:56'),(11,5,'USER',7,'oe ya p ctmr mono ratero','2026-01-26 20:56:09'),(12,5,'',0,'✅ Ticket #5 creado exitosamente. Hola maminiti, nos comunicaremos contigo por WhatsApp (+51937401236) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-26 20:56:09'),(13,6,'USER',16,'ASDASDA','2026-05-26 00:46:39'),(14,6,'',0,'✅ Ticket #6 creado exitosamente. Hola luisprueba, nos comunicaremos contigo por WhatsApp (+519762353222) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-05-26 00:46:39'),(15,6,'ADMIN',8,'BURRO','2026-05-26 00:48:08'),(16,7,'USER',16,'oe ratero de mrd mi cuenta no entra','2026-05-29 00:02:46'),(17,7,'',0,'✅ Ticket #7 creado exitosamente. Hola luisprueba, nos comunicaremos contigo por WhatsApp (+519762353222) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-05-29 00:02:46'),(18,7,'ADMIN',8,'burro eres , ya cache ya','2026-05-29 00:03:46');
/*!40000 ALTER TABLE `ticket_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_respuestas_old_20251223_091517`
--

DROP TABLE IF EXISTS `ticket_respuestas_old_20251223_091517`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_respuestas_old_20251223_091517` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `es_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `ticket_id` (`ticket_id`) USING BTREE,
  KEY `usuario_id` (`usuario_id`) USING BTREE,
  CONSTRAINT `ticket_respuestas_old_20251223_091517_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_respuestas_old_20251223_091517`
--

LOCK TABLES `ticket_respuestas_old_20251223_091517` WRITE;
/*!40000 ALTER TABLE `ticket_respuestas_old_20251223_091517` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_respuestas_old_20251223_091517` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('abierto','en_proceso','cerrado') NOT NULL DEFAULT 'abierto',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_reply_role` enum('USER','ADMIN') DEFAULT NULL,
  `last_reply_at` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `usuario_id` (`usuario_id`) USING BTREE,
  KEY `estado` (`estado`) USING BTREE,
  KEY `prioridad` (`prioridad`) USING BTREE,
  KEY `fk_tickets_cerrado_por` (`cerrado_por`) USING BTREE,
  CONSTRAINT `fk_tickets_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,7,'mi cuenta no entra','alta','abierto','2026-01-20 09:54:02','2026-01-20 09:54:02',NULL,NULL,NULL,NULL),(2,9,'mi cuenta no entra','alta','abierto','2026-01-23 12:28:06','2026-01-23 12:28:06',NULL,NULL,NULL,NULL),(3,7,'ya fue ya no quiero seguir','media','abierto','2026-01-23 14:55:50','2026-01-23 14:55:50',NULL,NULL,NULL,NULL),(4,10,'mi cuenta no entra','media','abierto','2026-01-24 14:10:41','2026-01-24 14:10:41',NULL,NULL,NULL,NULL),(5,7,'oe mano mi pedido p','alta','abierto','2026-01-26 20:56:09','2026-01-26 20:56:09',NULL,NULL,NULL,NULL),(6,16,'ZZZ','media','abierto','2026-05-26 00:46:39','2026-05-26 00:46:39',NULL,NULL,NULL,NULL),(7,16,'mi cuenta no entra','alta','abierto','2026-05-29 00:02:46','2026-05-29 00:02:46',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets_old_20251223_091517`
--

DROP TABLE IF EXISTS `tickets_old_20251223_091517`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets_old_20251223_091517` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('abierto','en proceso','cerrado') DEFAULT 'abierto',
  `prioridad` enum('baja','media','alta') DEFAULT 'media',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `usuario_id` (`usuario_id`) USING BTREE,
  CONSTRAINT `tickets_old_20251223_091517_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets_old_20251223_091517`
--

LOCK TABLES `tickets_old_20251223_091517` WRITE;
/*!40000 ALTER TABLE `tickets_old_20251223_091517` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets_old_20251223_091517` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','vendedor','cliente') DEFAULT 'cliente',
  `saldo` decimal(10,2) DEFAULT 0.00,
  `estado` enum('activo','suspendido','eliminado') NOT NULL DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `seller_status` enum('pendiente','activo','suspendido') NOT NULL DEFAULT 'activo',
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE,
  KEY `idx_usuarios_role` (`role`),
  KEY `idx_usuarios_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador','admin@monkeystraming.com',NULL,'$2y$10$YourHashedPasswordHere','admin',1000.00,'activo',NULL,'activo',NULL,'2025-12-07 11:03:03','2026-01-08 02:58:39'),(2,'cash','casumancilla@gmail.com',NULL,'$2y$10$V64mhtQiwVmS2QKtOEVmkOyIH32NaW41dODhW55WT.jZYRQkd3HRe','cliente',0.00,'',NULL,'activo',NULL,'2025-12-08 06:43:28','2026-05-20 05:58:26'),(3,'cashs','otriro5102@gmail.com',NULL,'$2y$10$vIQ83erWZkBCnHFj/4GAtuF61XkuOPN2nqs/ZZ6eluvnU27Aa5P9i','cliente',0.00,'activo',NULL,'activo',NULL,'2025-12-08 06:44:23','2026-05-26 05:45:15'),(4,'casuclash','casuclash@gmail.com',NULL,'$2y$10$uF7iVLxBOUfarHXRvZ4.2OWG8zZ2dcZMSbNeTJBAv/nf6MEFDFJB6','cliente',0.00,'activo',NULL,'activo',NULL,'2025-12-08 07:09:45','2026-05-20 05:58:26'),(5,'demo','demo@gmail.com',NULL,'$2y$10$aSS4h5Q/J2HNn2PHcGlMy.OrtDmF1dR/ddC.1fI6QfnAtlTqtOltq','cliente',0.00,'',NULL,'activo',NULL,'2025-12-08 22:51:03','2026-05-26 05:45:08'),(6,'demo2','demo2@gmail.com',NULL,'$2y$10$NYAzARAmuGEUdieV8EDy2.fbxhTzLl8Sui/B5JUrC2maOBVZ9ZL9e','cliente',0.00,'activo',NULL,'activo',NULL,'2025-12-17 14:07:51','2026-05-20 05:58:26'),(7,'maminiti','demo12@gmil.com','+51937401236','$2y$10$Pk.gxT33miTF3CbbYMJWPuniAI2fQN.NKiAtmoqd/fbCTZ.zSTlQi','cliente',2550.25,'activo',NULL,'activo',NULL,'2025-12-23 14:34:40','2026-05-20 05:58:26'),(8,'casuty','casuty@gmail.com',NULL,'$2y$10$l66I02YSu3hJ78vw2XRA.u7d8GVUiDA9jbDCroqlXFzjuvUoQ/inq','admin',516.01,'activo',NULL,'activo',NULL,'2025-12-23 19:26:04','2026-05-22 15:14:56'),(9,'anal','anal@gmail.com','+51964279873','$2y$10$G31HpL1uPgAsi/EtleQuce46QMnDJjCxMMnVhqN3i2Mf3BSlopijG','cliente',0.00,'activo',NULL,'activo',NULL,'2026-01-23 17:04:44','2026-05-20 05:58:26'),(10,'monochoro','monochoro@gmail.com','+51952261472','$2y$10$Tll5c4uVXLzKh1/Q3wixheIPz7XW///Po7ezKI1tRjIziLGiV/TV2','cliente',14.04,'activo',NULL,'activo',NULL,'2026-01-24 19:06:55','2026-05-20 05:58:26'),(11,'casa','casa@gmail.com',NULL,'S$2y$10$O4hNgxeO7zhHyYyShyJjS.9GGhAid3/YYmiRlqUBURsBPX56kMKby','admin',0.00,'activo',NULL,'activo',NULL,'2026-05-20 05:19:45','2026-05-20 05:45:18'),(12,'prueeba','demo0@gmil.com','+519642798744','$2y$10$M3yq8Dc8qnBSIyZPMRyTVe42SB88ndcXg2WZjlPvbtnHb/zEZG/e2','admin',0.00,'activo',NULL,'activo',NULL,'2026-05-20 05:51:22','2026-05-20 05:54:13'),(13,'mono','mono@gmail.com','937423236','$2y$10$FofT/8ByIpIZPA5WzfyHH.u.9qfoRwO3Uj414YWLLkzx0qV8QW/86','vendedor',0.00,'activo',12,'activo',NULL,'2026-05-20 06:10:44','2026-05-20 06:10:44'),(14,'casu','casu@gmail.com','937234123','$2y$10$8TW9mataACx1L4bnNtVIeOZLMB4TFtedzZHtBnI2LEpz7vzliI4XG','vendedor',0.00,'activo',12,'activo',NULL,'2026-05-21 14:03:55','2026-05-26 05:41:01'),(15,'prueba02','prueba02@gmail.com','+51943632783','$2y$10$CIJiw6uimuvI5EcTVWhxl.mD4L9AzQerYpn9tNA38G3y4iMetINA6','cliente',1031.00,'activo',NULL,'activo',NULL,'2026-05-21 14:11:23','2026-05-22 13:55:10'),(16,'luisprueba','luis@gmail.com','+519762353222','$2y$10$v4YTdTW/9/lz.ZxHp2GSROkb9KWOONXU8rxhmmT/TPAlYQQFPu.g6','cliente',2386.00,'activo',NULL,'activo',NULL,'2026-05-22 15:23:57','2026-05-31 01:01:19'),(17,'monito','monitochoro@gmail.com','93740111','$2y$10$i6JwGbgVCITdwOA2wUY1cu6lEEXcF/U.mC3DII0JmgezOTek1V4v6','vendedor',0.00,'activo',8,'activo',NULL,'2026-05-30 16:04:36','2026-05-30 16:04:36');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendedor_perfiles`
--

DROP TABLE IF EXISTS `vendedor_perfiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendedor_perfiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendedor_id` int(11) NOT NULL,
  `tienda_nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `soporte_whatsapp` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendedor_perfiles_vendedor` (`vendedor_id`),
  CONSTRAINT `fk_vendedor_perfiles_usuario` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendedor_perfiles`
--

LOCK TABLES `vendedor_perfiles` WRITE;
/*!40000 ALTER TABLE `vendedor_perfiles` DISABLE KEYS */;
INSERT INTO `vendedor_perfiles` VALUES (1,13,'monki',NULL,NULL,NULL,'937423236',1,'2026-05-20 06:10:44','2026-05-20 06:10:44'),(2,14,'casuty',NULL,NULL,NULL,'937234123',1,'2026-05-21 14:03:55','2026-05-21 14:03:55'),(6,17,'monitochoroshop',NULL,NULL,NULL,'93740111',1,'2026-05-30 16:04:36','2026-05-30 16:04:36');
/*!40000 ALTER TABLE `vendedor_perfiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendedor_retiros`
--

DROP TABLE IF EXISTS `vendedor_retiros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendedor_retiros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendedor_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo` varchar(50) NOT NULL,
  `cuenta_destino` varchar(190) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `nota` text DEFAULT NULL,
  `admin_nota` text DEFAULT NULL,
  `comprobante_url` varchar(255) DEFAULT NULL,
  `comprobante_subido_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `revisado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendedor_retiros_vendedor` (`vendedor_id`),
  KEY `idx_vendedor_retiros_estado` (`estado`),
  KEY `idx_vendedor_retiros_creado` (`creado_en`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendedor_retiros`
--

LOCK TABLES `vendedor_retiros` WRITE;
/*!40000 ALTER TABLE `vendedor_retiros` DISABLE KEYS */;
INSERT INTO `vendedor_retiros` VALUES (1,14,55.00,'Yape','964279873','aprobado','ASD','',NULL,NULL,'2026-05-26 01:04:34','2026-05-26 01:05:37'),(2,14,10.00,'Yape','964279873','rechazado','asdasd\nCancelado por el vendedor',NULL,NULL,NULL,'2026-05-26 01:14:54',NULL),(3,14,10.00,'Yape','964279873','rechazado','asdasd\nCancelado por el vendedor',NULL,NULL,NULL,'2026-05-26 01:20:13',NULL),(4,14,10.00,'Yape','964279833','aprobado','asd','asdad',NULL,NULL,'2026-05-26 01:22:42','2026-05-26 01:23:57'),(5,14,10.00,'Yape','964279822','aprobado','asdqweq','','uploads/comprobantes/retiro_5_26b289bcfd6d01b6.jpg','2026-05-26 01:25:56','2026-05-26 01:25:18','2026-05-26 01:25:56'),(6,14,10.00,'Yape','965738494','aprobado','pagame oe','no quiero p ya cache','uploads/comprobantes/retiro_6_b260b4365d587c20.jpg','2026-05-30 11:16:49','2026-05-30 11:09:36','2026-05-30 11:16:49');
/*!40000 ALTER TABLE `vendedor_retiros` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-30 21:10:43
