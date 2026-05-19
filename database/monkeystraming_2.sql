/*
 Navicat Premium Data Transfer

 Source Server         : localhost_3306
 Source Server Type    : MySQL
 Source Server Version : 100432
 Source Host           : localhost:3306
 Source Schema         : monkeystraming_2

 Target Server Type    : MySQL
 Target Server Version : 100432
 File Encoding         : 65001

 Date: 19/05/2026 13:57:39
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for categorias
-- ----------------------------
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `icono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `color` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of categorias
-- ----------------------------
INSERT INTO `categorias` VALUES (1, '🎬 Películas', 1, 'Las mejores películas en streaming', 'fas fa-film', '#12aaff', '2025-12-07 06:03:03');
INSERT INTO `categorias` VALUES (2, '📺 Series', 1, 'Series completas y temporadas', 'fas fa-tv', '#0de0c9', '2025-12-07 06:03:03');
INSERT INTO `categorias` VALUES (3, '🎵 Música', 1, 'Plataformas de música streaming', 'fas fa-music', '#9d4edd', '2025-12-07 06:03:03');
INSERT INTO `categorias` VALUES (4, '🎮 Juegos', 1, 'Juegos y suscripciones gaming', 'fas fa-gamepad', '#ff6d00', '2025-12-07 06:03:03');
INSERT INTO `categorias` VALUES (5, '🤖 IA', 1, 'Herramientas de inteligencia artificial', 'fas fa-robot', '#ff0054', '2025-12-07 06:03:03');
INSERT INTO `categorias` VALUES (6, '💻 Software', 1, 'Software y aplicaciones', 'fas fa-laptop-code', '#00c853', '2025-12-07 06:03:03');

-- ----------------------------
-- Table structure for compra_items
-- ----------------------------
DROP TABLE IF EXISTS `compra_items`;
CREATE TABLE `compra_items`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `compra_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `tipo_venta` enum('PERFIL','CUENTA_COMPLETA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `precio` decimal(10, 2) NOT NULL,
  `cuenta_id` int NULL DEFAULT NULL,
  `perfil_id` int NULL DEFAULT NULL,
  `vence_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `compra_id`(`compra_id` ASC) USING BTREE,
  INDEX `producto_id`(`producto_id` ASC) USING BTREE,
  INDEX `cuenta_id`(`cuenta_id` ASC) USING BTREE,
  INDEX `perfil_id`(`perfil_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of compra_items
-- ----------------------------

-- ----------------------------
-- Table structure for compras
-- ----------------------------
DROP TABLE IF EXISTS `compras`;
CREATE TABLE `compras`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `monto` decimal(10, 2) NOT NULL,
  `estado` enum('pendiente','completada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'pendiente',
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp,
  `fecha_vencimiento` datetime NULL DEFAULT NULL,
  `detalles` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `usuario_id`(`usuario_id` ASC) USING BTREE,
  INDEX `producto_id`(`producto_id` ASC) USING BTREE,
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of compras
-- ----------------------------
INSERT INTO `compras` VALUES (1, 7, 2, 8.99, 'completada', '2025-12-30 14:28:39', '0000-00-00 00:00:00', NULL);
INSERT INTO `compras` VALUES (2, 7, 3, 11.99, 'completada', '2025-12-30 14:30:29', '0000-00-00 00:00:00', NULL);
INSERT INTO `compras` VALUES (3, 7, 2, 8.99, 'completada', '2025-12-30 14:46:44', '0000-00-00 00:00:00', NULL);
INSERT INTO `compras` VALUES (4, 7, 2, 8.99, 'completada', '2025-12-31 09:33:08', '0000-00-00 00:00:00', NULL);
INSERT INTO `compras` VALUES (6, 7, 2, 8.99, 'completada', '2026-01-24 16:31:07', '0000-00-00 00:00:00', NULL);
INSERT INTO `compras` VALUES (7, 7, 34, 20.00, 'completada', '2026-01-24 18:35:42', '0000-00-00 00:00:00', NULL);
INSERT INTO `compras` VALUES (8, 7, 2, 8.99, 'completada', '2026-01-24 18:39:01', NULL, NULL);
INSERT INTO `compras` VALUES (9, 7, 2, 8.99, 'completada', '2026-01-24 18:42:52', '2026-02-23 18:42:52', NULL);
INSERT INTO `compras` VALUES (10, 7, 28, 8.00, 'completada', '2026-01-24 20:42:27', '2026-02-23 20:42:27', NULL);
INSERT INTO `compras` VALUES (11, 7, 31, 15.00, 'completada', '2026-01-24 20:42:52', '2026-02-23 20:42:52', NULL);
INSERT INTO `compras` VALUES (12, 7, 35, 10.00, 'completada', '2026-01-26 20:54:59', '2026-02-25 20:54:59', NULL);

-- ----------------------------
-- Table structure for cuenta_perfiles
-- ----------------------------
DROP TABLE IF EXISTS `cuenta_perfiles`;
CREATE TABLE `cuenta_perfiles`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `cuenta_id` int NOT NULL,
  `perfil_nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Perfil',
  `estado` enum('DISPONIBLE','RESERVADO','VENDIDO','BLOQUEADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DISPONIBLE',
  `vendido_a_usuario_id` int NULL DEFAULT NULL,
  `compra_item_id` int NULL DEFAULT NULL,
  `vence_at` datetime NULL DEFAULT NULL,
  `vendido_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `cuenta_id`(`cuenta_id` ASC) USING BTREE,
  INDEX `estado`(`estado` ASC) USING BTREE,
  CONSTRAINT `fk_cp_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 44 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of cuenta_perfiles
-- ----------------------------
INSERT INTO `cuenta_perfiles` VALUES (1, 1, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-01-30 12:18:21', '2025-12-31 12:18:21', '2025-12-31 11:01:40');
INSERT INTO `cuenta_perfiles` VALUES (2, 1, 'Perfil 2', 'VENDIDO', 7, NULL, '2026-01-30 14:13:25', '2025-12-31 14:13:25', '2025-12-31 11:01:40');
INSERT INTO `cuenta_perfiles` VALUES (3, 1, 'Perfil 3', 'VENDIDO', 7, NULL, '2026-01-30 14:17:54', '2025-12-31 14:17:54', '2025-12-31 11:01:40');
INSERT INTO `cuenta_perfiles` VALUES (4, 1, 'Perfil 4', 'VENDIDO', 7, NULL, '2026-01-30 14:24:21', '2025-12-31 14:24:21', '2025-12-31 11:01:40');
INSERT INTO `cuenta_perfiles` VALUES (5, 1, 'Perfil 5', 'VENDIDO', 7, NULL, '2026-01-30 14:24:30', '2025-12-31 14:24:30', '2025-12-31 11:01:40');
INSERT INTO `cuenta_perfiles` VALUES (6, 2, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-08 22:12:37', '2026-01-09 22:12:37', '2026-01-09 22:12:14');
INSERT INTO `cuenta_perfiles` VALUES (7, 2, 'Perfil 2', 'VENDIDO', 7, NULL, '2026-02-08 22:12:43', '2026-01-09 22:12:43', '2026-01-09 22:12:14');
INSERT INTO `cuenta_perfiles` VALUES (8, 2, 'Perfil 3', 'VENDIDO', 7, NULL, '2026-02-08 22:12:48', '2026-01-09 22:12:48', '2026-01-09 22:12:14');
INSERT INTO `cuenta_perfiles` VALUES (9, 2, 'Perfil 4', 'VENDIDO', 7, NULL, '2026-02-08 22:12:54', '2026-01-09 22:12:54', '2026-01-09 22:12:14');
INSERT INTO `cuenta_perfiles` VALUES (10, 3, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-19 08:30:58', '2026-01-20 08:30:58', '2026-01-20 08:27:05');
INSERT INTO `cuenta_perfiles` VALUES (11, 4, 'Perfil 1', 'VENDIDO', 7, 11, '2026-02-23 20:42:52', '2026-01-24 20:42:52', '2026-01-20 08:27:41');
INSERT INTO `cuenta_perfiles` VALUES (12, 5, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-19 08:30:07', '2026-01-20 08:30:07', '2026-01-20 08:28:12');
INSERT INTO `cuenta_perfiles` VALUES (13, 5, 'Perfil 2', 'VENDIDO', 7, NULL, '2026-02-19 08:30:33', '2026-01-20 08:30:33', '2026-01-20 08:28:12');
INSERT INTO `cuenta_perfiles` VALUES (14, 5, 'Perfil 3', 'VENDIDO', 7, NULL, '2026-02-19 08:30:40', '2026-01-20 08:30:40', '2026-01-20 08:28:12');
INSERT INTO `cuenta_perfiles` VALUES (15, 5, 'Perfil 4', 'VENDIDO', 7, NULL, '2026-02-19 08:30:46', '2026-01-20 08:30:46', '2026-01-20 08:28:12');
INSERT INTO `cuenta_perfiles` VALUES (16, 6, 'Perfil 1', 'VENDIDO', 7, 10, '2026-02-23 20:42:27', '2026-01-24 20:42:27', '2026-01-20 08:28:40');
INSERT INTO `cuenta_perfiles` VALUES (17, 7, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-23 15:52:32', '2026-01-24 15:52:32', '2026-01-20 08:29:00');
INSERT INTO `cuenta_perfiles` VALUES (18, 8, 'Perfil 1', 'VENDIDO', 10, NULL, '2026-02-23 14:10:01', '2026-01-24 14:10:01', '2026-01-24 14:09:47');
INSERT INTO `cuenta_perfiles` VALUES (19, 8, 'Perfil 2', 'VENDIDO', 10, NULL, '2026-02-23 14:21:06', '2026-01-24 14:21:06', '2026-01-24 14:09:47');
INSERT INTO `cuenta_perfiles` VALUES (20, 8, 'Perfil 3', 'VENDIDO', 10, NULL, '2026-02-23 14:21:16', '2026-01-24 14:21:16', '2026-01-24 14:09:47');
INSERT INTO `cuenta_perfiles` VALUES (21, 8, 'Perfil 4', 'VENDIDO', 10, NULL, '2026-02-23 15:43:51', '2026-01-24 15:43:51', '2026-01-24 14:09:47');
INSERT INTO `cuenta_perfiles` VALUES (22, 9, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-23 15:46:33', '2026-01-24 15:46:33', '2026-01-24 15:46:22');
INSERT INTO `cuenta_perfiles` VALUES (23, 9, 'Perfil 2', 'VENDIDO', 7, NULL, '2026-02-23 15:47:03', '2026-01-24 15:47:03', '2026-01-24 15:46:22');
INSERT INTO `cuenta_perfiles` VALUES (24, 9, 'Perfil 3', 'VENDIDO', 7, NULL, '2026-02-23 15:47:06', '2026-01-24 15:47:06', '2026-01-24 15:46:22');
INSERT INTO `cuenta_perfiles` VALUES (25, 9, 'Perfil 4', 'VENDIDO', 7, NULL, '2026-02-23 15:47:08', '2026-01-24 15:47:08', '2026-01-24 15:46:22');
INSERT INTO `cuenta_perfiles` VALUES (26, 9, 'Perfil 5', 'VENDIDO', 7, NULL, '2026-02-23 15:51:36', '2026-01-24 15:51:36', '2026-01-24 15:46:22');
INSERT INTO `cuenta_perfiles` VALUES (27, 10, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-23 15:51:42', '2026-01-24 15:51:42', '2026-01-24 15:51:19');
INSERT INTO `cuenta_perfiles` VALUES (28, 11, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-23 15:56:58', '2026-01-24 15:56:58', '2026-01-24 15:56:14');
INSERT INTO `cuenta_perfiles` VALUES (29, 12, 'Perfil 1', 'VENDIDO', 7, NULL, '2026-02-23 15:56:52', '2026-01-24 15:56:52', '2026-01-24 15:56:41');
INSERT INTO `cuenta_perfiles` VALUES (30, 12, 'Perfil 2', 'VENDIDO', 7, NULL, '2026-02-23 15:56:55', '2026-01-24 15:56:55', '2026-01-24 15:56:41');
INSERT INTO `cuenta_perfiles` VALUES (31, 12, 'Perfil 3', 'VENDIDO', 7, 6, '2026-02-23 16:31:07', '2026-01-24 16:31:07', '2026-01-24 15:56:41');
INSERT INTO `cuenta_perfiles` VALUES (32, 12, 'Perfil 4', 'VENDIDO', 7, 8, '2026-02-23 18:39:01', '2026-01-24 18:39:01', '2026-01-24 15:56:41');
INSERT INTO `cuenta_perfiles` VALUES (33, 13, 'Perfil 1', 'VENDIDO', 7, 7, '2026-02-23 18:35:42', '2026-01-24 18:35:42', '2026-01-24 18:34:25');
INSERT INTO `cuenta_perfiles` VALUES (34, 14, 'Perfil 1', 'VENDIDO', 7, 9, '2026-02-23 18:42:52', '2026-01-24 18:42:52', '2026-01-24 18:34:51');
INSERT INTO `cuenta_perfiles` VALUES (35, 14, 'Perfil 2', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-24 18:34:51');
INSERT INTO `cuenta_perfiles` VALUES (36, 14, 'Perfil 3', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-24 18:34:51');
INSERT INTO `cuenta_perfiles` VALUES (37, 14, 'Perfil 4', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-24 18:34:51');
INSERT INTO `cuenta_perfiles` VALUES (38, 14, 'Perfil 5', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-24 18:34:51');
INSERT INTO `cuenta_perfiles` VALUES (39, 15, 'Perfil 1', 'VENDIDO', 7, 12, '2026-02-25 20:54:59', '2026-01-26 20:54:59', '2026-01-26 20:54:32');
INSERT INTO `cuenta_perfiles` VALUES (40, 15, 'Perfil 2', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-26 20:54:32');
INSERT INTO `cuenta_perfiles` VALUES (41, 15, 'Perfil 3', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-26 20:54:32');
INSERT INTO `cuenta_perfiles` VALUES (42, 15, 'Perfil 4', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-26 20:54:32');
INSERT INTO `cuenta_perfiles` VALUES (43, 15, 'Perfil 5', 'DISPONIBLE', NULL, NULL, NULL, NULL, '2026-01-26 20:54:32');

-- ----------------------------
-- Table structure for cuentas
-- ----------------------------
DROP TABLE IF EXISTS `cuentas`;
CREATE TABLE `cuentas`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `login_user` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `login_pass` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `max_perfiles` int NOT NULL DEFAULT 1,
  `estado` enum('DISPONIBLE','VENDIDA_COMPLETA','BLOQUEADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DISPONIBLE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `producto_id`(`producto_id` ASC) USING BTREE,
  INDEX `estado`(`estado` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of cuentas
-- ----------------------------
INSERT INTO `cuentas` VALUES (1, 1, 'otracosa@gmail.com', '123', '12', 5, 'DISPONIBLE', '2025-12-31 11:01:40');
INSERT INTO `cuentas` VALUES (2, 2, 'max@gmail.com', '123', '1234', 4, 'DISPONIBLE', '2026-01-09 22:12:14');
INSERT INTO `cuentas` VALUES (3, 32, 'youprueba@gmail.com', '123', NULL, 1, 'DISPONIBLE', '2026-01-20 08:27:05');
INSERT INTO `cuentas` VALUES (4, 31, 'VAXZ-ASDC-ASCW-ASCC', 'SIN CONTRA', NULL, 1, 'DISPONIBLE', '2026-01-20 08:27:41');
INSERT INTO `cuentas` VALUES (5, 30, 'vix@gmail.com', '123', '123', 4, 'DISPONIBLE', '2026-01-20 08:28:12');
INSERT INTO `cuentas` VALUES (6, 28, 'surf@gmail.com', '123123', NULL, 1, 'DISPONIBLE', '2026-01-20 08:28:40');
INSERT INTO `cuentas` VALUES (7, 4, 'spoty@gmail.com', '123', NULL, 1, 'DISPONIBLE', '2026-01-20 08:29:00');
INSERT INTO `cuentas` VALUES (8, 2, 'habvoassod@gmail.com', '123124', NULL, 4, 'DISPONIBLE', '2026-01-24 14:09:47');
INSERT INTO `cuentas` VALUES (9, 2, 'otrademax@gmail.com', '123412', NULL, 5, 'DISPONIBLE', '2026-01-24 15:46:22');
INSERT INTO `cuentas` VALUES (10, 2, 'masdafsa@gmail.com', '123', NULL, 1, 'DISPONIBLE', '2026-01-24 15:51:19');
INSERT INTO `cuentas` VALUES (11, 34, 'dfasfa@gmail.com', '123', NULL, 1, 'DISPONIBLE', '2026-01-24 15:56:14');
INSERT INTO `cuentas` VALUES (12, 2, 'sdfsfsdghh@gmail.com', '123', NULL, 4, 'DISPONIBLE', '2026-01-24 15:56:41');
INSERT INTO `cuentas` VALUES (13, 34, 'maxpruevba@gmail.com', '12341', NULL, 1, 'DISPONIBLE', '2026-01-24 18:34:25');
INSERT INTO `cuentas` VALUES (14, 2, 'maxperfilmqmq@gmail.com', '123', NULL, 5, 'DISPONIBLE', '2026-01-24 18:34:51');
INSERT INTO `cuentas` VALUES (15, 35, 'comunicate con el proveedor', '9612341234', NULL, 5, 'DISPONIBLE', '2026-01-26 20:54:32');

-- ----------------------------
-- Table structure for metodos_pago
-- ----------------------------
DROP TABLE IF EXISTS `metodos_pago`;
CREATE TABLE `metodos_pago`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `icono` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '#12aaff',
  `comision_porcentaje` decimal(5, 2) NOT NULL DEFAULT 0.00,
  `comision_fija` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `comision` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0%',
  `tiempo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'Inmediato',
  `imagen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `instrucciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `clave`(`clave` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of metodos_pago
-- ----------------------------
INSERT INTO `metodos_pago` VALUES (1, 'yape', 'Yape', 'Recarga desde Yape', 'fas fa-mobile-alt', '#7C3AED', 0.00, 0.00, '0%', 'Instantáneo', 'uploads/metodos/metodo_40718154c232fed40feda385.png', 'Envía el monto al número 9xx xxx xxx y sube la captura del pago.', 1, 1);
INSERT INTO `metodos_pago` VALUES (2, 'plin', 'Plin', 'Recarga por Plin', 'fas fa-wallet', '#10B981', 0.00, 0.00, '0%', 'Instantáneo', 'assets/img/metodos/plin.png', 'Envía el monto al número 9xx xxx xxx y adjunta el voucher.', 1, 2);
INSERT INTO `metodos_pago` VALUES (3, 'binance', 'Binance', 'Pagos internacionales', 'fab fa-paypal', '#0EA5E9', 0.00, 0.30, '0', 'Hasta 24h', 'assets/img/metodos/paypal.png', 'Envía el pago a tu-correo@paypal.com y sube el comprobante.', 1, 3);
INSERT INTO `metodos_pago` VALUES (4, 'chupadas', 'Chupadita', 'Pago oral', '', '#12aaff', 0.00, 0.00, '0%', 'Inmediato', NULL, NULL, 0, 0);

-- ----------------------------
-- Table structure for productos
-- ----------------------------
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `precio` decimal(10, 2) NOT NULL,
  `categoria_id` int NULL DEFAULT NULL,
  `imagen_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `stock` int NULL DEFAULT 1,
  `destacado` tinyint(1) NULL DEFAULT 0,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `contraseña` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tipo_venta` enum('PERFIL','CUENTA_COMPLETA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PERFIL',
  `duracion_dias` int NOT NULL DEFAULT 30,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `categoria_id`(`categoria_id` ASC) USING BTREE,
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 36 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of productos
-- ----------------------------
INSERT INTO `productos` VALUES (1, 'Netflix 4K Premium', '4 pantallas simultáneas, contenido 4K', 9.99, 1, 'uploads/productos/prod_0557220aade1a8eb39943966.png', 50, 1, 1, '2025-12-07 06:03:03', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (2, 'HBO MAX PERFIL', 'Series exclusivas y estrenos', 8.99, 1, 'uploads/productos/prod_e911917c3bf653da1e0ca8cd.jpg', 6, 1, 1, '2025-12-07 06:03:03', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (3, 'Disney+', 'Disney, Marvel, Star Wars, Pixar', 11.99, 1, 'uploads/productos/prod_068af2a597ec4bba0ffc0811.jpg', 29, 1, 1, '2025-12-07 06:03:03', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (4, 'Spotify Premium', 'Música sin anuncios, modo offline', 9.99, 3, 'uploads/productos/prod_0f0b85f0d502aba785972bf3.jpg', 1, 1, 1, '2025-12-07 06:03:03', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (5, 'Xbox Game Pass', '+100 juegos en PC y consola', 14.99, 4, 'assets/img/productos/xbox.png', 25, 0, 1, '2025-12-07 06:03:03', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (6, 'SETSP', 'ADADA', 11.99, 4, '', 0, 0, 0, '2025-12-26 11:16:36', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (7, 'Adobe Creative Cloud', '', 10.00, 6, 'uploads/productos/prod_8228f7c19641adad8e95b5ad.jpg', 0, 0, 1, '2026-01-20 08:13:18', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (8, 'Autodesck', '', 10.00, 6, 'uploads/productos/prod_3cb24ecc05f121518a626a29.jpg', 0, 0, 1, '2026-01-20 08:13:51', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (9, 'Canva', '', 5.00, 6, 'uploads/productos/prod_fb68ec2c1f8af47003cc14f1.jpg', 0, 0, 1, '2026-01-20 08:14:09', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (10, 'Canva 1 año', '', 15.00, NULL, 'uploads/productos/prod_c8e9f0ff12b7106b256d3d58.jpg', 0, 0, 1, '2026-01-20 08:14:39', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (11, 'Canva 2 años', '', 25.00, 6, 'uploads/productos/prod_6cf8269912f733402f7e12d5.jpg', 0, 0, 1, '2026-01-20 08:14:58', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (12, 'Chat GPT', '', 12.00, 5, 'uploads/productos/prod_3a8c9a49cb2bed2c67af03f6.jpg', 0, 0, 1, '2026-01-20 08:15:23', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (13, 'Crunchyrroll', '', 4.00, 2, 'uploads/productos/prod_554f3189cc327e893d9f3360.jpg', 0, 0, 1, '2026-01-20 08:15:45', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (14, 'Deezer', '', 10.00, 3, 'uploads/productos/prod_ea5bc237b3b762711fee012f.jpg', 0, 0, 1, '2026-01-20 08:16:05', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (15, 'DGO', '', 10.00, 4, 'uploads/productos/prod_5bc7cf267d5cba9886b292f3.jpg', 0, 0, 1, '2026-01-20 08:16:25', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (16, 'DGO + LIGA 1 MAX', '', 20.00, 4, 'uploads/productos/prod_630e267104ae1d0b27199098.jpg', 0, 0, 1, '2026-01-20 08:16:51', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (17, 'ESET', '', 10.00, 6, 'uploads/productos/prod_7cdbbad2d9f1a7af78ec814e.jpg', 0, 0, 1, '2026-01-20 08:17:14', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (18, 'Express VPN', '', 5.00, 6, 'uploads/productos/prod_92fb2c344e8f7e9cc8bb0b62.jpg', 0, 0, 1, '2026-01-20 08:17:31', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (19, 'Gemini PRO', '', 10.00, 5, 'uploads/productos/prod_3a653e97f89261ac8749a8cf.jpg', 0, 0, 1, '2026-01-20 08:18:09', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (20, 'IPTV', '', 5.00, NULL, 'uploads/productos/prod_bb261eeba6c5699d54744987.jpg', 0, 0, 1, '2026-01-20 08:18:24', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (21, 'Kaspersky', '', 10.00, 6, 'uploads/productos/prod_878cfe796a6256dc2eee9dd5.jpg', 0, 0, 1, '2026-01-20 08:18:43', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (22, 'Macfee', '', 5.00, NULL, 'uploads/productos/prod_f6c4f06a153541f15d1f1b31.jpg', 0, 0, 1, '2026-01-20 08:19:01', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (23, 'Nord VPN', '', 6.00, 6, 'uploads/productos/prod_1538886c8f9d5fe8eaba6708.jpg', 0, 0, 1, '2026-01-20 08:19:25', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (24, 'Oiffice 365', '', 12.00, NULL, 'uploads/productos/prod_9184fe10e9920a805e23afd3.jpg', 0, 0, 1, '2026-01-20 08:19:49', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (25, 'Perplexity', '', 12.00, 5, 'uploads/productos/prod_066b601b9c7e469088a519d5.jpg', 0, 0, 1, '2026-01-20 08:20:07', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (26, 'Prime Video', '', 5.00, 2, 'uploads/productos/prod_34a01caa261c9966244ac44b.jpg', 0, 0, 1, '2026-01-20 08:20:25', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (27, 'Pure VPN', '', 6.00, NULL, 'uploads/productos/prod_b96547b981496460b54ee15d.jpg', 0, 0, 1, '2026-01-20 08:20:41', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (28, 'Surf Shack VPN', '', 8.00, 6, 'uploads/productos/prod_7e3cca32a150bb9940b04c5a.jpg', 1, 0, 1, '2026-01-20 08:21:58', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (29, 'Tidal Music', '', 6.00, 3, 'uploads/productos/prod_4966d2dac691b52ef91c26ba.jpg', 0, 0, 1, '2026-01-20 08:22:18', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (30, 'Vix', '', 5.00, 2, 'uploads/productos/prod_4fe56cb77f705c8e0e982a60.jpg', 4, 0, 1, '2026-01-20 08:22:49', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (31, 'Windows 11 PRO', '', 15.00, 6, 'uploads/productos/prod_2c9de059de9b21953490741d.jpg', 1, 0, 1, '2026-01-20 08:23:10', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (32, 'YouTube Premiun', '', 8.00, 3, 'uploads/productos/prod_a2148ce8c50e133f7c7c0a63.jpg', 1, 0, 1, '2026-01-20 08:23:28', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (33, 'porno', 'adadasda', 10.00, 1, 'uploads/productos/prod_06df3c03aaf6dec3d0fc9e2d.jpg', 0, 0, 0, '2026-01-24 14:12:48', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (34, 'HBO MAX Completo', '', 20.00, 1, 'uploads/productos/prod_b75ee843e6d17c632680ece0.jpg', 1, 0, 1, '2026-01-24 15:54:40', NULL, 'PERFIL', 30);
INSERT INTO `productos` VALUES (35, 'vix a pedido', '', 10.00, 1, 'uploads/productos/prod_62b763d57676b446e7f5bd40.png', 5, 0, 1, '2026-01-26 20:52:58', NULL, 'PERFIL', 30);

-- ----------------------------
-- Table structure for recargas
-- ----------------------------
DROP TABLE IF EXISTS `recargas`;
CREATE TABLE `recargas`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `metodo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `monto` decimal(10, 2) NOT NULL,
  `comision` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `comprobante_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp,
  `fecha_aprobacion` timestamp NULL DEFAULT NULL,
  `aprobado_por` int NULL DEFAULT NULL,
  `rechazo_motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `usuario_id`(`usuario_id` ASC) USING BTREE,
  INDEX `aprobado_por`(`aprobado_por` ASC) USING BTREE,
  CONSTRAINT `recargas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `recargas_ibfk_2` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of recargas
-- ----------------------------
INSERT INTO `recargas` VALUES (1, 7, 'yape', 50.00, 0.00, 'assets/comprobantes/recarga_1_1767122823.jfif', 'aprobada', '2025-12-30 14:27:03', '2025-12-30 14:27:52', 8, NULL);
INSERT INTO `recargas` VALUES (2, 7, 'yape', 200.00, 0.00, 'assets/comprobantes/recarga_2_1767197015.jfif', 'aprobada', '2025-12-31 11:03:35', '2025-12-31 11:05:21', 8, NULL);
INSERT INTO `recargas` VALUES (3, 7, 'plin', 500.00, 0.00, 'assets/comprobantes/recarga_3_1768014490.jpg', 'aprobada', '2026-01-09 22:08:10', '2026-01-09 22:10:04', 8, NULL);
INSERT INTO `recargas` VALUES (4, 7, 'yape', 500.00, 0.00, 'assets/comprobantes/recarga_4_1768020875.jpg', 'aprobada', '2026-01-09 23:54:35', '2026-01-19 16:00:47', 8, NULL);
INSERT INTO `recargas` VALUES (5, 7, 'yape', 500.00, 0.00, 'assets/comprobantes/recarga_5_1769178187.png', 'aprobada', '2026-01-23 09:23:07', '2026-01-23 09:23:17', 8, NULL);
INSERT INTO `recargas` VALUES (6, 7, 'yape', 1000.00, 0.00, 'assets/comprobantes/recarga_6_1769201351.png', 'aprobada', '2026-01-23 15:49:11', '2026-01-23 15:49:27', 8, NULL);
INSERT INTO `recargas` VALUES (7, 7, 'yape', 50.00, 0.00, 'assets/comprobantes/recarga_7_1769204558.png', 'rechazada', '2026-01-23 16:42:38', '2026-01-23 16:43:10', 8, 'es un sonso se cree vivo');
INSERT INTO `recargas` VALUES (8, 10, 'yape', 50.00, 0.00, 'assets/comprobantes/recarga_8_1769281709.png', 'aprobada', '2026-01-24 14:08:29', '2026-01-24 14:08:51', 8, NULL);
INSERT INTO `recargas` VALUES (9, 8, 'yape', 500.00, 0.00, 'assets/comprobantes/recarga_9_1769479152.png', 'aprobada', '2026-01-26 20:59:12', '2026-01-26 20:59:39', 8, NULL);

-- ----------------------------
-- Table structure for recuperaciones_pendientes
-- ----------------------------
DROP TABLE IF EXISTS `recuperaciones_pendientes`;
CREATE TABLE `recuperaciones_pendientes`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `whatsapp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_usuario` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enlace` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('pendiente','enviado','expirado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'pendiente',
  `fecha_solicitud` datetime NULL DEFAULT current_timestamp,
  `fecha_envio` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `token`(`token` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of recuperaciones_pendientes
-- ----------------------------
INSERT INTO `recuperaciones_pendientes` VALUES (3, 7, '+51937401236', 'maminiti', 'e7d6598eb571bb0bd030e04e3c20050e5f712755832048c045c7a82200647beb', 'http://localhost/monkydos/restablecer.php?token=e7d6598eb571bb0bd030e04e3c20050e5f712755832048c045c7a82200647beb', 'enviado', '2026-01-24 20:07:51', '2026-01-24 20:38:04');
INSERT INTO `recuperaciones_pendientes` VALUES (4, 7, '+51937401236', 'maminiti', 'ad932281906f003a7d523fd08292194b0827bd7d601ecaecdf5db6466b60e249', 'http://localhost/monkydos/restablecer.php?token=ad932281906f003a7d523fd08292194b0827bd7d601ecaecdf5db6466b60e249', 'enviado', '2026-01-24 20:26:03', '2026-01-24 20:37:34');
INSERT INTO `recuperaciones_pendientes` VALUES (5, 7, '+51937401236', 'maminiti', 'debaa7e7db01995803f27e210dd9440c743692f0b463cab4091dac004c51e7a1', 'http://localhost/monkydos/restablecer.php?token=debaa7e7db01995803f27e210dd9440c743692f0b463cab4091dac004c51e7a1', 'enviado', '2026-01-26 09:41:47', '2026-01-26 09:47:49');
INSERT INTO `recuperaciones_pendientes` VALUES (6, 7, '+51937401236', 'maminiti', 'f463fe914236d5c95d241f651f58f6b0bc8902ec156243cc26a3bbd2512854de', 'http://localhost/monkydos/restablecer.php?token=f463fe914236d5c95d241f651f58f6b0bc8902ec156243cc26a3bbd2512854de', 'pendiente', '2026-01-26 21:01:58', NULL);

-- ----------------------------
-- Table structure for ticket_auto_messages
-- ----------------------------
DROP TABLE IF EXISTS `ticket_auto_messages`;
CREATE TABLE `ticket_auto_messages`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('creacion','respuesta','cierre') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'creacion',
  `activo` tinyint(1) NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ticket_auto_messages
-- ----------------------------
INSERT INTO `ticket_auto_messages` VALUES (1, '✅ Ticket creado. Hola {nombre}, nos comunicaremos contigo por WhatsApp ({whatsapp}) en las próximas 24 horas. Tu ticket #{ticket_id} ha sido registrado.', 'creacion', 1, '2026-01-23 10:32:54');

-- ----------------------------
-- Table structure for ticket_messages
-- ----------------------------
DROP TABLE IF EXISTS `ticket_messages`;
CREATE TABLE `ticket_messages`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `sender_role` enum('USER','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sender_id` int NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `ticket_id`(`ticket_id` ASC) USING BTREE,
  INDEX `sender_role`(`sender_role` ASC) USING BTREE,
  INDEX `sender_id`(`sender_id` ASC) USING BTREE,
  CONSTRAINT `fk_ticket_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ticket_messages
-- ----------------------------
INSERT INTO `ticket_messages` VALUES (1, 1, 'USER', 7, 'mi cuenta dejo de funcionar', '2026-01-20 09:54:02');
INSERT INTO `ticket_messages` VALUES (2, 2, 'USER', 9, 'no se que paso que dejo de entrar', '2026-01-23 12:28:06');
INSERT INTO `ticket_messages` VALUES (3, 2, '', 0, '✅ Ticket #2 creado exitosamente. Hola anal, nos comunicaremos contigo por WhatsApp (+51964279873) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.', '2026-01-23 12:28:06');
INSERT INTO `ticket_messages` VALUES (4, 2, 'ADMIN', 8, 'oño', '2026-01-23 13:45:02');
INSERT INTO `ticket_messages` VALUES (5, 3, 'USER', 7, '<<<zzzzz', '2026-01-23 14:55:50');
INSERT INTO `ticket_messages` VALUES (6, 3, '', 0, '✅ Ticket #3 creado exitosamente. Hola maminiti, nos comunicaremos contigo por WhatsApp (+51937401236) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.', '2026-01-23 14:55:50');
INSERT INTO `ticket_messages` VALUES (7, 4, 'USER', 10, 'estafador de mrd dame mi plata', '2026-01-24 14:10:41');
INSERT INTO `ticket_messages` VALUES (8, 4, '', 0, '✅ Ticket #4 creado exitosamente. Hola monochoro, nos comunicaremos contigo por WhatsApp (+51952261472) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.', '2026-01-24 14:10:41');
INSERT INTO `ticket_messages` VALUES (9, 4, 'ADMIN', 8, 'hola andino de mrd ahorita te atiendo', '2026-01-24 14:11:22');
INSERT INTO `ticket_messages` VALUES (10, 4, 'ADMIN', 8, 'oño', '2026-01-24 15:48:56');
INSERT INTO `ticket_messages` VALUES (11, 5, 'USER', 7, 'oe ya p ctmr mono ratero', '2026-01-26 20:56:09');
INSERT INTO `ticket_messages` VALUES (12, 5, '', 0, '✅ Ticket #5 creado exitosamente. Hola maminiti, nos comunicaremos contigo por WhatsApp (+51937401236) en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.', '2026-01-26 20:56:09');

-- ----------------------------
-- Table structure for ticket_respuestas_old_20251223_091517
-- ----------------------------
DROP TABLE IF EXISTS `ticket_respuestas_old_20251223_091517`;
CREATE TABLE `ticket_respuestas_old_20251223_091517`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `es_admin` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `ticket_id`(`ticket_id` ASC) USING BTREE,
  INDEX `usuario_id`(`usuario_id` ASC) USING BTREE,
  CONSTRAINT `ticket_respuestas_old_20251223_091517_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of ticket_respuestas_old_20251223_091517
-- ----------------------------

-- ----------------------------
-- Table structure for tickets
-- ----------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `asunto` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prioridad` enum('baja','media','alta') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'media',
  `estado` enum('abierto','en_proceso','cerrado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'abierto',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  `last_reply_role` enum('USER','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_reply_at` datetime NULL DEFAULT NULL,
  `cerrado_por` int NULL DEFAULT NULL,
  `cerrado_en` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `usuario_id`(`usuario_id` ASC) USING BTREE,
  INDEX `estado`(`estado` ASC) USING BTREE,
  INDEX `prioridad`(`prioridad` ASC) USING BTREE,
  INDEX `fk_tickets_cerrado_por`(`cerrado_por` ASC) USING BTREE,
  CONSTRAINT `fk_tickets_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of tickets
-- ----------------------------
INSERT INTO `tickets` VALUES (1, 7, 'mi cuenta no entra', 'alta', 'abierto', '2026-01-20 09:54:02', '2026-01-20 09:54:02', NULL, NULL, NULL, NULL);
INSERT INTO `tickets` VALUES (2, 9, 'mi cuenta no entra', 'alta', 'abierto', '2026-01-23 12:28:06', '2026-01-23 12:28:06', NULL, NULL, NULL, NULL);
INSERT INTO `tickets` VALUES (3, 7, 'ya fue ya no quiero seguir', 'media', 'abierto', '2026-01-23 14:55:50', '2026-01-23 14:55:50', NULL, NULL, NULL, NULL);
INSERT INTO `tickets` VALUES (4, 10, 'mi cuenta no entra', 'media', 'abierto', '2026-01-24 14:10:41', '2026-01-24 14:10:41', NULL, NULL, NULL, NULL);
INSERT INTO `tickets` VALUES (5, 7, 'oe mano mi pedido p', 'alta', 'abierto', '2026-01-26 20:56:09', '2026-01-26 20:56:09', NULL, NULL, NULL, NULL);

-- ----------------------------
-- Table structure for tickets_old_20251223_091517
-- ----------------------------
DROP TABLE IF EXISTS `tickets_old_20251223_091517`;
CREATE TABLE `tickets_old_20251223_091517`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `asunto` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('abierto','en proceso','cerrado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'abierto',
  `prioridad` enum('baja','media','alta') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'media',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `usuario_id`(`usuario_id` ASC) USING BTREE,
  CONSTRAINT `tickets_old_20251223_091517_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of tickets_old_20251223_091517
-- ----------------------------

-- ----------------------------
-- Table structure for usuarios
-- ----------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `whatsapp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('user','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'user',
  `saldo` decimal(10, 2) NULL DEFAULT 0.00,
  `estado` enum('activo','suspendido','eliminado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activo',
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `email`(`email` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of usuarios
-- ----------------------------
INSERT INTO `usuarios` VALUES (1, 'Administrador', 'admin@monkeystraming.com', NULL, '$2y$10$YourHashedPasswordHere', 'admin', 1000.00, 'activo', NULL, '2025-12-07 06:03:03', '2026-01-07 21:58:39');
INSERT INTO `usuarios` VALUES (2, 'cash', 'casumancilla@gmail.com', NULL, '$2y$10$V64mhtQiwVmS2QKtOEVmkOyIH32NaW41dODhW55WT.jZYRQkd3HRe', 'user', 0.00, '', NULL, '2025-12-08 01:43:28', '2026-01-07 21:58:43');
INSERT INTO `usuarios` VALUES (3, 'cashs', 'otriro5102@gmail.com', NULL, '$2y$10$vIQ83erWZkBCnHFj/4GAtuF61XkuOPN2nqs/ZZ6eluvnU27Aa5P9i', 'user', 0.00, '', NULL, '2025-12-08 01:44:23', '2026-01-07 21:58:47');
INSERT INTO `usuarios` VALUES (4, 'casuclash', 'casuclash@gmail.com', NULL, '$2y$10$uF7iVLxBOUfarHXRvZ4.2OWG8zZ2dcZMSbNeTJBAv/nf6MEFDFJB6', 'user', 0.00, 'activo', NULL, '2025-12-08 02:09:45', '2025-12-08 02:09:45');
INSERT INTO `usuarios` VALUES (5, 'demo', 'demo@gmail.com', NULL, '$2y$10$aSS4h5Q/J2HNn2PHcGlMy.OrtDmF1dR/ddC.1fI6QfnAtlTqtOltq', 'user', 0.00, 'activo', NULL, '2025-12-08 17:51:03', '2025-12-08 17:51:03');
INSERT INTO `usuarios` VALUES (6, 'demo2', 'demo2@gmail.com', NULL, '$2y$10$NYAzARAmuGEUdieV8EDy2.fbxhTzLl8Sui/B5JUrC2maOBVZ9ZL9e', 'user', 0.00, 'activo', NULL, '2025-12-17 09:07:51', '2025-12-17 09:07:51');
INSERT INTO `usuarios` VALUES (7, 'maminiti', 'demo12@gmil.com', '+51937401236', '$2y$10$Pk.gxT33miTF3CbbYMJWPuniAI2fQN.NKiAtmoqd/fbCTZ.zSTlQi', 'user', 2550.25, 'activo', NULL, '2025-12-23 09:34:40', '2026-01-26 20:54:59');
INSERT INTO `usuarios` VALUES (8, 'casuty', 'casuty@gmail.com', NULL, '$2y$10$l66I02YSu3hJ78vw2XRA.u7d8GVUiDA9jbDCroqlXFzjuvUoQ/inq', 'admin', 525.00, 'activo', NULL, '2025-12-23 14:26:04', '2026-01-26 20:59:39');
INSERT INTO `usuarios` VALUES (9, 'anal', 'anal@gmail.com', '+51964279873', '$2y$10$G31HpL1uPgAsi/EtleQuce46QMnDJjCxMMnVhqN3i2Mf3BSlopijG', 'user', 0.00, 'activo', NULL, '2026-01-23 12:04:44', '2026-01-23 12:04:44');
INSERT INTO `usuarios` VALUES (10, 'monochoro', 'monochoro@gmail.com', '+51952261472', '$2y$10$Tll5c4uVXLzKh1/Q3wixheIPz7XW///Po7ezKI1tRjIziLGiV/TV2', 'user', 14.04, 'activo', NULL, '2026-01-24 14:06:55', '2026-01-24 15:43:51');

SET FOREIGN_KEY_CHECKS = 1;
