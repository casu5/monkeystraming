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
  `usuario_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','completada','cancelada') DEFAULT 'pendiente',
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` datetime DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `usuario_id` (`usuario_id`) USING BTREE,
  KEY `producto_id` (`producto_id`) USING BTREE,
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
INSERT INTO `compras` VALUES (1,7,2,8.99,'completada','2025-12-30 19:28:39','0000-00-00 00:00:00',NULL),(2,7,3,11.99,'completada','2025-12-30 19:30:29','0000-00-00 00:00:00',NULL),(3,7,2,8.99,'completada','2025-12-30 19:46:44','0000-00-00 00:00:00',NULL),(4,7,2,8.99,'completada','2025-12-31 14:33:08','0000-00-00 00:00:00',NULL),(6,7,2,8.99,'completada','2026-01-24 21:31:07','0000-00-00 00:00:00',NULL),(7,7,34,20.00,'completada','2026-01-24 23:35:42','0000-00-00 00:00:00',NULL),(8,7,2,8.99,'completada','2026-01-24 23:39:01',NULL,NULL),(9,7,2,8.99,'completada','2026-01-24 23:42:52','2026-02-23 18:42:52',NULL),(10,7,28,8.00,'completada','2026-01-25 01:42:27','2026-02-23 20:42:27',NULL),(11,7,31,15.00,'completada','2026-01-25 01:42:52','2026-02-23 20:42:52',NULL),(12,7,35,10.00,'completada','2026-01-27 01:54:59','2026-02-25 20:54:59',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuenta_perfiles`
--

LOCK TABLES `cuenta_perfiles` WRITE;
/*!40000 ALTER TABLE `cuenta_perfiles` DISABLE KEYS */;
INSERT INTO `cuenta_perfiles` VALUES (1,1,'Perfil 1','VENDIDO',7,NULL,'2026-01-30 12:18:21','2025-12-31 12:18:21','2025-12-31 11:01:40'),(2,1,'Perfil 2','VENDIDO',7,NULL,'2026-01-30 14:13:25','2025-12-31 14:13:25','2025-12-31 11:01:40'),(3,1,'Perfil 3','VENDIDO',7,NULL,'2026-01-30 14:17:54','2025-12-31 14:17:54','2025-12-31 11:01:40'),(4,1,'Perfil 4','VENDIDO',7,NULL,'2026-01-30 14:24:21','2025-12-31 14:24:21','2025-12-31 11:01:40'),(5,1,'Perfil 5','VENDIDO',7,NULL,'2026-01-30 14:24:30','2025-12-31 14:24:30','2025-12-31 11:01:40'),(6,2,'Perfil 1','VENDIDO',7,NULL,'2026-02-08 22:12:37','2026-01-09 22:12:37','2026-01-09 22:12:14'),(7,2,'Perfil 2','VENDIDO',7,NULL,'2026-02-08 22:12:43','2026-01-09 22:12:43','2026-01-09 22:12:14'),(8,2,'Perfil 3','VENDIDO',7,NULL,'2026-02-08 22:12:48','2026-01-09 22:12:48','2026-01-09 22:12:14'),(9,2,'Perfil 4','VENDIDO',7,NULL,'2026-02-08 22:12:54','2026-01-09 22:12:54','2026-01-09 22:12:14'),(10,3,'Perfil 1','VENDIDO',7,NULL,'2026-02-19 08:30:58','2026-01-20 08:30:58','2026-01-20 08:27:05'),(11,4,'Perfil 1','VENDIDO',7,11,'2026-02-23 20:42:52','2026-01-24 20:42:52','2026-01-20 08:27:41'),(12,5,'Perfil 1','VENDIDO',7,NULL,'2026-02-19 08:30:07','2026-01-20 08:30:07','2026-01-20 08:28:12'),(13,5,'Perfil 2','VENDIDO',7,NULL,'2026-02-19 08:30:33','2026-01-20 08:30:33','2026-01-20 08:28:12'),(14,5,'Perfil 3','VENDIDO',7,NULL,'2026-02-19 08:30:40','2026-01-20 08:30:40','2026-01-20 08:28:12'),(15,5,'Perfil 4','VENDIDO',7,NULL,'2026-02-19 08:30:46','2026-01-20 08:30:46','2026-01-20 08:28:12'),(16,6,'Perfil 1','VENDIDO',7,10,'2026-02-23 20:42:27','2026-01-24 20:42:27','2026-01-20 08:28:40'),(17,7,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:52:32','2026-01-24 15:52:32','2026-01-20 08:29:00'),(18,8,'Perfil 1','VENDIDO',10,NULL,'2026-02-23 14:10:01','2026-01-24 14:10:01','2026-01-24 14:09:47'),(19,8,'Perfil 2','VENDIDO',10,NULL,'2026-02-23 14:21:06','2026-01-24 14:21:06','2026-01-24 14:09:47'),(20,8,'Perfil 3','VENDIDO',10,NULL,'2026-02-23 14:21:16','2026-01-24 14:21:16','2026-01-24 14:09:47'),(21,8,'Perfil 4','VENDIDO',10,NULL,'2026-02-23 15:43:51','2026-01-24 15:43:51','2026-01-24 14:09:47'),(22,9,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:46:33','2026-01-24 15:46:33','2026-01-24 15:46:22'),(23,9,'Perfil 2','VENDIDO',7,NULL,'2026-02-23 15:47:03','2026-01-24 15:47:03','2026-01-24 15:46:22'),(24,9,'Perfil 3','VENDIDO',7,NULL,'2026-02-23 15:47:06','2026-01-24 15:47:06','2026-01-24 15:46:22'),(25,9,'Perfil 4','VENDIDO',7,NULL,'2026-02-23 15:47:08','2026-01-24 15:47:08','2026-01-24 15:46:22'),(26,9,'Perfil 5','VENDIDO',7,NULL,'2026-02-23 15:51:36','2026-01-24 15:51:36','2026-01-24 15:46:22'),(27,10,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:51:42','2026-01-24 15:51:42','2026-01-24 15:51:19'),(28,11,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:56:58','2026-01-24 15:56:58','2026-01-24 15:56:14'),(29,12,'Perfil 1','VENDIDO',7,NULL,'2026-02-23 15:56:52','2026-01-24 15:56:52','2026-01-24 15:56:41'),(30,12,'Perfil 2','VENDIDO',7,NULL,'2026-02-23 15:56:55','2026-01-24 15:56:55','2026-01-24 15:56:41'),(31,12,'Perfil 3','VENDIDO',7,6,'2026-02-23 16:31:07','2026-01-24 16:31:07','2026-01-24 15:56:41'),(32,12,'Perfil 4','VENDIDO',7,8,'2026-02-23 18:39:01','2026-01-24 18:39:01','2026-01-24 15:56:41'),(33,13,'Perfil 1','VENDIDO',7,7,'2026-02-23 18:35:42','2026-01-24 18:35:42','2026-01-24 18:34:25'),(34,14,'Perfil 1','VENDIDO',7,9,'2026-02-23 18:42:52','2026-01-24 18:42:52','2026-01-24 18:34:51'),(35,14,'Perfil 2','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-24 18:34:51'),(36,14,'Perfil 3','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-24 18:34:51'),(37,14,'Perfil 4','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-24 18:34:51'),(38,14,'Perfil 5','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-24 18:34:51'),(39,15,'Perfil 1','VENDIDO',7,12,'2026-02-25 20:54:59','2026-01-26 20:54:59','2026-01-26 20:54:32'),(40,15,'Perfil 2','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-26 20:54:32'),(41,15,'Perfil 3','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-26 20:54:32'),(42,15,'Perfil 4','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-26 20:54:32'),(43,15,'Perfil 5','DISPONIBLE',NULL,NULL,NULL,NULL,'2026-01-26 20:54:32');
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
  `producto_id` int(11) NOT NULL,
  `login_user` varchar(190) NOT NULL,
  `login_pass` varchar(190) NOT NULL,
  `pin` varchar(50) DEFAULT NULL,
  `max_perfiles` int(11) NOT NULL DEFAULT 1,
  `estado` enum('DISPONIBLE','VENDIDA_COMPLETA','BLOQUEADA') NOT NULL DEFAULT 'DISPONIBLE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `producto_id` (`producto_id`) USING BTREE,
  KEY `estado` (`estado`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas`
--

LOCK TABLES `cuentas` WRITE;
/*!40000 ALTER TABLE `cuentas` DISABLE KEYS */;
INSERT INTO `cuentas` VALUES (1,1,'otracosa@gmail.com','123','12',5,'DISPONIBLE','2025-12-31 11:01:40'),(2,2,'max@gmail.com','123','1234',4,'DISPONIBLE','2026-01-09 22:12:14'),(3,32,'youprueba@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-20 08:27:05'),(4,31,'VAXZ-ASDC-ASCW-ASCC','SIN CONTRA',NULL,1,'DISPONIBLE','2026-01-20 08:27:41'),(5,30,'vix@gmail.com','123','123',4,'DISPONIBLE','2026-01-20 08:28:12'),(6,28,'surf@gmail.com','123123',NULL,1,'DISPONIBLE','2026-01-20 08:28:40'),(7,4,'spoty@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-20 08:29:00'),(8,2,'habvoassod@gmail.com','123124',NULL,4,'DISPONIBLE','2026-01-24 14:09:47'),(9,2,'otrademax@gmail.com','123412',NULL,5,'DISPONIBLE','2026-01-24 15:46:22'),(10,2,'masdafsa@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-24 15:51:19'),(11,34,'dfasfa@gmail.com','123',NULL,1,'DISPONIBLE','2026-01-24 15:56:14'),(12,2,'sdfsfsdghh@gmail.com','123',NULL,4,'DISPONIBLE','2026-01-24 15:56:41'),(13,34,'maxpruevba@gmail.com','12341',NULL,1,'DISPONIBLE','2026-01-24 18:34:25'),(14,2,'maxperfilmqmq@gmail.com','123',NULL,5,'DISPONIBLE','2026-01-24 18:34:51'),(15,35,'comunicate con el proveedor','9612341234',NULL,5,'DISPONIBLE','2026-01-26 20:54:32');
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
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 1,
  `destacado` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contraseña` varchar(20) DEFAULT NULL,
  `tipo_venta` enum('PERFIL','CUENTA_COMPLETA') NOT NULL DEFAULT 'PERFIL',
  `duracion_dias` int(11) NOT NULL DEFAULT 30,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `categoria_id` (`categoria_id`) USING BTREE,
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'Netflix 4K Premium','4 pantallas simultáneas, contenido 4K',9.99,1,'uploads/productos/prod_0557220aade1a8eb39943966.png',50,1,1,'2025-12-07 11:03:03',NULL,'PERFIL',30),(2,'HBO MAX PERFIL','Series exclusivas y estrenos',8.99,1,'uploads/productos/prod_e911917c3bf653da1e0ca8cd.jpg',6,1,1,'2025-12-07 11:03:03',NULL,'PERFIL',30),(3,'Disney+','Disney, Marvel, Star Wars, Pixar',11.99,1,'uploads/productos/prod_068af2a597ec4bba0ffc0811.jpg',29,1,1,'2025-12-07 11:03:03',NULL,'PERFIL',30),(4,'Spotify Premium','Música sin anuncios, modo offline',9.99,3,'uploads/productos/prod_0f0b85f0d502aba785972bf3.jpg',1,1,1,'2025-12-07 11:03:03',NULL,'PERFIL',30),(5,'Xbox Game Pass','+100 juegos en PC y consola',14.99,4,'assets/img/productos/xbox.png',25,0,1,'2025-12-07 11:03:03',NULL,'PERFIL',30),(6,'SETSP','ADADA',11.99,4,'',0,0,0,'2025-12-26 16:16:36',NULL,'PERFIL',30),(7,'Adobe Creative Cloud','',10.00,6,'uploads/productos/prod_8228f7c19641adad8e95b5ad.jpg',0,0,1,'2026-01-20 13:13:18',NULL,'PERFIL',30),(8,'Autodesck','',10.00,6,'uploads/productos/prod_3cb24ecc05f121518a626a29.jpg',0,0,1,'2026-01-20 13:13:51',NULL,'PERFIL',30),(9,'Canva','',5.00,6,'uploads/productos/prod_fb68ec2c1f8af47003cc14f1.jpg',0,0,1,'2026-01-20 13:14:09',NULL,'PERFIL',30),(10,'Canva 1 año','',15.00,NULL,'uploads/productos/prod_c8e9f0ff12b7106b256d3d58.jpg',0,0,1,'2026-01-20 13:14:39',NULL,'PERFIL',30),(11,'Canva 2 años','',25.00,6,'uploads/productos/prod_6cf8269912f733402f7e12d5.jpg',0,0,1,'2026-01-20 13:14:58',NULL,'PERFIL',30),(12,'Chat GPT','',12.00,5,'uploads/productos/prod_3a8c9a49cb2bed2c67af03f6.jpg',0,0,1,'2026-01-20 13:15:23',NULL,'PERFIL',30),(13,'Crunchyrroll','',4.00,2,'uploads/productos/prod_554f3189cc327e893d9f3360.jpg',0,0,1,'2026-01-20 13:15:45',NULL,'PERFIL',30),(14,'Deezer','',10.00,3,'uploads/productos/prod_ea5bc237b3b762711fee012f.jpg',0,0,1,'2026-01-20 13:16:05',NULL,'PERFIL',30),(15,'DGO','',10.00,4,'uploads/productos/prod_5bc7cf267d5cba9886b292f3.jpg',0,0,1,'2026-01-20 13:16:25',NULL,'PERFIL',30),(16,'DGO + LIGA 1 MAX','',20.00,4,'uploads/productos/prod_630e267104ae1d0b27199098.jpg',0,0,1,'2026-01-20 13:16:51',NULL,'PERFIL',30),(17,'ESET','',10.00,6,'uploads/productos/prod_7cdbbad2d9f1a7af78ec814e.jpg',0,0,1,'2026-01-20 13:17:14',NULL,'PERFIL',30),(18,'Express VPN','',5.00,6,'uploads/productos/prod_92fb2c344e8f7e9cc8bb0b62.jpg',0,0,1,'2026-01-20 13:17:31',NULL,'PERFIL',30),(19,'Gemini PRO','',10.00,5,'uploads/productos/prod_3a653e97f89261ac8749a8cf.jpg',0,0,1,'2026-01-20 13:18:09',NULL,'PERFIL',30),(20,'IPTV','',5.00,NULL,'uploads/productos/prod_bb261eeba6c5699d54744987.jpg',0,0,1,'2026-01-20 13:18:24',NULL,'PERFIL',30),(21,'Kaspersky','',10.00,6,'uploads/productos/prod_878cfe796a6256dc2eee9dd5.jpg',0,0,1,'2026-01-20 13:18:43',NULL,'PERFIL',30),(22,'Macfee','',5.00,NULL,'uploads/productos/prod_f6c4f06a153541f15d1f1b31.jpg',0,0,1,'2026-01-20 13:19:01',NULL,'PERFIL',30),(23,'Nord VPN','',6.00,6,'uploads/productos/prod_1538886c8f9d5fe8eaba6708.jpg',0,0,1,'2026-01-20 13:19:25',NULL,'PERFIL',30),(24,'Oiffice 365','',12.00,NULL,'uploads/productos/prod_9184fe10e9920a805e23afd3.jpg',0,0,1,'2026-01-20 13:19:49',NULL,'PERFIL',30),(25,'Perplexity','',12.00,5,'uploads/productos/prod_066b601b9c7e469088a519d5.jpg',0,0,1,'2026-01-20 13:20:07',NULL,'PERFIL',30),(26,'Prime Video','',5.00,2,'uploads/productos/prod_34a01caa261c9966244ac44b.jpg',0,0,1,'2026-01-20 13:20:25',NULL,'PERFIL',30),(27,'Pure VPN','',6.00,NULL,'uploads/productos/prod_b96547b981496460b54ee15d.jpg',0,0,1,'2026-01-20 13:20:41',NULL,'PERFIL',30),(28,'Surf Shack VPN','',8.00,6,'uploads/productos/prod_7e3cca32a150bb9940b04c5a.jpg',1,0,1,'2026-01-20 13:21:58',NULL,'PERFIL',30),(29,'Tidal Music','',6.00,3,'uploads/productos/prod_4966d2dac691b52ef91c26ba.jpg',0,0,1,'2026-01-20 13:22:18',NULL,'PERFIL',30),(30,'Vix','',5.00,2,'uploads/productos/prod_4fe56cb77f705c8e0e982a60.jpg',4,0,1,'2026-01-20 13:22:49',NULL,'PERFIL',30),(31,'Windows 11 PRO','',15.00,6,'uploads/productos/prod_2c9de059de9b21953490741d.jpg',1,0,1,'2026-01-20 13:23:10',NULL,'PERFIL',30),(32,'YouTube Premiun','',8.00,3,'uploads/productos/prod_a2148ce8c50e133f7c7c0a63.jpg',1,0,1,'2026-01-20 13:23:28',NULL,'PERFIL',30),(33,'porno','adadasda',10.00,1,'uploads/productos/prod_06df3c03aaf6dec3d0fc9e2d.jpg',0,0,0,'2026-01-24 19:12:48',NULL,'PERFIL',30),(34,'HBO MAX Completo','',20.00,1,'uploads/productos/prod_b75ee843e6d17c632680ece0.jpg',1,0,1,'2026-01-24 20:54:40',NULL,'PERFIL',30),(35,'vix a pedido','',10.00,1,'uploads/productos/prod_62b763d57676b446e7f5bd40.png',5,0,1,'2026-01-27 01:52:58',NULL,'PERFIL',30);
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recargas`
--

LOCK TABLES `recargas` WRITE;
/*!40000 ALTER TABLE `recargas` DISABLE KEYS */;
INSERT INTO `recargas` VALUES (1,7,'yape',50.00,0.00,'assets/comprobantes/recarga_1_1767122823.jfif','aprobada','2025-12-30 19:27:03','2025-12-30 19:27:52',8,NULL),(2,7,'yape',200.00,0.00,'assets/comprobantes/recarga_2_1767197015.jfif','aprobada','2025-12-31 16:03:35','2025-12-31 16:05:21',8,NULL),(3,7,'plin',500.00,0.00,'assets/comprobantes/recarga_3_1768014490.jpg','aprobada','2026-01-10 03:08:10','2026-01-10 03:10:04',8,NULL),(4,7,'yape',500.00,0.00,'assets/comprobantes/recarga_4_1768020875.jpg','aprobada','2026-01-10 04:54:35','2026-01-19 21:00:47',8,NULL),(5,7,'yape',500.00,0.00,'assets/comprobantes/recarga_5_1769178187.png','aprobada','2026-01-23 14:23:07','2026-01-23 14:23:17',8,NULL),(6,7,'yape',1000.00,0.00,'assets/comprobantes/recarga_6_1769201351.png','aprobada','2026-01-23 20:49:11','2026-01-23 20:49:27',8,NULL),(7,7,'yape',50.00,0.00,'assets/comprobantes/recarga_7_1769204558.png','rechazada','2026-01-23 21:42:38','2026-01-23 21:43:10',8,'es un sonso se cree vivo'),(8,10,'yape',50.00,0.00,'assets/comprobantes/recarga_8_1769281709.png','aprobada','2026-01-24 19:08:29','2026-01-24 19:08:51',8,NULL),(9,8,'yape',500.00,0.00,'assets/comprobantes/recarga_9_1769479152.png','aprobada','2026-01-27 01:59:12','2026-01-27 01:59:39',8,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperaciones_pendientes`
--

LOCK TABLES `recuperaciones_pendientes` WRITE;
/*!40000 ALTER TABLE `recuperaciones_pendientes` DISABLE KEYS */;
INSERT INTO `recuperaciones_pendientes` VALUES (3,7,'+51937401236','maminiti','e7d6598eb571bb0bd030e04e3c20050e5f712755832048c045c7a82200647beb','http://localhost/monkydos/restablecer.php?token=e7d6598eb571bb0bd030e04e3c20050e5f712755832048c045c7a82200647beb','enviado','2026-01-24 20:07:51','2026-01-24 20:38:04'),(4,7,'+51937401236','maminiti','ad932281906f003a7d523fd08292194b0827bd7d601ecaecdf5db6466b60e249','http://localhost/monkydos/restablecer.php?token=ad932281906f003a7d523fd08292194b0827bd7d601ecaecdf5db6466b60e249','enviado','2026-01-24 20:26:03','2026-01-24 20:37:34'),(5,7,'+51937401236','maminiti','debaa7e7db01995803f27e210dd9440c743692f0b463cab4091dac004c51e7a1','http://localhost/monkydos/restablecer.php?token=debaa7e7db01995803f27e210dd9440c743692f0b463cab4091dac004c51e7a1','enviado','2026-01-26 09:41:47','2026-01-26 09:47:49'),(6,7,'+51937401236','maminiti','f463fe914236d5c95d241f651f58f6b0bc8902ec156243cc26a3bbd2512854de','http://localhost/monkydos/restablecer.php?token=f463fe914236d5c95d241f651f58f6b0bc8902ec156243cc26a3bbd2512854de','pendiente','2026-01-26 21:01:58',NULL);
/*!40000 ALTER TABLE `recuperaciones_pendientes` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_messages`
--

LOCK TABLES `ticket_messages` WRITE;
/*!40000 ALTER TABLE `ticket_messages` DISABLE KEYS */;
INSERT INTO `ticket_messages` VALUES (1,1,'USER',7,'mi cuenta dejo de funcionar','2026-01-20 09:54:02'),(2,2,'USER',9,'no se que paso que dejo de entrar','2026-01-23 12:28:06'),(3,2,'',0,'✅ Ticket #2 creado exitosamente. Hola anal, nos comunicaremos contigo por WhatsApp (+51964279873) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-23 12:28:06'),(4,2,'ADMIN',8,'oño','2026-01-23 13:45:02'),(5,3,'USER',7,'<<<zzzzz','2026-01-23 14:55:50'),(6,3,'',0,'✅ Ticket #3 creado exitosamente. Hola maminiti, nos comunicaremos contigo por WhatsApp (+51937401236) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-23 14:55:50'),(7,4,'USER',10,'estafador de mrd dame mi plata','2026-01-24 14:10:41'),(8,4,'',0,'✅ Ticket #4 creado exitosamente. Hola monochoro, nos comunicaremos contigo por WhatsApp (+51952261472) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-24 14:10:41'),(9,4,'ADMIN',8,'hola andino de mrd ahorita te atiendo','2026-01-24 14:11:22'),(10,4,'ADMIN',8,'oño','2026-01-24 15:48:56'),(11,5,'USER',7,'oe ya p ctmr mono ratero','2026-01-26 20:56:09'),(12,5,'',0,'✅ Ticket #5 creado exitosamente. Hola maminiti, nos comunicaremos contigo por WhatsApp (+51937401236) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.','2026-01-26 20:56:09');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,7,'mi cuenta no entra','alta','abierto','2026-01-20 09:54:02','2026-01-20 09:54:02',NULL,NULL,NULL,NULL),(2,9,'mi cuenta no entra','alta','abierto','2026-01-23 12:28:06','2026-01-23 12:28:06',NULL,NULL,NULL,NULL),(3,7,'ya fue ya no quiero seguir','media','abierto','2026-01-23 14:55:50','2026-01-23 14:55:50',NULL,NULL,NULL,NULL),(4,10,'mi cuenta no entra','media','abierto','2026-01-24 14:10:41','2026-01-24 14:10:41',NULL,NULL,NULL,NULL),(5,7,'oe mano mi pedido p','alta','abierto','2026-01-26 20:56:09','2026-01-26 20:56:09',NULL,NULL,NULL,NULL);
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
  `role` enum('user','admin') DEFAULT 'user',
  `saldo` decimal(10,2) DEFAULT 0.00,
  `estado` enum('activo','suspendido','eliminado') NOT NULL DEFAULT 'activo',
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador','admin@monkeystraming.com',NULL,'$2y$10$YourHashedPasswordHere','admin',1000.00,'activo',NULL,'2025-12-07 11:03:03','2026-01-08 02:58:39'),(2,'cash','casumancilla@gmail.com',NULL,'$2y$10$V64mhtQiwVmS2QKtOEVmkOyIH32NaW41dODhW55WT.jZYRQkd3HRe','user',0.00,'',NULL,'2025-12-08 06:43:28','2026-01-08 02:58:43'),(3,'cashs','otriro5102@gmail.com',NULL,'$2y$10$vIQ83erWZkBCnHFj/4GAtuF61XkuOPN2nqs/ZZ6eluvnU27Aa5P9i','user',0.00,'',NULL,'2025-12-08 06:44:23','2026-01-08 02:58:47'),(4,'casuclash','casuclash@gmail.com',NULL,'$2y$10$uF7iVLxBOUfarHXRvZ4.2OWG8zZ2dcZMSbNeTJBAv/nf6MEFDFJB6','user',0.00,'activo',NULL,'2025-12-08 07:09:45','2025-12-08 07:09:45'),(5,'demo','demo@gmail.com',NULL,'$2y$10$aSS4h5Q/J2HNn2PHcGlMy.OrtDmF1dR/ddC.1fI6QfnAtlTqtOltq','user',0.00,'activo',NULL,'2025-12-08 22:51:03','2025-12-08 22:51:03'),(6,'demo2','demo2@gmail.com',NULL,'$2y$10$NYAzARAmuGEUdieV8EDy2.fbxhTzLl8Sui/B5JUrC2maOBVZ9ZL9e','user',0.00,'activo',NULL,'2025-12-17 14:07:51','2025-12-17 14:07:51'),(7,'maminiti','demo12@gmil.com','+51937401236','$2y$10$Pk.gxT33miTF3CbbYMJWPuniAI2fQN.NKiAtmoqd/fbCTZ.zSTlQi','user',2550.25,'activo',NULL,'2025-12-23 14:34:40','2026-01-27 01:54:59'),(8,'casuty','casuty@gmail.com',NULL,'$2y$10$l66I02YSu3hJ78vw2XRA.u7d8GVUiDA9jbDCroqlXFzjuvUoQ/inq','admin',525.00,'activo',NULL,'2025-12-23 19:26:04','2026-01-27 01:59:39'),(9,'anal','anal@gmail.com','+51964279873','$2y$10$G31HpL1uPgAsi/EtleQuce46QMnDJjCxMMnVhqN3i2Mf3BSlopijG','user',0.00,'activo',NULL,'2026-01-23 17:04:44','2026-01-23 17:04:44'),(10,'monochoro','monochoro@gmail.com','+51952261472','$2y$10$Tll5c4uVXLzKh1/Q3wixheIPz7XW///Po7ezKI1tRjIziLGiV/TV2','user',14.04,'activo',NULL,'2026-01-24 19:06:55','2026-01-24 20:43:51'),(11,'casa','casa@gmail.com',NULL,'S$2y$10$O4hNgxeO7zhHyYyShyJjS.9GGhAid3/YYmiRlqUBURsBPX56kMKby','admin',0.00,'activo',NULL,'2026-05-20 05:19:45','2026-05-20 05:45:18'),(12,'prueeba','demo0@gmil.com','+519642798744','$2y$10$M3yq8Dc8qnBSIyZPMRyTVe42SB88ndcXg2WZjlPvbtnHb/zEZG/e2','admin',0.00,'activo',NULL,'2026-05-20 05:51:22','2026-05-20 05:54:13');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-20  0:58:12
