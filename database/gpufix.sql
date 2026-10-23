-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: if0_43068954_gpufix
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
-- Table structure for table `carrito`
--

DROP TABLE IF EXISTS `carrito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carrito` (
  `id_carrito` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_carrito`),
  UNIQUE KEY `uq_carrito_cliente` (`id_cliente`),
  CONSTRAINT `fk_carrito_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carrito`
--

LOCK TABLES `carrito` WRITE;
/*!40000 ALTER TABLE `carrito` DISABLE KEYS */;
INSERT INTO `carrito` VALUES (1,11,'2026-10-23 01:02:03','2026-10-23 01:02:03');
/*!40000 ALTER TABLE `carrito` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cliente`
--

DROP TABLE IF EXISTS `cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cliente` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `id_correo` int(11) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_cliente_correo` (`id_correo`),
  CONSTRAINT `fk_cliente_usuarios` FOREIGN KEY (`id_correo`) REFERENCES `usuarios` (`id_correo`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente`
--

LOCK TABLES `cliente` WRITE;
/*!40000 ALTER TABLE `cliente` DISABLE KEYS */;
INSERT INTO `cliente` VALUES (11,11,NULL);
/*!40000 ALTER TABLE `cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `componente`
--

DROP TABLE IF EXISTS `componente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `componente` (
  `id_componente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id_componente`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `componente`
--

LOCK TABLES `componente` WRITE;
/*!40000 ALTER TABLE `componente` DISABLE KEYS */;
/*!40000 ALTER TABLE `componente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_carrito`
--

DROP TABLE IF EXISTS `detalle_carrito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_carrito` (
  `id_detalle_carrito` int(11) NOT NULL AUTO_INCREMENT,
  `id_carrito` int(11) NOT NULL,
  `id_gpu` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_detalle_carrito`),
  UNIQUE KEY `uq_detalle_carrito_gpu` (`id_carrito`,`id_gpu`),
  KEY `ix_detalle_carrito_gpu` (`id_gpu`),
  CONSTRAINT `fk_detalle_carrito_carrito` FOREIGN KEY (`id_carrito`) REFERENCES `carrito` (`id_carrito`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_carrito_gpu` FOREIGN KEY (`id_gpu`) REFERENCES `gpu` (`id_gpu`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_carrito`
--

LOCK TABLES `detalle_carrito` WRITE;
/*!40000 ALTER TABLE `detalle_carrito` DISABLE KEYS */;
INSERT INTO `detalle_carrito` VALUES (5,1,10,1);
/*!40000 ALTER TABLE `detalle_carrito` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compra`
--

DROP TABLE IF EXISTS `detalle_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compra` (
  `id_detalle_compra` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_compra` int(11) NOT NULL,
  `id_gpu` int(11) DEFAULT NULL,
  `id_componente` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_detalle_compra`),
  KEY `fk_detalle_compra_orden` (`id_orden_compra`),
  KEY `fk_detalle_compra_gpu` (`id_gpu`),
  KEY `fk_detalle_compra_componente` (`id_componente`),
  CONSTRAINT `fk_detalle_compra_componente` FOREIGN KEY (`id_componente`) REFERENCES `componente` (`id_componente`),
  CONSTRAINT `fk_detalle_compra_gpu` FOREIGN KEY (`id_gpu`) REFERENCES `gpu` (`id_gpu`),
  CONSTRAINT `fk_detalle_compra_orden` FOREIGN KEY (`id_orden_compra`) REFERENCES `orden_compra` (`id_orden_compra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compra`
--

LOCK TABLES `detalle_compra` WRITE;
/*!40000 ALTER TABLE `detalle_compra` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_factura`
--

DROP TABLE IF EXISTS `detalle_factura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_factura` (
  `id_detalle_factura` int(11) NOT NULL AUTO_INCREMENT,
  `id_factura` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id_detalle_factura`),
  KEY `fk_detalle_factura_factura` (`id_factura`),
  CONSTRAINT `fk_detalle_factura_factura` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_factura`
--

LOCK TABLES `detalle_factura` WRITE;
/*!40000 ALTER TABLE `detalle_factura` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_factura` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_presupuesto`
--

DROP TABLE IF EXISTS `detalle_presupuesto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_presupuesto` (
  `id_detalle_presupuesto` int(11) NOT NULL AUTO_INCREMENT,
  `id_presupuesto` int(11) NOT NULL,
  `id_componente` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id_detalle_presupuesto`),
  KEY `fk_det_presupuesto_presupuesto` (`id_presupuesto`),
  KEY `fk_det_presupuesto_componente` (`id_componente`),
  CONSTRAINT `fk_det_presupuesto_componente` FOREIGN KEY (`id_componente`) REFERENCES `componente` (`id_componente`),
  CONSTRAINT `fk_det_presupuesto_presupuesto` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuesto` (`id_presupuesto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_presupuesto`
--

LOCK TABLES `detalle_presupuesto` WRITE;
/*!40000 ALTER TABLE `detalle_presupuesto` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_presupuesto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_reparacion`
--

DROP TABLE IF EXISTS `detalle_reparacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_reparacion` (
  `id_detalle_reparacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_reparacion` int(11) NOT NULL,
  `id_componente` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  PRIMARY KEY (`id_detalle_reparacion`),
  KEY `fk_det_reparacion_reparacion` (`id_reparacion`),
  KEY `fk_det_reparacion_componente` (`id_componente`),
  CONSTRAINT `fk_det_reparacion_componente` FOREIGN KEY (`id_componente`) REFERENCES `componente` (`id_componente`),
  CONSTRAINT `fk_det_reparacion_reparacion` FOREIGN KEY (`id_reparacion`) REFERENCES `reparacion` (`id_reparacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_reparacion`
--

LOCK TABLES `detalle_reparacion` WRITE;
/*!40000 ALTER TABLE `detalle_reparacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_reparacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_venta`
--

DROP TABLE IF EXISTS `detalle_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_venta` (
  `id_detalle_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_gpu_ventas` int(11) NOT NULL,
  `id_gpu` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id_detalle_venta`),
  KEY `fk_detalle_venta_venta` (`id_gpu_ventas`),
  KEY `fk_detalle_venta_gpu` (`id_gpu`),
  CONSTRAINT `fk_detalle_venta_gpu` FOREIGN KEY (`id_gpu`) REFERENCES `gpu` (`id_gpu`),
  CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`id_gpu_ventas`) REFERENCES `gpu_ventas` (`id_gpu_ventas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_venta`
--

LOCK TABLES `detalle_venta` WRITE;
/*!40000 ALTER TABLE `detalle_venta` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_venta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `diagnostico`
--

DROP TABLE IF EXISTS `diagnostico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `diagnostico` (
  `id_diagnostico` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_reparacion` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `fecha_diagnostico` datetime NOT NULL DEFAULT current_timestamp(),
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id_diagnostico`),
  KEY `fk_diagnostico_orden` (`id_orden_reparacion`),
  KEY `fk_diagnostico_tecnico` (`id_tecnico`),
  CONSTRAINT `fk_diagnostico_orden` FOREIGN KEY (`id_orden_reparacion`) REFERENCES `orden_reparacion` (`id_orden_reparacion`),
  CONSTRAINT `fk_diagnostico_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnostico`
--

LOCK TABLES `diagnostico` WRITE;
/*!40000 ALTER TABLE `diagnostico` DISABLE KEYS */;
/*!40000 ALTER TABLE `diagnostico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empleado`
--

DROP TABLE IF EXISTS `empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empleado` (
  `id_empleado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `id_puesto` int(11) NOT NULL,
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `uq_empleado_dni` (`dni`),
  KEY `fk_empleado_puesto` (`id_puesto`),
  CONSTRAINT `fk_empleado_puesto` FOREIGN KEY (`id_puesto`) REFERENCES `puesto` (`id_puesto`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empleado`
--

LOCK TABLES `empleado` WRITE;
/*!40000 ALTER TABLE `empleado` DISABLE KEYS */;
/*!40000 ALTER TABLE `empleado` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `envios`
--

DROP TABLE IF EXISTS `envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `envios` (
  `id_envios` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_gpu_ventas` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` varchar(50) DEFAULT NULL,
  `direccion_envio` varchar(255) DEFAULT NULL,
  `codigo_seguimiento` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_envios`),
  KEY `fk_envios_cliente` (`id_cliente`),
  KEY `fk_envios_venta` (`id_gpu_ventas`),
  CONSTRAINT `fk_envios_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_envios_venta` FOREIGN KEY (`id_gpu_ventas`) REFERENCES `gpu_ventas` (`id_gpu_ventas`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `envios`
--

LOCK TABLES `envios` WRITE;
/*!40000 ALTER TABLE `envios` DISABLE KEYS */;
/*!40000 ALTER TABLE `envios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `factura`
--

DROP TABLE IF EXISTS `factura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factura` (
  `id_factura` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_gpu_ventas` int(11) DEFAULT NULL,
  `id_reparacion` int(11) DEFAULT NULL,
  `numero_factura` varchar(50) NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_factura`),
  UNIQUE KEY `uq_factura_numero` (`numero_factura`),
  UNIQUE KEY `uq_factura_venta` (`id_gpu_ventas`),
  UNIQUE KEY `uq_factura_reparacion` (`id_reparacion`),
  KEY `fk_factura_cliente` (`id_cliente`),
  CONSTRAINT `fk_factura_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_factura_reparacion` FOREIGN KEY (`id_reparacion`) REFERENCES `reparacion` (`id_reparacion`),
  CONSTRAINT `fk_factura_venta` FOREIGN KEY (`id_gpu_ventas`) REFERENCES `gpu_ventas` (`id_gpu_ventas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factura`
--

LOCK TABLES `factura` WRITE;
/*!40000 ALTER TABLE `factura` DISABLE KEYS */;
/*!40000 ALTER TABLE `factura` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gpu`
--

DROP TABLE IF EXISTS `gpu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gpu` (
  `id_gpu` int(11) NOT NULL AUTO_INCREMENT,
  `marca` varchar(100) NOT NULL,
  `modelo` varchar(150) NOT NULL,
  `precio_especial` decimal(14,2) DEFAULT NULL,
  `especificaciones` text DEFAULT NULL,
  `url_referencia` varchar(1000) DEFAULT NULL,
  `pagina` varchar(100) DEFAULT NULL,
  `imagen` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_gpu`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gpu`
--

LOCK TABLES `gpu` WRITE;
/*!40000 ALTER TABLE `gpu` DISABLE KEYS */;
INSERT INTO `gpu` VALUES (1,'Sapphire','Radeon RX 7600 8GB PULSE OC',688210.91,'GPU: AMD Radeon RX 7600\nArquitectura: RDNA 3\nProcesadores de flujo: 2048\nMemoria: 8 GB GDDR6, bus de 128 bits, 18 Gbps\nInterfaz: PCI Express 4.0 x8\nSalidas: 1 x HDMI 2.1 y 3 x DisplayPort 1.4\nFuente recomendada: 550 W\nRefrigeración: Sapphire PULSE','https://fullh4rd.com.ar/prod/26179/placa-de-video-radeon-rx-7600-8gb-sapphire-pulse-oc','rx7600pulse.php','img/rx7600.png'),(2,'Gigabyte','Radeon RX 6700 XT Gaming OC 12GB',619850.00,'GPU: AMD Radeon RX 6700 XT | Arquitectura: RDNA 2 | Unidades de cómputo: 40 | Procesadores de flujo: 2560 | Memoria: 12 GB GDDR6, bus de 192 bits, 16 Gbps | Ancho de banda: hasta 384 GB/s | Interfaz: PCI Express 4.0 x16 | Reloj: hasta 2622 MHz Boost / 2514 MHz Game | Refrigeración: GIGABYTE WINDFORCE 3X | Salidas: 2 x DisplayPort 1.4a y 2 x HDMI 2.1 | Conectores de alimentación: 1 x 8 pines + 1 x 6 pines | Consumo típico: 230 W | Fuente recomendada: 650 W | Dimensiones: 281 x 115 x 49 mm','https://libreopcion.com.ar/placa-de-video-gigabyte-rx-6700-xt-gaming-oc-12gb-P661212','rx6700xtgigabyte.php','img/rx6700xtgiga.png'),(3,'Gigabyte','Radeon RX 7700 XT Gaming OC 12GB',951982.07,'GPU: AMD Radeon RX 7700 XT\nArquitectura: RDNA 3\nProcesadores de flujo: 3456\nMemoria: 12 GB GDDR6, bus de 192 bits, 18 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia: hasta 2599 MHz (Boost) / 2276 MHz (Game)\nSalidas: 2 x DisplayPort 2.1 y 2 x HDMI 2.1\nFuente recomendada: 700 W\nDimensiones: 302 x 130 x 56 mm','https://fullh4rd.com.ar/prod/27146/video-radeon-rx-7700-xt-12gb-gigabyte-gaming-oc','rx7700xtgigabyte.php','img/rx7700xtgiga.png'),(4,'Gigabyte','Radeon RX 9070 Gaming OC 16GB',1430999.97,'GPU: AMD Radeon RX 9070\nArquitectura: RDNA 4\nProcesadores de flujo: 3584\nMemoria: 16 GB GDDR6, bus de 256 bits, 20 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia: hasta 2700 MHz (Boost) / 2210 MHz (Game)\nSalidas: 2 x DisplayPort 2.1a y 2 x HDMI 2.1b\nFuente recomendada: 750 W\nDimensiones: 288 x 132 x 50 mm','https://fullh4rd.com.ar/prod/29933/placa-de-video-radeon-rx-9070-16gb-gigabyte-gaming-oc','rx9070gigabyte.php','img/rx9070giga.png'),(5,'Gigabyte','GeForce RTX 3060 Windforce OC 12GB',838984.07,'GPU: NVIDIA GeForce RTX 3060\nNúcleos CUDA: 3584\nMemoria: 12 GB GDDR6, bus de 192 bits, 15 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia Boost: hasta 1792 MHz\nSalidas: 2 x DisplayPort 1.4a y 2 x HDMI 2.1\nFuente recomendada: 550 W\nDimensiones: 198 x 121 x 39 mm','https://fullh4rd.com.ar/prod/25594/placa-de-video-geforce-rtx-3060-12gb-gigabyte-windforce-oc','rtx3060gigabyte.php','img/rtx3060giga.png'),(6,'Gigabyte','GeForce RTX 4090 Gaming OC 24GB',4452882.00,'GPU: NVIDIA GeForce RTX 4090\nNúcleos CUDA: 16384\nMemoria: 24 GB GDDR6X, bus de 384 bits, 21 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia Boost: hasta 2535 MHz\nSalidas: 3 x DisplayPort 1.4a y 1 x HDMI 2.1a\nFuente recomendada: 1000 W\nDimensiones: 340 x 150,2 x 75,2 mm','https://addons.com.ar/producto/placa-de-video-gigabyte-rtx-4090-24gb-gaming-oc/','rtx4090gigabyte.php','img/rtx4090giga.png'),(7,'Gigabyte','GeForce RTX 5090 Gaming OC 32GB',7707938.65,'GPU: NVIDIA GeForce RTX 5090, arquitectura Blackwell\nNúcleos CUDA: 21760\nMemoria: 32 GB GDDR7, bus de 512 bits, 28 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia Boost: hasta 2550 MHz\nSalidas: 3 x DisplayPort 2.1b y 1 x HDMI 2.1b\nFuente recomendada: 1000 W\nDimensiones: 342 x 152 x 70 mm','https://fullh4rd.com.ar/prod/30262/video-geforce-rtx-5090-32gb-gigabyte-gaming-oc','rtx5090gigabyte.php','img/rtx5090giga.png'),(8,'Aorus','Radeon RX 9070 XT AORUS Elite OC 16GB',1705990.00,'GPU: AMD Radeon RX 9070 XT\nArquitectura: RDNA 4\nProcesadores de flujo: 4096\nMemoria: 16 GB GDDR6, bus de 256 bits, 20 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia: hasta 3100 MHz (Boost) / 2570 MHz (Game)\nSalidas: 2 x DisplayPort 2.1a y 2 x HDMI 2.1b\nFuente recomendada: 850 W\nDimensiones: 339 x 136 x 59 mm','https://fullh4rd.com.ar/prod/29914/placa-de-video-radeon-rx-9070-xt-16gb-gigabyte-aorus-elite-oc','rx9070xtaorus.php','img/rx9070tiaorus.png'),(9,'Aorus','GeForce RTX 5090 AORUS Master 32GB',7346606.97,'GPU: NVIDIA GeForce RTX 5090, arquitectura Blackwell\nNúcleos CUDA: 21760\nMemoria: 32 GB GDDR7, bus de 512 bits, 28 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia Boost: hasta 2655 MHz\nSalidas: 3 x DisplayPort 2.1b y 1 x HDMI 2.1b\nFuente recomendada: 1000 W\nDimensiones: 360 x 150 x 75 mm','https://fullh4rd.com.ar/prod/30261/placa-de-video-geforce-rtx-5090-32gb-gigabyte-aorus-master','rtx5090aorusmaster.php','img/rtx5090aorus.png'),(10,'Aorus','GeForce RTX 5090 AORUS Master Ice 32GB',6286612.00,'GPU: NVIDIA GeForce RTX 5090, arquitectura Blackwell\nNúcleos CUDA: 21760\nMemoria: 32 GB GDDR7, bus de 512 bits, 28 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia: no indicada en la ficha de Katech consultada\nSalidas: 3 x DisplayPort y 1 x HDMI\nFuente recomendada: 1000 W\nDimensiones: 360 x 150 x 75 mm\nExtras: pantalla LCD lateral, iluminación RGB y BIOS dual','https://fotopoint.com.ar/producto/placa-de-video-gigabyte-rtx-5090-aorus-master-ice-32gb-white-2/','rtx5090aorusmasterice.php','img/rtx5090Maorus.png'),(11,'Sapphire','Radeon RX 6700 XT Nitro+ 12GB',550000.00,'GPU: AMD Radeon RX 6700 XT\nArquitectura: RDNA 2\nProcesadores de flujo: 2560\nMemoria: 12 GB GDDR6, bus de 192 bits, 16 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia: hasta 2622 MHz (Boost) / 2548 MHz (Game)\nSalidas: 1 x HDMI 2.1 y 3 x DisplayPort 1.4\nFuente recomendada: 650 W\nDimensiones: 310,05 x 130,75 x 51,4 mm','https://www.compu-santafe.com.ar/producto/40851/placa-de-video-pci-e-radeon-rx-6700-xt-de-12gb-gddr6-nitro-11306-01-20g-sapphire','rx6700xtsapphire.php','img/rx6700xtsapphire.png'),(12,'Sapphire','Radeon RX 7700 XT PULSE 12GB',861098.08,'GPU: AMD Radeon RX 7700 XT\nArquitectura: RDNA 3\nProcesadores de flujo: 3456\nMemoria: 12 GB GDDR6, bus de 192 bits, 18 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia de referencia: hasta 2544 MHz (Boost) / 2171 MHz (Game)\nSalidas: 2 x HDMI 2.1 y 2 x DisplayPort 2.1\nFuente recomendada: 700 W\nRefrigeración: Sapphire Dual-X','https://fullh4rd.com.ar/prod/27411/video-radeon-rx-7700-xt-12gb-sapphire-pulse','rx7700xtsapphire.php','img/rx7700xtsapphire.png'),(13,'Sapphire','Radeon RX 9070 PULSE 16GB',1441659.94,'GPU: AMD Radeon RX 9070\nArquitectura: RDNA 4\nProcesadores de flujo: 3584\nMemoria: 16 GB GDDR6, bus de 256 bits, 20 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia de referencia: hasta 2520 MHz (Boost) / 2070 MHz (Game)\nSalidas: 2 x HDMI y 2 x DisplayPort 2.1a\nFuente recomendada: 650 W\nDimensiones: 280 x 120,25 x 51,5 mm\nRefrigeración: Sapphire Dual-X','https://fullh4rd.com.ar/prod/30099/placa-de-video-radeon-rx-9070-16gb-sapphire-pulse','rx9070sapphire.php','img/rx9070sapphire.png'),(14,'ASUS','Radeon RX 9070 Prime EVO OC 16GB',1279683.89,'GPU: AMD Radeon RX 9070\nArquitectura: RDNA 4\nProcesadores de flujo: 3584\nMemoria: 16 GB GDDR6, bus de 256 bits, 20 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia OC: hasta 2610 MHz (Boost) / 2140 MHz (Game)\nSalidas: 1 x HDMI 2.1b y 3 x DisplayPort 2.1a\nFuente recomendada: 650 W\nRefrigeración: ASUS Axial-tech','https://www.fullh4rd.com.ar/prod/31840','rx9070asus.php','img/rx9070asus.png'),(15,'ASUS','GeForce RTX 3060 Dual V2 OC 12GB',983489.97,'GPU: NVIDIA GeForce RTX 3060\nNúcleos CUDA: 3584\nMemoria: 12 GB GDDR6, bus de 192 bits, 15 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia: hasta 1867 MHz (OC) / 1837 MHz (Gaming)\nSalidas: 1 x HDMI 2.1 y 3 x DisplayPort 1.4a\nFuente recomendada: 650 W\nDimensiones: 200 x 123 x 38 mm','https://fullh4rd.com.ar/prod/19851/video-geforce-rtx-3060-12gb-asus-dual-v2-oc-edition','rtx3060asus.php','img/rtx3060asus.png'),(16,'ASUS','GeForce RTX 5090 TUF Gaming OC 32GB',7612112.30,'GPU: NVIDIA GeForce RTX 5090, arquitectura Blackwell\nNúcleos CUDA: 21760\nMemoria: 32 GB GDDR7, bus de 512 bits, 28 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia OC: hasta 2580 MHz\nSalidas: 2 x HDMI 2.1b y 3 x DisplayPort 2.1b\nDimensiones: 348 x 146 x 72 mm\nFuente recomendada: 1000 W\nRefrigeración: Axial-tech, cámara de vapor y BIOS dual','https://fullh4rd.com.ar/prod/31958','rtx5090asus.php','img/rtx5090-sin-sello-garantia.png'),(17,'PowerColor','Radeon RX 7600 Fighter 8GB',566804.57,'GPU: AMD Radeon RX 7600\nArquitectura: RDNA 3\nProcesadores de flujo: 2048\nMemoria: 8 GB GDDR6, bus de 128 bits, 18 Gbps\nInterfaz: PCI Express 4.0 x8\nFrecuencia: hasta 2655 MHz (Boost) / 2250 MHz (Game)\nSalidas: 1 x HDMI 2.1 y 3 x DisplayPort 2.1\nFuente recomendada: 550 W\nDimensiones: 241 x 116 x 49 mm','https://fullh4rd.com.ar/prod/32089/placa-de-video-radeon-rx-7600-8gb-powercolor-figther','rx7600powercolor.php','img/rx7600powercolor.png'),(18,'PowerColor','Radeon RX 9070 XT Hellhound OC 16GB',1556412.54,'GPU: AMD Radeon RX 9070 XT\nArquitectura: RDNA 4\nProcesadores de flujo: 4096\nMemoria: 16 GB GDDR6, bus de 256 bits, 20 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia OC: hasta 3010 MHz (Boost) / 2460 MHz (Game)\nSalidas: 1 x HDMI 2.1b y 3 x DisplayPort 2.1a\nFuente mínima recomendada: 800 W\nDimensiones: 327 x 128 x 49 mm (340 mm con soporte)','https://fullh4rd.com.ar/prod/30613/placa-de-video-radeon-rx-9070-xt-16gb-powercolor-hellhound-oc','rx9070xtpowercolor.php','img/rx9070tipowercolor.png'),(19,'INNO3D','GeForce RTX 3080 X3 LHR 10GB',NULL,'GPU: NVIDIA GeForce RTX 3080, arquitectura Ampere\nNúcleos CUDA: 8704\nMemoria: 10 GB GDDR6X, bus de 320 bits, 19 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia base/Boost: 1440/1710 MHz\nSalidas: 3 x DisplayPort 1.4a y 1 x HDMI 2.1a\nFuente recomendada: 750 W\nAlimentación: 2 x 8 pines\nDimensiones: 300 x 112 mm; diseño de 2 ranuras','https://www.inno3d.com/product/inno3d-geforce-rtx-3080-x3-lhr','rtx3080inno3d.php','img/rtx3080inno3d.png'),(20,'INNO3D','GeForce RTX 5090 X3 32GB',5268897.11,'GPU: NVIDIA GeForce RTX 5090, arquitectura Blackwell\nNúcleos CUDA: 21760\nMemoria: 32 GB GDDR7, bus de 512 bits, 28 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia Boost: hasta 2400 MHz (dato de la ficha de referencia)\nRefrigeración: triple ventilador X3 y cámara de vapor\nFuente recomendada para RTX 5090: 1000 W','https://fullh4rd.com.ar/prod/30628/placa-de-video-geforce-rtx-5090-32gb-inno3d-x3','rtx5090inno3d.php','img/rtx5090inno.png'),(21,'MSI','GeForce RTX 3060 Ventus 2X OC 12GB',987599.85,'GPU: NVIDIA GeForce RTX 3060\nNúcleos CUDA: 3584\nMemoria: 12 GB GDDR6, bus de 192 bits, 15 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia Boost: hasta 1807 MHz\nSalidas: 3 x DisplayPort 1.4a y 1 x HDMI 2.1\nFuente recomendada: 550 W\nDimensiones: 235 x 124 x 42 mm\nRefrigeración: doble ventilador TORX 3.0','https://fullh4rd.com.ar/prod/18168/placa-de-video-geforce-rtx-3060-12gb-msi-ventus-2x-oc','rtx3060msi.php','img/rtx3060msi.png'),(22,'MSI','GeForce RTX 4090 Gaming Slim 24GB',3669574.00,'GPU: NVIDIA GeForce RTX 4090\nNúcleos CUDA: 16384\nMemoria: 24 GB GDDR6X, bus de 384 bits, 21 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia Boost: hasta 2595 MHz\nSalidas: 2 x DisplayPort 1.4a y 2 x HDMI 2.1a\nFuente recomendada: 850 W\nDimensiones: 322 x 136 x 62 mm\nRefrigeración: MSI TRI FROZR 3','https://articulo.mercadolibre.com.ar/MLA-1904279984-placa-de-video-msi-geforce-rtx-4090-gaming-slim-24gb-gddr6x-_JM','rtx4090msi.php','img/rtx4090msi.png'),(23,'Zotac','GeForce RTX 5090 AMP Extreme Infinity 32GB',5274705.14,'GPU: NVIDIA GeForce RTX 5090, arquitectura Blackwell\nNúcleos CUDA: 21760\nMemoria: 32 GB GDDR7, bus de 512 bits, 28 Gbps\nInterfaz: PCI Express 5.0 x16\nFrecuencia Boost: hasta 2467 MHz\nSalidas: 3 x DisplayPort 2.1b y 1 x HDMI 2.1b\nFuente recomendada: 1000 W\nConsumo: 600 W\nDimensiones: 332,1 x 137,5 x 69,6 mm\nRefrigeración: ZOTAC IceStorm 3.0','https://fullh4rd.com.ar/prod/30094/video-geforce-rtx-5090-32gb-zotac-amp-extreme-infinity','rtx5090zotac.php','img/rtx5090zotac.png'),(24,'Zotac','GeForce RTX 3080 Trinity 10GB',706930.00,'GPU: NVIDIA GeForce RTX 3080, arquitectura Ampere\nNúcleos CUDA: 8704\nMemoria: 10 GB GDDR6X, bus de 320 bits, 19 Gbps\nInterfaz: PCI Express 4.0 x16\nFrecuencia Boost: 1710 MHz\nSalidas: 3 x DisplayPort 1.4a y 1 x HDMI 2.1\nFuente recomendada: 750 W\nConsumo: 320 W\nAlimentación: 2 x 8 pines\nDimensiones: 317,8 x 120,7 x 58 mm\nRefrigeración: Zotac IceStorm 2.0','https://www.microsistemas.ar/productos/ZOTAC-GeForce-RTX-3080-10G6X-GOC-10GB-GDDR6X/','rtx3080zotac.php','img/rtx3080zotac.png');
/*!40000 ALTER TABLE `gpu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gpu_cliente`
--

DROP TABLE IF EXISTS `gpu_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gpu_cliente` (
  `id_gpu_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `marca` varchar(100) NOT NULL,
  `modelo` varchar(150) NOT NULL,
  `numero_serie` varchar(100) NOT NULL,
  `fecha_compra` date DEFAULT NULL,
  `garantia_hasta` date DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `id_cliente` int(11) NOT NULL,
  PRIMARY KEY (`id_gpu_cliente`),
  UNIQUE KEY `uq_gpu_cliente_serie` (`numero_serie`),
  KEY `fk_gpu_cliente_cliente` (`id_cliente`),
  CONSTRAINT `fk_gpu_cliente_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gpu_cliente`
--

LOCK TABLES `gpu_cliente` WRITE;
/*!40000 ALTER TABLE `gpu_cliente` DISABLE KEYS */;
/*!40000 ALTER TABLE `gpu_cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gpu_ventas`
--

DROP TABLE IF EXISTS `gpu_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gpu_ventas` (
  `id_gpu_ventas` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `fecha_venta` datetime NOT NULL DEFAULT current_timestamp(),
  `precio_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_gpu_ventas`),
  KEY `fk_gpu_ventas_cliente` (`id_cliente`),
  CONSTRAINT `fk_gpu_ventas_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gpu_ventas`
--

LOCK TABLES `gpu_ventas` WRITE;
/*!40000 ALTER TABLE `gpu_ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `gpu_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orden_compra`
--

DROP TABLE IF EXISTS `orden_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orden_compra` (
  `id_orden_compra` int(11) NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(11) NOT NULL,
  `id_empleado_recepcion` int(11) NOT NULL,
  `fecha_orden` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_orden_compra`),
  KEY `fk_orden_compra_proveedor` (`id_proveedor`),
  KEY `fk_orden_compra_empleado` (`id_empleado_recepcion`),
  CONSTRAINT `fk_orden_compra_empleado` FOREIGN KEY (`id_empleado_recepcion`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_orden_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orden_compra`
--

LOCK TABLES `orden_compra` WRITE;
/*!40000 ALTER TABLE `orden_compra` DISABLE KEYS */;
/*!40000 ALTER TABLE `orden_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orden_reparacion`
--

DROP TABLE IF EXISTS `orden_reparacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orden_reparacion` (
  `id_orden_reparacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_gpu_cliente` int(11) NOT NULL,
  `id_empleado_recepcion` int(11) NOT NULL,
  `fecha_ingreso` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `fecha_egreso` datetime DEFAULT NULL,
  PRIMARY KEY (`id_orden_reparacion`),
  KEY `fk_orden_rep_gpu_cliente` (`id_gpu_cliente`),
  KEY `fk_orden_rep_empleado` (`id_empleado_recepcion`),
  CONSTRAINT `fk_orden_rep_empleado` FOREIGN KEY (`id_empleado_recepcion`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_orden_rep_gpu_cliente` FOREIGN KEY (`id_gpu_cliente`) REFERENCES `gpu_cliente` (`id_gpu_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orden_reparacion`
--

LOCK TABLES `orden_reparacion` WRITE;
/*!40000 ALTER TABLE `orden_reparacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `orden_reparacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto`
--

DROP TABLE IF EXISTS `presupuesto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuesto` (
  `id_presupuesto` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_reparacion` int(11) NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `mano_obra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_presupuesto`),
  KEY `fk_presupuesto_orden` (`id_orden_reparacion`),
  CONSTRAINT `fk_presupuesto_orden` FOREIGN KEY (`id_orden_reparacion`) REFERENCES `orden_reparacion` (`id_orden_reparacion`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto`
--

LOCK TABLES `presupuesto` WRITE;
/*!40000 ALTER TABLE `presupuesto` DISABLE KEYS */;
/*!40000 ALTER TABLE `presupuesto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor`
--

DROP TABLE IF EXISTS `proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(20) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_proveedor`),
  UNIQUE KEY `uq_proveedor_cuit` (`cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor`
--

LOCK TABLES `proveedor` WRITE;
/*!40000 ALTER TABLE `proveedor` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `puesto`
--

DROP TABLE IF EXISTS `puesto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `puesto` (
  `id_puesto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id_puesto`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `puesto`
--

LOCK TABLES `puesto` WRITE;
/*!40000 ALTER TABLE `puesto` DISABLE KEYS */;
/*!40000 ALTER TABLE `puesto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registro_pendiente`
--

DROP TABLE IF EXISTS `registro_pendiente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registro_pendiente` (
  `id_pendiente` int(11) NOT NULL AUTO_INCREMENT,
  `correo` varchar(150) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `token_verificacion` char(64) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  PRIMARY KEY (`id_pendiente`),
  UNIQUE KEY `uq_registro_pendiente_correo` (`correo`),
  UNIQUE KEY `uq_registro_pendiente_token` (`token_verificacion`),
  KEY `idx_registro_pendiente_expira` (`token_expira`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registro_pendiente`
--

LOCK TABLES `registro_pendiente` WRITE;
/*!40000 ALTER TABLE `registro_pendiente` DISABLE KEYS */;
INSERT INTO `registro_pendiente` VALUES (1,'lucasnicolas2002@gmail.com','$2y$10$klsSAMUt1fBbjHLYpV6imuT3mxJBAjbvE9gEky54BrtOMC7TMFHDK','lucas','calvano','43434343','+541160209263','d15ca4e8719a40654e74853873bad0942856a64257194880ba90df1c166a4502','2026-10-24 05:05:46');
/*!40000 ALTER TABLE `registro_pendiente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reparacion`
--

DROP TABLE IF EXISTS `reparacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reparacion` (
  `id_reparacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_reparacion` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_fin` datetime DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_reparacion`),
  KEY `fk_reparacion_orden` (`id_orden_reparacion`),
  KEY `fk_reparacion_tecnico` (`id_tecnico`),
  CONSTRAINT `fk_reparacion_orden` FOREIGN KEY (`id_orden_reparacion`) REFERENCES `orden_reparacion` (`id_orden_reparacion`),
  CONSTRAINT `fk_reparacion_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reparacion`
--

LOCK TABLES `reparacion` WRITE;
/*!40000 ALTER TABLE `reparacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `reparacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `id_stock` int(11) NOT NULL AUTO_INCREMENT,
  `id_componente` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `ubicacion` varchar(150) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_stock`),
  KEY `fk_stock_componente` (`id_componente`),
  CONSTRAINT `fk_stock_componente` FOREIGN KEY (`id_componente`) REFERENCES `componente` (`id_componente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock`
--

LOCK TABLES `stock` WRITE;
/*!40000 ALTER TABLE `stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_gpu`
--

DROP TABLE IF EXISTS `stock_gpu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_gpu` (
  `id_stock_gpu` int(11) NOT NULL AUTO_INCREMENT,
  `id_gpu` int(11) NOT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `ubicacion` varchar(150) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_stock_gpu`),
  KEY `fk_stock_gpu_gpu` (`id_gpu`),
  CONSTRAINT `fk_stock_gpu_gpu` FOREIGN KEY (`id_gpu`) REFERENCES `gpu` (`id_gpu`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_gpu`
--

LOCK TABLES `stock_gpu` WRITE;
/*!40000 ALTER TABLE `stock_gpu` DISABLE KEYS */;
INSERT INTO `stock_gpu` VALUES (1,1,'Disponible',NULL,4),(2,2,'Disponible',NULL,2),(3,3,'Disponible',NULL,3),(4,4,'Disponible',NULL,5),(5,5,'Disponible',NULL,4),(6,6,'Disponible',NULL,2),(7,7,'Disponible',NULL,3),(8,8,'Disponible',NULL,4),(9,9,'Disponible',NULL,2),(10,10,'Disponible',NULL,5),(11,11,'Disponible',NULL,3),(12,12,'Disponible',NULL,4),(13,13,'Disponible',NULL,2),(14,14,'Disponible',NULL,5),(15,15,'Disponible',NULL,3),(16,16,'Disponible',NULL,2),(17,17,'Disponible',NULL,5),(18,18,'Disponible',NULL,3),(19,19,'Disponible',NULL,4),(20,20,'Disponible',NULL,2),(21,21,'Disponible',NULL,5),(22,22,'Disponible',NULL,3),(23,23,'Disponible',NULL,4),(24,24,'Disponible',NULL,2);
/*!40000 ALTER TABLE `stock_gpu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_correo` int(11) NOT NULL AUTO_INCREMENT,
  `correo` varchar(150) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `correo_verificado` tinyint(1) NOT NULL DEFAULT 1,
  `token_verificacion` char(64) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  PRIMARY KEY (`id_correo`),
  UNIQUE KEY `uq_usuarios_correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (11,'gpufixstore@gmail.com','$2y$10$M6eNKvWrARaxfqO9eJjemuIrgA.gg.gdx8bWpI7SOyuDFjmTp80UW','GPU FIX','Provisorio','32323232',NULL,1,NULL,NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'if0_43068954_gpufix'
--

--
-- Dumping routines for database 'if0_43068954_gpufix'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-23  9:50:19
