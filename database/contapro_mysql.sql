-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: contapro
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accesos_grupos`
--

DROP TABLE IF EXISTS `accesos_grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accesos_grupos` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_grupo` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_modulo` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_forma` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puede_insertar` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puede_borrar` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puede_actualizar` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puede_consultar` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_menu` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mostrar_en_menu` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_accesos_grupos_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accesos_grupos`
--

LOCK TABLES `accesos_grupos` WRITE;
/*!40000 ALTER TABLE `accesos_grupos` DISABLE KEYS */;
INSERT INTO `accesos_grupos` VALUES (1,'CONTA','CO','COLIBCOM','S','S','S','S','Libro Compras','S'),(2,'CONTA','CO','COLIBCOM','S','S','S','S','Libro Compras','S'),(1,'CONTA','CO','COASIENT','S','S','S','S','Registro de Asientos Contables','S'),(2,'CONTA','CO','COASIENT','S','S','S','S','Registro de Asientos Contables','S'),(1,'CONTA','CO','COPLANCT','S','S','S','S','Plan de Cuentas','S'),(2,'CONTA','CO','COPLANCT','S','S','S','S','Plan de Cuentas','S'),(1,'CONTA','CO','BSCOTIZA','S','S','S','S','Cotizaciones de Monedas','S'),(2,'CONTA','CO','BSCOTIZA','S','S','S','S','Cotizaciones de Monedas','S'),(1,'CONTA','CO','COF12000','S','S','S','S','Formulario 120 (IVA V3)','S'),(2,'CONTA','CO','COF12000','S','S','S','S','Formulario 120 (IVA V3)','S'),(1,'CONTA','AD','COCONTRI','S','S','S','S','Registro de Contribuyentes','S'),(2,'CONTA','AD','COCONTRI','S','S','S','S','Registro de Contribuyentes','S'),(1,'CONTA','AD','BSUSUARI','S','S','S','S','Gestión de Usuarios','S'),(2,'CONTA','AD','BSUSUARI','S','S','S','S','Gestión de Usuarios','S'),(1,'CONTA','AD','BSCAMCLA','S','S','S','S','Cambio de Clave','S'),(2,'CONTA','AD','BSCAMCLA','S','S','S','S','Cambio de Clave','S'),(1,'CONTA','AD','BSBACKUP','S','S','S','S','Respaldo de Base de Datos','S'),(2,'CONTA','AD','BSBACKUP','S','S','S','S','Respaldo de Base de Datos','S'),(1,'CONTA','CO','COLIBMAY','S','S','S','S','Libro Mayor','S'),(2,'CONTA','CO','COLIBMAY','S','S','S','S','Libro Mayor','S'),(1,'CONTA','CO','COMARANG','S','S','S','S','Exportar a Marangatu (RG 90/2021)','S'),(2,'CONTA','CO','COMARANG','S','S','S','S','Exportar a Marangatu (RG 90/2021)','S'),(1,'CONTA','CO','COREGIMN','S','S','S','S','Definición de Regímenes','S'),(2,'CONTA','CO','COREGIMN','S','S','S','S','Definición de Regímenes','S'),(1,'CONTA','CO','COTIPEGR','S','S','S','S','Definición de Tipos de Egreso','S'),(2,'CONTA','CO','COTIPEGR','S','S','S','S','Definición de Tipos de Egreso','S'),(1,'CONTA','AD','COACTIVI','S','S','S','S','Actividades Económicas','S'),(2,'CONTA','AD','COACTIVI','S','S','S','S','Actividades Económicas','S'),(1,'CONTA','CO','COLIBDIA','S','S','S','S','Libro Diario','S'),(2,'CONTA','CO','COLIBDIA','S','S','S','S','Libro Diario','S'),(1,'CONTA','CO','COINGRES','S','S','S','S','Registro de Ingresos','S'),(2,'CONTA','CO','COINGRES','S','S','S','S','Registro de Ingresos','S'),(1,'CONTA','CO','COEGRES','S','S','S','S','Registro de Egresos','S'),(2,'CONTA','CO','COEGRES','S','S','S','S','Registro de Egresos','S'),(1,'CONTA','CO','COTIMBRA','S','S','S','S','Registro de Timbrados de Venta','S'),(2,'CONTA','CO','COTIMBRA','S','S','S','S','Registro de Timbrados de Venta','S'),(1,'CONTA','CO','COPERSON','S','S','S','S','Directorio de Personas','S'),(2,'CONTA','CO','COPERSON','S','S','S','S','Directorio de Personas','S'),(1,'CONTA','CO','COPROVEE','S','S','S','S','Directorio de Proveedores','S'),(2,'CONTA','CO','COPROVEE','S','S','S','S','Directorio de Proveedores','S'),(1,'CONTA','CO','COCLIENT','S','S','S','S','Directorio de Clientes','S'),(2,'CONTA','CO','COCLIENT','S','S','S','S','Directorio de Clientes','S'),(1,'CONTA','CO','COSUCURS','S','S','S','S','Directorio de Sucursales','S'),(2,'CONTA','CO','COSUCURS','S','S','S','S','Directorio de Sucursales','S'),(1,'CONTA','CO','COEJERCI','S','S','S','S','Ejercicios Contables','S'),(2,'CONTA','CO','COEJERCI','S','S','S','S','Ejercicios Contables','S'),(1,'CONTA','CO','COTIPCOM','S','S','S','S','Tipos de Comprobantes','S'),(2,'CONTA','CO','COTIPCOM','S','S','S','S','Tipos de Comprobantes','S'),(1,'CONTA','CO','COCLASGA','S','S','S','S','Clasificación de Gastos','S'),(2,'CONTA','CO','COCLASGA','S','S','S','S','Clasificación de Gastos','S'),(1,'CONTA','CO','COTIPING','S','S','S','S','Tipos de Ingresos','S'),(2,'CONTA','CO','COTIPING','S','S','S','S','Tipos de Ingresos','S'),(1,'CONTA','CO','COLIBVEN','S','S','S','S','Libro Ventas','S'),(2,'CONTA','CO','COLIBVEN','S','S','S','S','Libro Ventas','S');
/*!40000 ALTER TABLE `accesos_grupos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_actividades`
--

DROP TABLE IF EXISTS `co_actividades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_actividades` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_actividad` int DEFAULT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `por_defecto` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_actividades_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_actividades`
--

LOCK TABLES `co_actividades` WRITE;
/*!40000 ALTER TABLE `co_actividades` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_actividades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_asientos_cab`
--

DROP TABLE IF EXISTS `co_asientos_cab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_asientos_cab` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_asiento` bigint DEFAULT NULL,
  `fec_asiento` datetime DEFAULT NULL,
  `ejercicio` int DEFAULT NULL,
  `cod_sucursal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_comprobante` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_comprobante` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `concepto` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_debe` decimal(15,2) DEFAULT NULL,
  `total_haber` decimal(15,2) DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  KEY `idx_co_asientos_cab_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_asientos_cab`
--

LOCK TABLES `co_asientos_cab` WRITE;
/*!40000 ALTER TABLE `co_asientos_cab` DISABLE KEYS */;
INSERT INTO `co_asientos_cab` VALUES (1,'1',1,'2026-10-12 00:00:00',2026,'1','COEGRES','1','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',304000.00,304000.00,'A','CONTA','2026-10-14 00:19:33'),(1,'4',1,'2026-10-08 00:00:00',2026,'01','MANUAL',NULL,'prueba',1000.00,1000.00,'A','CONTA','2026-10-07 22:34:39'),(1,'4',3,'2026-10-13 00:00:00',2026,'1','COEGRES','1','Factura N° 001-001-0000001 - OJEDA SANDOVAL, CARLOS JAVIER',5500000.00,5500000.00,'A','CONTA','2026-10-12 23:46:16'),(1,'1',4,'2026-10-12 00:00:00',2026,'1','COEGRES','2','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',304000.00,304000.00,'A','CONTA','2026-10-14 23:46:40'),(1,'1',5,'2026-09-18 00:00:00',2026,'1','COEGRES','3','Factura N° 005-001-0078837 - ELECTROPAR SA',50800.00,50800.00,'A','CONTA','2026-10-14 23:48:18'),(1,'4',2,'2026-10-10 00:00:00',2026,'1','COEGRES','3','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',205550.00,205550.00,'A','CONTA','2026-10-10 14:13:00');
/*!40000 ALTER TABLE `co_asientos_cab` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_asientos_det`
--

DROP TABLE IF EXISTS `co_asientos_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_asientos_det` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_asiento` bigint DEFAULT NULL,
  `secuencia` int DEFAULT NULL,
  `cod_cuenta` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_cuenta` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debe` decimal(15,2) DEFAULT NULL,
  `haber` decimal(15,2) DEFAULT NULL,
  KEY `idx_co_asientos_det_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_asientos_det`
--

LOCK TABLES `co_asientos_det` WRITE;
/*!40000 ALTER TABLE `co_asientos_det` DISABLE KEYS */;
INSERT INTO `co_asientos_det` VALUES (1,'1',1,3,'01.01.01.02.01','CAJA GUARANÍES','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',0.00,304000.00),(1,'1',1,1,'01.01.04.01.01','MERCADERÍAS GRAVADAS POR EL IVA AL 10%','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',276364.00,0.00),(1,'4',1,1,'01.01.01.01','RECAUDACIONES A DEPOSITAR','prueba',1000.00,0.00),(1,'4',1,2,'01.01.01.02.01','CAJA GUARANÍES','prueba',0.00,1000.00),(1,'4',3,3,'01.01.01.02.01','CAJA GUARANÍES','Factura N° 001-001-0000001 - OJEDA SANDOVAL, CARLOS JAVIER',0.00,5500000.00),(1,'4',3,1,'01.01.04.01.01','MERCADERÍAS GRAVADAS POR EL IVA AL 10%','Factura N° 001-001-0000001 - OJEDA SANDOVAL, CARLOS JAVIER',5000000.00,0.00),(1,'4',3,2,'01.01.03.05.03.01','IVA CRÉDITO 10%','Factura N° 001-001-0000001 - OJEDA SANDOVAL, CARLOS JAVIER',500000.00,0.00),(1,'1',4,3,'01.01.01.02.01','CAJA GUARANÍES','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',0.00,304000.00),(1,'1',5,1,'01.01.04.01.01','MERCADERÍAS GRAVADAS POR EL IVA AL 10%','Factura N° 005-001-0078837 - ELECTROPAR SA',46182.00,0.00),(1,'1',1,2,'01.01.03.05.03.01','IVA CRÉDITO 10%','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',27636.00,0.00),(1,'1',5,2,'01.01.03.05.03.01','IVA CRÉDITO 10%','Factura N° 005-001-0078837 - ELECTROPAR SA',4618.00,0.00),(1,'1',4,1,'01.01.04.01.01','MERCADERÍAS GRAVADAS POR EL IVA AL 10%','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',276364.00,0.00),(1,'1',4,2,'01.01.03.05.03.01','IVA CRÉDITO 10%','Factura N° 001-001-0032386 - GONZALEZ CACERES, FRANCISCO RAMON',27636.00,0.00),(1,'1',5,3,'01.01.01.02.01','CAJA GUARANÍES','Factura N° 005-001-0078837 - ELECTROPAR SA',0.00,50800.00),(1,'4',2,1,'01.01.04.01.01','MERCADERÍAS GRAVADAS POR EL IVA AL 10%','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',90909.00,0.00),(1,'4',2,2,'01.01.03.05.03.01','IVA CRÉDITO 10%','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',9091.00,0.00),(1,'4',2,3,'01.01.04.01.02','MERCADERÍAS GRAVADAS POR EL IVA AL 5%','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',47619.00,0.00),(1,'4',2,4,'01.01.03.05.03.02','IVA CRÉDITO 05%','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',2381.00,0.00),(1,'4',2,5,'01.01.04.01.03','MERCADERÍAS EXENTAS DEL IVA','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',55550.00,0.00),(1,'4',2,6,'01.01.01.02.01','CAJA GUARANÍES','Factura N° 001-001-0000004 - RUIZ AQUINO, RUTH KARINA',0.00,205550.00);
/*!40000 ALTER TABLE `co_asientos_det` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_clasificacion_gasto`
--

DROP TABLE IF EXISTS `co_clasificacion_gasto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_clasificacion_gasto` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_clasificacion` decimal(20,2) DEFAULT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  KEY `idx_co_clasificacion_gasto_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_clasificacion_gasto`
--

LOCK TABLES `co_clasificacion_gasto` WRITE;
/*!40000 ALTER TABLE `co_clasificacion_gasto` DISABLE KEYS */;
INSERT INTO `co_clasificacion_gasto` VALUES (1,1.00,'Gasto','A','SYSTEM','2026-10-03 22:44:42'),(1,2.00,'Inversiones Relacionadas a la Actividad Gravada','A','SYSTEM','2026-10-03 22:44:42'),(1,3.00,'Inversiones Personales y de familiares a Cargo','A','SYSTEM','2026-10-03 22:44:42'),(1,4.00,'Gastos Personales y Familiares a Cargo','A','SYSTEM','2026-10-03 22:44:42');
/*!40000 ALTER TABLE `co_clasificacion_gasto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_clientes`
--

DROP TABLE IF EXISTS `co_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_clientes` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_persona` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  `tipo_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_clientes_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_clientes`
--

LOCK TABLES `co_clientes` WRITE;
/*!40000 ALTER TABLE `co_clientes` DISABLE KEYS */;
INSERT INTO `co_clientes` VALUES (1,'1','1','3','DISTRIBUIDORA Y COMERCIAL SANABRIA SOCIEDAD ANONIMA','80108040-1',NULL,NULL,NULL,'A','ADMIN','2026-10-15 01:00:06','RUC');
/*!40000 ALTER TABLE `co_clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_egresos`
--

DROP TABLE IF EXISTS `co_egresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_egresos` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `id_egreso` bigint NOT NULL AUTO_INCREMENT,
  `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdc` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clasificacion_gasto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_timb` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venc_timbrado` datetime DEFAULT NULL,
  `nro_factura` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_egreso` datetime DEFAULT NULL,
  `comprador` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_proveedor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruc_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_egreso` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_120` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_145` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tasa_cambio` decimal(12,4) DEFAULT NULL,
  `grav10` decimal(15,2) DEFAULT NULL,
  `iva10` decimal(15,2) DEFAULT NULL,
  `grav5` decimal(15,2) DEFAULT NULL,
  `iva5` decimal(15,2) DEFAULT NULL,
  `exentas` decimal(15,2) DEFAULT NULL,
  `tot_iva` decimal(15,2) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  `regimen` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_regimen` decimal(20,2) DEFAULT NULL,
  `cta_grav10` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_iva10` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_grav5` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_iva5` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_exentas` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_credito` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timbrado_asoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprobante_asoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_egreso`),
  KEY `idx_co_egresos_prop` (`id_propietario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_egresos`
--

LOCK TABLES `co_egresos` WRITE;
/*!40000 ALTER TABLE `co_egresos` DISABLE KEYS */;
INSERT INTO `co_egresos` VALUES (1,2,'1',NULL,'FAC','1','Pre-impreso','18976697','2026-07-31 00:00:00','001-001-0032386',NULL,'2026-10-12 00:00:00','OJEDA SANDOVAL, CARLOS JAVIER','1','GONZALEZ CACERES, FRANCISCO RAMON','2433384-0','1','Directo','CON','Original','1',1.0000,304000.00,27636.00,0.00,0.00,0.00,27636.00,304000.00,NULL,'A','CONTA','2026-10-14 00:20:55','1',1.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,3,'1',NULL,'FAC','1','Electrónico','16068710','2100-12-31 00:00:00','005-001-0078837',NULL,'2026-09-18 00:00:00','OJEDA SANDOVAL, CARLOS JAVIER','2','ELECTROPAR SA','80009085-3','1','Directo','CON','Original','1',1.0000,50800.00,4618.00,0.00,0.00,0.00,4618.00,50800.00,NULL,'A','CONTA','2026-10-14 23:48:18','1',1.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `co_egresos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_ejercicios`
--

DROP TABLE IF EXISTS `co_ejercicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_ejercicios` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_ejercicio` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_inicio` datetime DEFAULT NULL,
  `fec_final` datetime DEFAULT NULL,
  `cod_per_dueno` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_per_repres_legal` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_per_contador` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_formulario` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_orden` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_representante` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_ejercicios_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_ejercicios`
--

LOCK TABLES `co_ejercicios` WRITE;
/*!40000 ALTER TABLE `co_ejercicios` DISABLE KEYS */;
INSERT INTO `co_ejercicios` VALUES (1,'1','2026','2026-01-01 00:00:00','2026-12-31 00:00:00',NULL,NULL,NULL,NULL,NULL,'A',NULL);
/*!40000 ALTER TABLE `co_ejercicios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_formulario_120`
--

DROP TABLE IF EXISTS `co_formulario_120`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_formulario_120` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mes` int DEFAULT NULL,
  `anio` int DEFAULT NULL,
  `tipo_declaracion` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_orden_rect` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caracter_cese` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c10` bigint DEFAULT NULL,
  `c22` bigint DEFAULT NULL,
  `c11` bigint DEFAULT NULL,
  `c19` bigint DEFAULT NULL,
  `c12` bigint DEFAULT NULL,
  `c13` bigint DEFAULT NULL,
  `c14` bigint DEFAULT NULL,
  `c15` bigint DEFAULT NULL,
  `c23` bigint DEFAULT NULL,
  `c16` bigint DEFAULT NULL,
  `c20` bigint DEFAULT NULL,
  `c17` bigint DEFAULT NULL,
  `c18` bigint DEFAULT NULL,
  `c21` bigint DEFAULT NULL,
  `c24` bigint DEFAULT NULL,
  `c25` bigint DEFAULT NULL,
  `c26` bigint DEFAULT NULL,
  `c27` bigint DEFAULT NULL,
  `c28` bigint DEFAULT NULL,
  `c29` bigint DEFAULT NULL,
  `c30` bigint DEFAULT NULL,
  `c31` bigint DEFAULT NULL,
  `c32` bigint DEFAULT NULL,
  `c35` bigint DEFAULT NULL,
  `c38` bigint DEFAULT NULL,
  `c33` bigint DEFAULT NULL,
  `c36` bigint DEFAULT NULL,
  `c39` bigint DEFAULT NULL,
  `c40` bigint DEFAULT NULL,
  `c41` bigint DEFAULT NULL,
  `c34` bigint DEFAULT NULL,
  `c37` bigint DEFAULT NULL,
  `c42` bigint DEFAULT NULL,
  `c43` bigint DEFAULT NULL,
  `c44` bigint DEFAULT NULL,
  `c45` bigint DEFAULT NULL,
  `c46` bigint DEFAULT NULL,
  `c47` bigint DEFAULT NULL,
  `c48` bigint DEFAULT NULL,
  `c49` bigint DEFAULT NULL,
  `c50` bigint DEFAULT NULL,
  `c55` bigint DEFAULT NULL,
  `c51` bigint DEFAULT NULL,
  `c52` bigint DEFAULT NULL,
  `c56` bigint DEFAULT NULL,
  `c53` bigint DEFAULT NULL,
  `c57` bigint DEFAULT NULL,
  `c54` bigint DEFAULT NULL,
  `c58` bigint DEFAULT NULL,
  `c59` bigint DEFAULT NULL,
  `c65` bigint DEFAULT NULL,
  `c60` bigint DEFAULT NULL,
  `c66` bigint DEFAULT NULL,
  `c61` bigint DEFAULT NULL,
  `c62` bigint DEFAULT NULL,
  `c63` bigint DEFAULT NULL,
  `c64` bigint DEFAULT NULL,
  KEY `idx_co_formulario_120_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_formulario_120`
--

LOCK TABLES `co_formulario_120` WRITE;
/*!40000 ALTER TABLE `co_formulario_120` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_formulario_120` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_ingresos`
--

DROP TABLE IF EXISTS `co_ingresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_ingresos` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `id_ingreso` bigint NOT NULL AUTO_INCREMENT,
  `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdc` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anulada` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inutilizado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venc_timbrado` datetime DEFAULT NULL,
  `nro_factura` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_ingreso` datetime DEFAULT NULL,
  `condicion` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_cliente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruc_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_ingreso` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_145` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tasa_cambio` decimal(12,4) DEFAULT NULL,
  `grav10` decimal(15,2) DEFAULT NULL,
  `iva10` decimal(15,2) DEFAULT NULL,
  `grav5` decimal(15,2) DEFAULT NULL,
  `iva5` decimal(15,2) DEFAULT NULL,
  `exentas` decimal(15,2) DEFAULT NULL,
  `tot_iva` decimal(15,2) DEFAULT NULL,
  `valor_bruto` decimal(15,2) DEFAULT NULL,
  `no_gravado` decimal(15,2) DEFAULT NULL,
  `redondeo` decimal(15,2) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  `cod_regimen` decimal(20,2) DEFAULT NULL,
  `cod_tipo_ingreso` decimal(20,2) DEFAULT NULL,
  `cta_debito` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_grav10` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_iva10` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_grav5` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_iva5` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_exentas` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timbrado_asoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprobante_asoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_ingreso`),
  KEY `idx_co_ingresos_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_ingresos`
--

LOCK TABLES `co_ingresos` WRITE;
/*!40000 ALTER TABLE `co_ingresos` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_ingresos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_persona_asientos`
--

DROP TABLE IF EXISTS `co_persona_asientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_persona_asientos` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_persona` decimal(20,2) DEFAULT NULL,
  `condicion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_iva10` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_iva5` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_grav10` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_grav5` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_exentas` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_contrapartida` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_persona_asientos_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_persona_asientos`
--

LOCK TABLES `co_persona_asientos` WRITE;
/*!40000 ALTER TABLE `co_persona_asientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_persona_asientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_plan_cuentas`
--

DROP TABLE IF EXISTS `co_plan_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_plan_cuentas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_cuenta` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_cuenta` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel` int DEFAULT NULL,
  `cod_padre` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asentable` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_plan_cuentas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_plan_cuentas`
--

LOCK TABLES `co_plan_cuentas` WRITE;
/*!40000 ALTER TABLE `co_plan_cuentas` DISABLE KEYS */;
INSERT INTO `co_plan_cuentas` VALUES (1,'01','ACTIVO','MAYORIZADOR',1,NULL,'N','A'),(1,'01.01','ACTIVO CORRIENTE','MAYORIZADOR',2,'01','N','A'),(1,'01.01.01','DISPONIBILIDADES','MAYORIZADOR',3,'01.01','N','A'),(1,'01.01.01.01','RECAUDACIONES A DEPOSITAR','IMPUTABLE',4,'01.01.01','S','A'),(1,'01.01.01.02','CAJA','MAYORIZADOR',4,'01.01.01','N','A'),(1,'01.01.01.02.01','CAJA GUARANÍES','IMPUTABLE',5,'01.01.01.02','S','A'),(1,'01.01.01.03','FONDOS FIJOS','IMPUTABLE',4,'01.01.01','S','A'),(1,'01.01.01.04','BANCOS','IMPUTABLE',4,'01.01.01','S','A'),(1,'01.01.02','INVERSIONES TEMPORARIAS','MAYORIZADOR',3,'01.01','N','A'),(1,'01.01.02.01','INVERSIONES FINANCIERAS','IMPUTABLE',4,'01.01.02','S','A');
/*!40000 ALTER TABLE `co_plan_cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_proveedores`
--

DROP TABLE IF EXISTS `co_proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_proveedores` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_persona` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  `tipo_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_proveedores_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_proveedores`
--

LOCK TABLES `co_proveedores` WRITE;
/*!40000 ALTER TABLE `co_proveedores` DISABLE KEYS */;
INSERT INTO `co_proveedores` VALUES (1,'1','1','4','GONZALEZ CACERES, FRANCISCO RAMON','2433384-0',NULL,NULL,NULL,'A','CONTA','2026-10-14 00:16:47','RUC'),(1,'2','1','5','ELECTROPAR SA','80009085-3',NULL,NULL,NULL,'A','CONTA','2026-10-14 23:47:42','RUC');
/*!40000 ALTER TABLE `co_proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_regimen`
--

DROP TABLE IF EXISTS `co_regimen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_regimen` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_regimen` decimal(20,2) DEFAULT NULL,
  `descripcion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imputa_iva` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imputa_irp` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imputa_ire` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  KEY `idx_co_regimen_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_regimen`
--

LOCK TABLES `co_regimen` WRITE;
/*!40000 ALTER TABLE `co_regimen` DISABLE KEYS */;
INSERT INTO `co_regimen` VALUES (1,1.00,'IRP & IVA','S','S','N','A','SYSTEM','2026-10-12 22:33:20'),(1,2.00,'IRP','N','S','N','A','SYSTEM','2026-10-12 22:33:20'),(1,3.00,'IVA','S','N','N','A','SYSTEM','2026-10-12 22:33:20'),(1,4.00,'G. CAPITAL','N','S','N','A','SYSTEM','2026-10-12 22:33:20');
/*!40000 ALTER TABLE `co_regimen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_timbrados`
--

DROP TABLE IF EXISTS `co_timbrados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_timbrados` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `id_timbrado` bigint NOT NULL AUTO_INCREMENT,
  `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_timbrado` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_establecimiento` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_punto_expedicion` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_inicio` datetime DEFAULT NULL,
  `fec_vencimiento` datetime DEFAULT NULL,
  `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_inicial` bigint DEFAULT NULL,
  `nro_final` bigint DEFAULT NULL,
  `nro_actual` bigint DEFAULT NULL,
  `controla_correlatividad` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `de_facturacion` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dar_de_baja` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actividades_economicas` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  PRIMARY KEY (`id_timbrado`),
  KEY `idx_co_timbrados_prop` (`id_propietario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_timbrados`
--

LOCK TABLES `co_timbrados` WRITE;
/*!40000 ALTER TABLE `co_timbrados` DISABLE KEYS */;
INSERT INTO `co_timbrados` VALUES (1,1,'4','12345678','Pre-impreso','001','001','2026-10-04 00:00:00','2027-10-04 00:00:00','FAC',1,100,1,'S','N','N',NULL,'A','CONTA','2026-10-03 21:32:10'),(1,2,'4','22222222','Pre-impreso','001','002','2026-10-04 00:00:00','2027-10-04 00:00:00','DEB',1,100,1,'S','N','N',NULL,'A','CONTA','2026-10-03 23:43:28'),(1,3,'1','12345678','Pre-impreso','001','001','2026-10-15 00:00:00','2027-10-15 00:00:00','FAC',1,100,1,'S','N','N',NULL,'A','CONTA','2026-10-14 23:50:49');
/*!40000 ALTER TABLE `co_timbrados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_tipo_ingreso`
--

DROP TABLE IF EXISTS `co_tipo_ingreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_tipo_ingreso` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_tipo_ingreso` decimal(20,2) DEFAULT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  KEY `idx_co_tipo_ingreso_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_tipo_ingreso`
--

LOCK TABLES `co_tipo_ingreso` WRITE;
/*!40000 ALTER TABLE `co_tipo_ingreso` DISABLE KEYS */;
INSERT INTO `co_tipo_ingreso` VALUES (1,1.00,'VENTAS DE MERCADERÍAS / SERVICIOS GRAVADOS','A','SYSTEM','2026-10-03 23:37:55'),(1,2.00,'SERVICIOS PROFESIONALES INDEPENDIENTES','A','SYSTEM','2026-10-03 23:37:55'),(1,3.00,'ALQUILER DE INMUEBLES / EQUIPOS','A','SYSTEM','2026-10-03 23:37:55'),(1,4.00,'OTROS INGRESOS OPERATIVOS','A','SYSTEM','2026-10-03 23:37:55');
/*!40000 ALTER TABLE `co_tipo_ingreso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_tipos_comprobantes`
--

DROP TABLE IF EXISTS `co_tipos_comprobantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_tipos_comprobantes` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `tip_comprobante` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_modulo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_co_tipos_comprobantes_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_tipos_comprobantes`
--

LOCK TABLES `co_tipos_comprobantes` WRITE;
/*!40000 ALTER TABLE `co_tipos_comprobantes` DISABLE KEYS */;
INSERT INTO `co_tipos_comprobantes` VALUES (1,'DEB','NOTA DE DEBITO','ST','A','CONTA   ',NULL,'INGRESOS'),(1,'FAC','FACTURA','AMBOS','A','CONTA   ',NULL,'AMBOS'),(1,'NCR','NOTA DE CREDITO','AMBOS','A','CONTA   ',NULL,'AMBOS');
/*!40000 ALTER TABLE `co_tipos_comprobantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_tipos_egreso`
--

DROP TABLE IF EXISTS `co_tipos_egreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_tipos_egreso` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_tipo` decimal(20,2) DEFAULT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_reg` datetime DEFAULT NULL,
  KEY `idx_co_tipos_egreso_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_tipos_egreso`
--

LOCK TABLES `co_tipos_egreso` WRITE;
/*!40000 ALTER TABLE `co_tipos_egreso` DISABLE KEYS */;
INSERT INTO `co_tipos_egreso` VALUES (1,1.00,'GASTOS GENERALES Y OPERATIVOS','A','SYSTEM','2026-10-12 23:29:11'),(1,2.00,'COMPRA DE MERCADERÍAS / MATERIA PRIMA','A','SYSTEM','2026-10-12 23:29:11'),(1,3.00,'COMBUSTIBLES Y LUBRICANTES','A','SYSTEM','2026-10-12 23:29:11'),(1,4.00,'SERVICIOS BÁSICOS (LUZ, AGUA, TELÉFONO, INTERNET)','A','SYSTEM','2026-10-12 23:29:11'),(1,5.00,'ALQUILERES PAGADOS','A','SYSTEM','2026-10-12 23:29:11'),(1,6.00,'HONORARIOS PROFESIONALES','A','SYSTEM','2026-10-12 23:29:11');
/*!40000 ALTER TABLE `co_tipos_egreso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizaciones` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `val_compra` decimal(15,2) DEFAULT NULL,
  `val_venta` decimal(15,2) DEFAULT NULL,
  KEY `idx_cotizaciones_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizaciones`
--

LOCK TABLES `cotizaciones` WRITE;
/*!40000 ALTER TABLE `cotizaciones` DISABLE KEYS */;
INSERT INTO `cotizaciones` VALUES (1,'2','2025-10-01 00:00:00',7245.00,7290.00),(2,'2','2025-10-01 00:00:00',7245.00,7290.00),(1,'2','2025-10-02 00:00:00',7252.00,7297.00),(2,'2','2025-10-02 00:00:00',7252.00,7297.00),(1,'2','2025-10-03 00:00:00',7259.00,7304.00),(2,'2','2025-10-03 00:00:00',7259.00,7304.00),(1,'2','2025-10-04 00:00:00',7241.00,7286.00),(2,'2','2025-10-04 00:00:00',7241.00,7286.00),(1,'2','2025-10-05 00:00:00',7248.00,7293.00),(2,'2','2025-10-05 00:00:00',7248.00,7293.00),(1,'2','2025-10-06 00:00:00',7255.00,7300.00),(2,'2','2025-10-06 00:00:00',7255.00,7300.00),(1,'2','2025-10-07 00:00:00',7262.00,7307.00),(2,'2','2025-10-07 00:00:00',7262.00,7307.00),(1,'2','2025-10-08 00:00:00',7244.00,7289.00),(2,'2','2025-10-08 00:00:00',7244.00,7289.00),(1,'2','2025-10-09 00:00:00',7251.00,7296.00),(2,'2','2025-10-09 00:00:00',7251.00,7296.00),(1,'2','2025-10-10 00:00:00',7258.00,7303.00),(2,'2','2025-10-10 00:00:00',7258.00,7303.00),(1,'2','2025-10-11 00:00:00',7240.00,7285.00),(2,'2','2025-10-11 00:00:00',7240.00,7285.00),(1,'2','2025-10-12 00:00:00',7247.00,7292.00),(2,'2','2025-10-12 00:00:00',7247.00,7292.00),(1,'2','2025-10-13 00:00:00',7254.00,7299.00),(2,'2','2025-10-13 00:00:00',7254.00,7299.00),(1,'2','2025-10-14 00:00:00',7261.00,7306.00),(2,'2','2025-10-14 00:00:00',7261.00,7306.00),(1,'2','2025-10-15 00:00:00',7243.00,7288.00),(2,'2','2025-10-15 00:00:00',7243.00,7288.00),(1,'2','2025-10-16 00:00:00',7250.00,7295.00),(2,'2','2025-10-16 00:00:00',7250.00,7295.00),(1,'2','2025-10-17 00:00:00',7257.00,7302.00),(2,'2','2025-10-17 00:00:00',7257.00,7302.00),(1,'2','2025-10-18 00:00:00',7239.00,7284.00),(2,'2','2025-10-18 00:00:00',7239.00,7284.00),(1,'2','2025-10-19 00:00:00',7246.00,7291.00),(2,'2','2025-10-19 00:00:00',7246.00,7291.00),(1,'2','2025-10-20 00:00:00',7253.00,7298.00),(2,'2','2025-10-20 00:00:00',7253.00,7298.00),(1,'2','2025-10-21 00:00:00',7260.00,7305.00),(2,'2','2025-10-21 00:00:00',7260.00,7305.00),(1,'2','2025-10-22 00:00:00',7242.00,7287.00),(2,'2','2025-10-22 00:00:00',7242.00,7287.00),(1,'2','2025-10-23 00:00:00',7249.00,7294.00),(2,'2','2025-10-23 00:00:00',7249.00,7294.00),(1,'2','2025-10-24 00:00:00',7256.00,7301.00),(2,'2','2025-10-24 00:00:00',7256.00,7301.00),(1,'2','2025-10-25 00:00:00',7238.00,7283.00),(2,'2','2025-10-25 00:00:00',7238.00,7283.00),(1,'2','2025-10-26 00:00:00',7245.00,7290.00),(2,'2','2025-10-26 00:00:00',7245.00,7290.00),(1,'2','2025-10-27 00:00:00',7252.00,7297.00),(2,'2','2025-10-27 00:00:00',7252.00,7297.00),(1,'2','2025-10-28 00:00:00',7259.00,7304.00),(2,'2','2025-10-28 00:00:00',7259.00,7304.00),(1,'2','2025-10-29 00:00:00',7241.00,7286.00),(2,'2','2025-10-29 00:00:00',7241.00,7286.00),(1,'2','2025-10-30 00:00:00',7248.00,7293.00),(2,'2','2025-10-30 00:00:00',7248.00,7293.00),(1,'2','2025-10-31 00:00:00',7255.00,7300.00),(2,'2','2025-10-31 00:00:00',7255.00,7300.00),(1,'3','2026-10-14 00:00:00',1129.95,1131.48),(1,'2','2026-10-15 00:00:00',5710.99,5712.61);
/*!40000 ALTER TABLE `cotizaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `direc_personas`
--

DROP TABLE IF EXISTS `direc_personas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direc_personas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_persona` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_direccion` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_direccion` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `casilla_correo` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_postal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalle` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_pais` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_provincia` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_ciudad` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_barrio` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `por_defecto` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion_ref` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barrio` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distancia` decimal(20,2) DEFAULT NULL,
  `observacion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_direc_personas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `direc_personas`
--

LOCK TABLES `direc_personas` WRITE;
/*!40000 ALTER TABLE `direc_personas` DISABLE KEYS */;
INSERT INTO `direc_personas` VALUES (1,'2','0','C',NULL,NULL,'avenida mariscal lopez y jose viñuales',NULL,NULL,NULL,NULL,'S',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `direc_personas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empresas`
--

DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_moneda_origen` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_per_juridica` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo_reportes` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usa_calendario` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruc_empresa` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actividad` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_patronal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departamento` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retiene_iva` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_logo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_replica_datos` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_retiene_iva` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_maneja_sectores` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_maneja_custodios` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_maneja_depositos` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_stock_negativo` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_maneja_lotes` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_facturacion_electronica` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userdpy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pwdpy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `klogin` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `klogout` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kupsync` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kcancel` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kinutil` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kconsulta` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kconsultacpb` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ramo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encabezado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_mimetype` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_autoriza_web` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_empresas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empresas`
--

LOCK TABLES `empresas` WRITE;
/*!40000 ALTER TABLE `empresas` DISABLE KEYS */;
INSERT INTO `empresas` VALUES (1,'1',NULL,NULL,NULL,'OJEDA SANDOVAL, CARLOS JAVIER',NULL,'5567097-0','avenida mariscal lopez y jose viñuales',NULL,NULL,'Fernando de la Mora',NULL,'N',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'N',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'N');
/*!40000 ALTER TABLE `empresas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `formas`
--

DROP TABLE IF EXISTS `formas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_modulo` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_forma` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `esta` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mostrar_en_menu` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_modulo_menu` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden_en_menu` int DEFAULT NULL,
  `cod_submodulo` int DEFAULT NULL,
  `orden` int DEFAULT NULL,
  `bloque_1` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloque_2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloque_3` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloque_4` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagina_apex` int DEFAULT NULL,
  KEY `idx_formas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formas`
--

LOCK TABLES `formas` WRITE;
/*!40000 ALTER TABLE `formas` DISABLE KEYS */;
INSERT INTO `formas` VALUES (1,'AD','BSBACKUP','Respaldo de Base de Datos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'AD','BSBACKUP','Respaldo de Base de Datos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'AD','BSCAMCLA','Cambio de Clave',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'AD','BSCAMCLA','Cambio de Clave',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'AD','BSUSUARI','Gestión de Usuarios',NULL,NULL,'S',NULL,'D',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'AD','BSUSUARI','Gestión de Usuarios',NULL,NULL,'S',NULL,'D',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'AD','COACTIVI','Actividades Económicas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'AD','COACTIVI','Actividades Económicas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'AD','COCONTRI','Registro de Contribuyentes',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'AD','COCONTRI','Registro de Contribuyentes',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','BSCOTIZA','Cotizaciones de Monedas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','BSCOTIZA','Cotizaciones de Monedas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COASIENT','Registro de Asientos Contables',NULL,NULL,'S',NULL,'M',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COASIENT','Registro de Asientos Contables',NULL,NULL,'S',NULL,'M',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COCLASGA','Clasificación de Gastos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COCLASGA','Clasificación de Gastos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COCLIENT','Directorio de Clientes',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COCLIENT','Directorio de Clientes',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COEGRES','Registro de Egresos',NULL,NULL,'S',NULL,'M',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COEGRES','Registro de Egresos',NULL,NULL,'S',NULL,'M',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COEJERCI','Ejercicios Contables',NULL,NULL,'S',NULL,'D',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COEJERCI','Ejercicios Contables',NULL,NULL,'S',NULL,'D',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COF12000','Formulario 120 (IVA V3)',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COF12000','Formulario 120 (IVA V3)',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COINGRES','Registro de Ingresos',NULL,NULL,'S',NULL,'M',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COINGRES','Registro de Ingresos',NULL,NULL,'S',NULL,'M',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COLIBCOM','Libro Compras',NULL,NULL,'S',NULL,'R',20,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COLIBCOM','Libro Compras',NULL,NULL,'S',NULL,'R',20,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COLIBDIA','Libro Diario',NULL,NULL,'S',NULL,'R',21,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COLIBDIA','Libro Diario',NULL,NULL,'S',NULL,'R',21,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COLIBMAY','Libro Mayor',NULL,NULL,'S',NULL,'R',22,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COLIBMAY','Libro Mayor',NULL,NULL,'S',NULL,'R',22,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COLIBVEN','Libro Ventas',NULL,NULL,'S',NULL,'R',10,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COLIBVEN','Libro Ventas',NULL,NULL,'S',NULL,'R',10,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COMARANG','Exportar a Marangatu (RG 90/2021)',NULL,NULL,'S',NULL,'R',30,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COMARANG','Exportar a Marangatu (RG 90/2021)',NULL,NULL,'S',NULL,'R',30,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COPERSON','Directorio de Personas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COPERSON','Directorio de Personas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COPLANCT','Plan de Cuentas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COPLANCT','Plan de Cuentas',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COPROVEE','Directorio de Proveedores',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COPROVEE','Directorio de Proveedores',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COREGIMN','Definición de Regímenes',NULL,NULL,'S',NULL,'D',17,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COREGIMN','Definición de Regímenes',NULL,NULL,'S',NULL,'D',17,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COSUCURS','Directorio de Sucursales',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COSUCURS','Directorio de Sucursales',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COTIMBRA','Registro de Timbrados de Venta',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COTIMBRA','Registro de Timbrados de Venta',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COTIPCOM','Tipos de Comprobantes',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COTIPCOM','Tipos de Comprobantes',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COTIPEGR','Tipos de Egresos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COTIPEGR','Tipos de Egresos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'CO','COTIPING','Tipos de Ingresos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'CO','COTIPING','Tipos de Ingresos',NULL,NULL,'S',NULL,'D',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `formas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grupos_usuarios`
--

DROP TABLE IF EXISTS `grupos_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_usuarios` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_grupo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_clave_sin_caducidad` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_grupos_usuarios_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grupos_usuarios`
--

LOCK TABLES `grupos_usuarios` WRITE;
/*!40000 ALTER TABLE `grupos_usuarios` DISABLE KEYS */;
INSERT INTO `grupos_usuarios` VALUES (1,'CONADM','ADMINISTRADOR CONTABLE','S'),(2,'CONADM','ADMINISTRADOR CONTABLE','S'),(1,'CONTA','GRUPO CONTABILIDAD (PLAN BASICO)','S'),(2,'CONTA','GRUPO CONTABILIDAD (PLAN BASICO)','S'),(1,'CONTAM','GRUPO CONTABILIDAD (PLAN MEDIO)','S'),(2,'CONTAM','GRUPO CONTABILIDAD (PLAN MEDIO)','S'),(1,'CONTAP','GRUPO CONTABILIDAD (PLAN PRO)','S'),(2,'CONTAP','GRUPO CONTABILIDAD (PLAN PRO)','S');
/*!40000 ALTER TABLE `grupos_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ident_personas`
--

DROP TABLE IF EXISTS `ident_personas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ident_personas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_persona` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_ident` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_vencimiento` datetime DEFAULT NULL,
  `observacion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_ident_personas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ident_personas`
--

LOCK TABLES `ident_personas` WRITE;
/*!40000 ALTER TABLE `ident_personas` DISABLE KEYS */;
INSERT INTO `ident_personas` VALUES (1,'5','RUC','80009085-3',NULL,'Registro'),(1,'4','RUC','2433384-0',NULL,'Registro'),(1,'2','RUC','5567097-0',NULL,'Registro'),(1,'3','RUC','80108040-1',NULL,'Registro');
/*!40000 ALTER TABLE `ident_personas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulos`
--

DROP TABLE IF EXISTS `modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_modulo` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maneja_calendario` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maneja_cierre` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden_menu` int DEFAULT NULL,
  `tipo_modulo` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_imagen` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_modulos_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulos`
--

LOCK TABLES `modulos` WRITE;
/*!40000 ALTER TABLE `modulos` DISABLE KEYS */;
INSERT INTO `modulos` VALUES (1,'CO','Contabilidad',NULL,NULL,'S',NULL,'M',NULL),(2,'CO','Contabilidad',NULL,NULL,'S',NULL,'M',NULL),(1,'AD','ADMINISTRACION',NULL,NULL,'S',2,'M','ph-buildings'),(2,'AD','ADMINISTRACION',NULL,NULL,'S',2,'M','ph-buildings');
/*!40000 ALTER TABLE `modulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monedas`
--

DROP TABLE IF EXISTS `monedas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monedas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_moneda` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `siglas` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_cambio_dia` decimal(20,6) DEFAULT NULL,
  `cod_pais` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimales` int DEFAULT NULL,
  `fec_tipo_cambio` datetime DEFAULT NULL,
  `tipo_cambio` decimal(20,6) DEFAULT NULL,
  `tipo_cambio_compra` decimal(20,6) DEFAULT NULL,
  `porc_interes` decimal(18,3) DEFAULT NULL,
  `tipo_cambio_contado` decimal(12,6) DEFAULT NULL,
  `tipo_cambio_credito` decimal(12,6) DEFAULT NULL,
  `cambio_para_diferencia` decimal(12,6) DEFAULT NULL,
  `cambio_diferencia_venta` decimal(12,6) DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_monedas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monedas`
--

LOCK TABLES `monedas` WRITE;
/*!40000 ALTER TABLE `monedas` DISABLE KEYS */;
INSERT INTO `monedas` VALUES (1,'6','DOLAR CANADIENSE','CAD',NULL,'CAN',3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'A'),(2,'6','DOLAR CANADIENSE','CAD',NULL,'CAN',3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'A'),(1,'1','GUARANIES','Gs',1.000000,'PAR',0,'9999-12-31 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,1.000000,1.000000,'A'),(2,'1','GUARANIES','Gs',1.000000,'PAR',0,'9999-12-31 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,1.000000,1.000000,'A'),(1,'2','DOLARES','USD',4050.000000,'USA',2,'3014-02-15 00:00:00',NULL,4041.000000,0.000,NULL,NULL,5000.000000,5000.000000,'A'),(2,'2','DOLARES','USD',4050.000000,'USA',2,'3014-02-15 00:00:00',NULL,4041.000000,0.000,NULL,NULL,5000.000000,5000.000000,'A'),(1,'3','REALES','R$',1.000000,'BRA',2,'2018-01-01 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,NULL,NULL,'A'),(2,'3','REALES','R$',1.000000,'BRA',2,'2018-01-01 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,NULL,NULL,'A'),(1,'4','EUROS','EU',1.000000,'ESP',2,'2018-01-01 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,NULL,NULL,'A'),(2,'4','EUROS','EU',1.000000,'ESP',2,'2018-01-01 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,NULL,NULL,'A'),(1,'5','PESOS ARG.','$',1.000000,'ARG',2,'2018-01-01 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,NULL,NULL,'A'),(2,'5','PESOS ARG.','$',1.000000,'ARG',2,'2018-01-01 00:00:00',NULL,1.000000,0.000,1.000000,1.000000,NULL,NULL,'A');
/*!40000 ALTER TABLE `monedas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos_propietarios`
--

DROP TABLE IF EXISTS `pagos_propietarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_propietarios` (
  `id_pago` int NOT NULL AUTO_INCREMENT,
  `id_propietario` int NOT NULL,
  `id_plan` int DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `monto` decimal(15,2) NOT NULL DEFAULT '0.00',
  `meses_renovados` int NOT NULL DEFAULT '1',
  `fec_vencimiento_anterior` date DEFAULT NULL,
  `fec_vencimiento_nueva` date NOT NULL,
  `comprobante` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_registro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`),
  KEY `idx_pagos_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos_propietarios`
--

LOCK TABLES `pagos_propietarios` WRITE;
/*!40000 ALTER TABLE `pagos_propietarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagos_propietarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personas`
--

DROP TABLE IF EXISTS `personas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_persona` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_per_fisica` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_per_juridica` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_fisica` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nomb_fantasia` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexo` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_nacimiento` datetime DEFAULT NULL,
  `nivel_estudios` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profesion` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sector` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_pais` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nacionalidad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_estado_civil` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conyugue` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_hijos` int DEFAULT NULL,
  `num_dependientes` int DEFAULT NULL,
  `total_ingresos` decimal(18,2) DEFAULT NULL,
  `es_mal_deudor` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direc_electronica` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagina_web` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_sociedad` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lucrativa` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estatal` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alta_por` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_alta` datetime DEFAULT NULL,
  `actualizado_por` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_actualizacion` datetime DEFAULT NULL,
  `referencia` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_fundacion` datetime DEFAULT NULL,
  `tiempo_residencia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_vivienda` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lugar_trabajo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sector_trab` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion_trab` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_trab` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `antiguedad` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_unipersonal` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_empresa` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cupo` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_comite` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sucursal_ref` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_ciudad_nacimiento` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_vendedor` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ivr` int DEFAULT NULL,
  `cod_procesadora` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_jefe` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_jefe` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_jefe` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_provee` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_provee` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_provee` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_foto` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_foto_2` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_foto_3` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_foto_4` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salario_hora` decimal(18,2) DEFAULT NULL,
  `ind_empleado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ci` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_personas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personas`
--

LOCK TABLES `personas` WRITE;
/*!40000 ALTER TABLE `personas` DISABLE KEYS */;
INSERT INTO `personas` VALUES (1,'4',NULL,NULL,'S','GONZALEZ CACERES, FRANCISCO RAMON',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-15 01:30:58',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2433384-0',NULL,NULL,NULL,NULL,'A','RUC'),(1,'5',NULL,NULL,'N','ELECTROPAR SA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-15 01:30:58',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'80009085-3',NULL,NULL,NULL,NULL,'A','RUC'),(1,'1',NULL,NULL,'S','ADMINISTRADOR DEL SISTEMA','ADMINISTRADOR DEL SISTEMA','M',NULL,NULL,NULL,'11',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'sabascabral@hotmail.com',NULL,NULL,'N','N','INV','2019-07-09 00:00:00','INV','2023-07-27 00:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'N',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,100000.00,'S',NULL,NULL,NULL,NULL,NULL,'A','RUC'),(1,'3',NULL,NULL,'S','DISTRIBUIDORA Y COMERCIAL SANABRIA SOCIEDAD ANONIMA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-15 01:00:05',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'80108040-1',NULL,NULL,NULL,NULL,'A','RUC'),(1,'2',NULL,NULL,'S','OJEDA SANDOVAL, CARLOS JAVIER',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 00:13:41',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'5567097-0',NULL,'avenida mariscal lopez y jose viñuales','0982965515',NULL,'A','RUC');
/*!40000 ALTER TABLE `personas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planes`
--

DROP TABLE IF EXISTS `planes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planes` (
  `id_plan` int NOT NULL AUTO_INCREMENT,
  `codigo_plan` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_plan` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_grupo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'CONTA',
  `max_contribuyentes` int NOT NULL DEFAULT '5',
  `max_usuarios` int NOT NULL DEFAULT '2',
  `precio_anterior` decimal(15,2) DEFAULT '0.00',
  `precio_mensual` decimal(15,2) DEFAULT '0.00',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
  `orden` int DEFAULT '1',
  PRIMARY KEY (`id_plan`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planes`
--

LOCK TABLES `planes` WRITE;
/*!40000 ALTER TABLE `planes` DISABLE KEYS */;
INSERT INTO `planes` VALUES (1,'BASICO','Plan Básico','CONTA',5,2,150000.00,140000.00,'Ideal para contadores independientes o pequeños negocios (hasta 5 contribuyentes y 2 usuarios)','A',1),(2,'MEDIO','Plan Medio','CONTAM',10,5,0.00,250000.00,'Para estudios contables en crecimiento (hasta 10 contribuyentes y 5 usuarios)','A',2),(3,'PRO','Plan Pro','CONTAP',30,15,0.00,450000.00,'Para estudios contables consolidados (hasta 30 contribuyentes y 15 usuarios)','A',3),(4,'ILIMITADO','Plan Corporativo / SuperAdmin','CONADM',9999,999,0.00,0.00,'Plan sin restricciones para administración general','A',4);
/*!40000 ALTER TABLE `planes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `propietarios`
--

DROP TABLE IF EXISTS `propietarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `propietarios` (
  `id_propietario` int NOT NULL AUTO_INCREMENT,
  `nombre_propietario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_plan` int NOT NULL DEFAULT '1',
  `max_contribuyentes` int NOT NULL DEFAULT '5',
  `max_usuarios` int NOT NULL DEFAULT '2',
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A',
  `fec_vencimiento` date DEFAULT NULL,
  `fec_alta` datetime DEFAULT CURRENT_TIMESTAMP,
  `observacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_ultimo_pago` date DEFAULT NULL,
  `dias_aviso` int NOT NULL DEFAULT '7',
  `dias_gracia` int NOT NULL DEFAULT '3',
  `mensaje_pago` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_propietario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `propietarios`
--

LOCK TABLES `propietarios` WRITE;
/*!40000 ALTER TABLE `propietarios` DISABLE KEYS */;
INSERT INTO `propietarios` VALUES (1,'JAVIER OJEDA','4193524-5','admin@contapro.com.py','+595',3,30,15,'A','2099-12-31','2026-10-15 21:04:37','Propietario Principal / SuperAdmin',NULL,7,3,NULL),(2,'JUAN PEREZ','8000000-1','juan.perez@ejemplo.com','+595',1,5,2,'I','2026-10-19','2026-10-15 21:04:37','Propietario 2 de demostración (Plan Básico)',NULL,7,3,'Contactar al WhatsApp +595 982 965 515 para renovar su suscripción.');
/*!40000 ALTER TABLE `propietarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursales`
--

DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursales` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sucursal` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_matriz` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `casilla_correo` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_postal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalle_dir` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trabaja_dom` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trabaja_sab` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_pais` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_provincia` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_ciudad` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_barrio` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_custodio_ini` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sucursal_central` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_patronal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_propio` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_um_def` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caracter` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_centro` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_cobrador` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sucursal_matriz` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_oculto` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_logo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_cliente` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_carga_fac` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_localidad` int DEFAULT NULL,
  `direc_electronica` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_recibe_rem` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actividad` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_sucursales_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursales`
--

LOCK TABLES `sucursales` WRITE;
/*!40000 ALTER TABLE `sucursales` DISABLE KEYS */;
INSERT INTO `sucursales` VALUES (1,'1','1','MATRIZ',NULL,'A',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S',NULL,NULL,'N',NULL,NULL),(1,'4','01','MATRIZ','N','A',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S',NULL,NULL,'N',NULL,NULL);
/*!40000 ALTER TABLE `sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telef_personas`
--

DROP TABLE IF EXISTS `telef_personas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telef_personas` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_persona` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_area` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_telefono` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tel_ubicacion` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interno` int DEFAULT NULL,
  `nota` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `por_defecto` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_direccion` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_linea_baja` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_telef_personas_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telef_personas`
--

LOCK TABLES `telef_personas` WRITE;
/*!40000 ALTER TABLE `telef_personas` DISABLE KEYS */;
INSERT INTO `telef_personas` VALUES (1,'2','+595','0982965515','M','C',NULL,NULL,'S',NULL,NULL,'Principal');
/*!40000 ALTER TABLE `telef_personas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_comprobantes`
--

DROP TABLE IF EXISTS `tipos_comprobantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_comprobantes` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_empresa` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_comprobante` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_modulo` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `abreviatura` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lineas` int DEFAULT NULL,
  `iva_incluido` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saldos` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caja` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_talonario` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desc_talonario` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_art` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_estado_art` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `libro_iva` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_cheque` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_origen` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_asientos` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_exento` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tip_movimiento` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_hechauka` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desc_impresion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_irp` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_ire` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo` int DEFAULT NULL,
  `ind_res_90` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_aranduka` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_tipos_comprobantes_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_comprobantes`
--

LOCK TABLES `tipos_comprobantes` WRITE;
/*!40000 ALTER TABLE `tipos_comprobantes` DISABLE KEYS */;
INSERT INTO `tipos_comprobantes` VALUES (1,'1','REC','CC','RECIBO','Recibo',999,'I',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'1','REC','CC','RECIBO','Recibo',999,'I',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1,'1','FCV','VT','Comprobante de Venta','Contado',999,'I','S','R','A','S','FAC',NULL,NULL,'V','S',NULL,'F','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(2,'1','FCV','VT','Comprobante de Venta','Contado',999,'I','S','R','A','S','FAC',NULL,NULL,'V','S',NULL,'F','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(1,'1','FCR','VT','FACTURA CREDITO','Credito',999,'I','S','R','A','S','FAC',NULL,NULL,'V','S',NULL,'F','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(2,'1','FCR','VT','FACTURA CREDITO','Credito',999,'I','S','R','A','S','FAC',NULL,NULL,'V','S',NULL,'F','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(1,'1','FCO','VT','FACTURA CONTADO','Contado',999,'I','S','R','A','S','FAC',NULL,NULL,'V','S',NULL,'F','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(2,'1','FCO','VT','FACTURA CONTADO','Contado',999,'I','S','R','A','S','FAC',NULL,NULL,'V','S',NULL,'F','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(1,'1','FAC','CM','Facturas Proveedor','FAC',NULL,'I','S','S','A',NULL,'IMP',NULL,NULL,'S','S',NULL,'C','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL),(2,'1','FAC','CM','Facturas Proveedor','FAC',NULL,'I','S','S','A',NULL,'IMP',NULL,NULL,'S','S',NULL,'C','S',NULL,NULL,NULL,NULL,NULL,NULL,109,'S',NULL);
/*!40000 ALTER TABLE `tipos_comprobantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_propietario` int NOT NULL DEFAULT '1',
  `cod_usuario` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_persona` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_grupo` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_empresa` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_sucursal` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_area` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_custodio` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_color_registro` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autoriza_stock` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autoriza_ctacte` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autoriza_contrato` int DEFAULT NULL,
  `autoriza_analisis` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autoriza_fijacion` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `e_mail` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo_administrador` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_cambio_clave` datetime DEFAULT NULL,
  `cod_autorizacion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_autoriza_precio` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_suc_oculto` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_stock_minimo` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ind_actualizado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ci` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_admin` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fec_nacimiento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_superadmin` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  KEY `idx_usuarios_prop` (`id_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'CONTA','1','CONTA','CONADM','1','1','A',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'N',NULL,NULL,NULL,NULL,NULL,'N',NULL,NULL,'S'),(2,'JUAN',NULL,'JUAN123','CONTA','1','01','A',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'JUAN','PEREZ',NULL,NULL,'S',NULL,NULL,'N');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'contapro'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-15 23:43:14
