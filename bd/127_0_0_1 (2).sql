-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-09-2026 a las 23:47:05
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gestor_cafe_recolectado`
--
CREATE DATABASE IF NOT EXISTS `gestor_cafe_recolectado` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestor_cafe_recolectado`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fincas`
--

CREATE TABLE `fincas` (
  `id_finca` int(11) NOT NULL,
  `id_caficultor` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ubicacion_vereda` varchar(150) DEFAULT NULL,
  `estado` enum('activa','en_pausa','inactiva') DEFAULT 'activa',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fincas`
--

INSERT INTO `fincas` (`id_finca`, `id_caficultor`, `nombre`, `ubicacion_vereda`, `estado`, `fecha_creacion`) VALUES
(1, 11, 'Finca La Esperanza', 'Vereda El Paraíso', 'activa', '2026-08-20 20:23:34'),
(6, 11, 'Juan Valdes', NULL, 'activa', '2026-08-13 13:35:13'),
(7, 11, 'las mil maravillas', 'primavera', 'activa', '2026-08-18 18:48:37'),
(8, 11, 'El mirador', 'Pedernal', 'activa', '2026-08-18 18:51:27'),
(9, 21, 'el recuerdo2', NULL, 'activa', '2026-09-12 14:11:12'),
(10, 20, 'el recuerdo 1', 'alto gualpi', 'activa', '2026-09-12 14:34:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_precios`
--

CREATE TABLE `historial_precios` (
  `id_precio` int(11) NOT NULL,
  `id_finca` int(11) NOT NULL,
  `precio_kilo` decimal(10,2) NOT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp(),
  `id_actualizado_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_precios`
--

INSERT INTO `historial_precios` (`id_precio`, `id_finca`, `precio_kilo`, `fecha_actualizacion`, `id_actualizado_por`) VALUES
(1, 6, 10000.00, '2026-08-18 15:44:11', 11),
(2, 6, 50000.00, '2026-08-18 15:44:24', 11),
(3, 6, 70000.00, '2026-08-18 15:44:40', 11),
(4, 6, 70000.00, '2026-08-18 15:44:43', 11),
(5, 6, 70000.00, '2026-08-18 15:44:45', 11),
(6, 6, 70000.00, '2026-08-18 15:44:46', 11),
(7, 6, 70000.00, '2026-08-18 15:44:47', 11),
(8, 6, 70000.00, '2026-08-18 15:44:48', 11),
(9, 6, 21400.00, '2026-08-18 15:44:54', 11),
(10, 6, 10000.00, '2026-08-18 16:00:33', 11),
(11, 6, 10000.00, '2026-08-18 16:00:36', 11),
(12, 6, 9000.00, '2026-08-20 14:25:01', 11),
(13, 1, 1000.00, '2026-08-15 08:00:00', 11),
(14, 1, 5000.00, '2026-08-22 15:29:35', 11),
(15, 1, 1000.00, '2026-08-22 17:27:30', 11),
(16, 1, 10000.00, '2026-08-27 13:40:42', 11),
(17, 1, 120000.00, '2026-08-27 13:47:35', 11),
(18, 1, 12354.00, '2026-08-27 14:00:42', 11),
(19, 6, 14000.00, '2026-08-27 14:03:28', 11),
(20, 7, 120000.00, '2026-09-01 14:33:24', 11),
(21, 10, 900.00, '2026-09-12 09:34:29', 20),
(22, 10, 800.00, '2026-09-12 10:07:38', 20),
(23, 9, 800.00, '2026-09-12 20:51:13', 21),
(24, 9, 1000.00, '2026-09-13 14:02:27', 21),
(25, 10, 1200.00, '2026-09-13 15:31:11', 20),
(26, 9, 1200.00, '2026-09-13 15:32:05', 21);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfiles_recolectores`
--

CREATE TABLE `perfiles_recolectores` (
  `id_usuario` int(11) NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `perfiles_recolectores`
--

INSERT INTO `perfiles_recolectores` (`id_usuario`, `foto_perfil`, `fecha_actualizacion`) VALUES
(23, 'uploads/perfiles/perfil_23_ff3f0c0fc6538324.jpg', '2026-09-13 20:49:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfiles_usuario`
--

CREATE TABLE `perfiles_usuario` (
  `id_usuario` int(11) NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `perfiles_usuario`
--

INSERT INTO `perfiles_usuario` (`id_usuario`, `foto_perfil`, `fecha_actualizacion`) VALUES
(21, 'uploads/perfiles/perfil_21_1790211579_6ab475fb8b9a1.jpg', '2026-09-24 00:59:39'),
(23, 'uploads/perfiles/usuario_23_20260913170458_7052b428.jpg', '2026-09-13 22:04:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_mayordomo`
--

CREATE TABLE `permisos_mayordomo` (
  `id_mayordomo` int(11) NOT NULL,
  `id_finca` int(11) NOT NULL,
  `registrar_recolecciones` tinyint(1) DEFAULT 0,
  `editar_recolecciones` tinyint(1) DEFAULT 0,
  `eliminar_recolectores` tinyint(1) DEFAULT 0,
  `ver_reportes` tinyint(1) DEFAULT 0,
  `gestionar_mayordomos` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos_mayordomo`
--

INSERT INTO `permisos_mayordomo` (`id_mayordomo`, `id_finca`, `registrar_recolecciones`, `editar_recolecciones`, `eliminar_recolectores`, `ver_reportes`, `gestionar_mayordomos`) VALUES
(19, 1, 1, 1, 1, 1, 1),
(20, 9, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recolecciones`
--

CREATE TABLE `recolecciones` (
  `id_recoleccion` int(11) NOT NULL,
  `id_recolector` int(11) NOT NULL,
  `id_finca` int(11) NOT NULL,
  `id_registrado_por` int(11) NOT NULL,
  `numero_entrega` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `kilos_recolectados` decimal(8,2) NOT NULL,
  `precio_kilo_aplicado` decimal(10,2) NOT NULL,
  `pago_calculado` decimal(12,2) NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado_pago` enum('pendiente','pagado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recolecciones`
--

INSERT INTO `recolecciones` (`id_recoleccion`, `id_recolector`, `id_finca`, `id_registrado_por`, `numero_entrega`, `fecha`, `kilos_recolectados`, `precio_kilo_aplicado`, `pago_calculado`, `observaciones`, `fecha_registro`, `estado_pago`) VALUES
(13, 12, 1, 11, 0, '2026-08-15 00:00:00', 85.50, 1000.00, 85500.00, 'Jornada mañana - Lote principal', '2026-08-15 22:30:00', 'pagado'),
(14, 12, 1, 11, 0, '2026-08-16 00:00:00', 90.00, 1000.00, 90000.00, 'Buena maduración del grano', '2026-08-16 23:00:00', 'pagado'),
(15, 19, 6, 11, 0, '2026-08-22 00:00:00', 100.00, 9000.00, 900000.00, '', '2026-08-22 20:42:12', 'pagado'),
(16, 19, 6, 11, 0, '2026-08-22 00:00:00', 200.00, 9000.00, 1800000.00, '', '2026-08-22 20:42:33', 'pendiente'),
(17, 19, 6, 11, 0, '2026-08-22 00:00:00', 400.00, 9000.00, 3600000.00, '', '2026-08-22 20:44:05', 'pendiente'),
(18, 19, 6, 11, 1, '2026-08-22 15:53:00', 1000.00, 9000.00, 9000000.00, '', '2026-08-22 20:53:24', 'pendiente'),
(19, 19, 6, 11, 2, '2026-08-22 15:53:00', 2000.00, 9000.00, 18000000.00, '', '2026-08-22 20:53:35', 'pendiente'),
(20, 19, 6, 11, 3, '2026-08-22 15:53:00', 4000.00, 9000.00, 36000000.00, '', '2026-08-22 20:53:52', 'pendiente'),
(21, 17, 7, 11, 1, '2026-08-22 17:26:00', 3000.00, 0.00, 0.00, '', '2026-08-22 22:26:53', 'pagado'),
(22, 17, 7, 11, 2, '2026-08-22 17:26:00', 200.00, 0.00, 0.00, '', '2026-08-22 22:26:53', 'pendiente'),
(23, 17, 7, 11, 3, '2026-08-22 17:26:00', 50.00, 0.00, 0.00, '', '2026-08-22 22:26:53', 'pendiente'),
(24, 17, 7, 11, 1, '2026-08-22 17:27:00', 1000.00, 0.00, 0.00, '', '2026-08-22 22:27:55', 'pendiente'),
(25, 17, 7, 11, 2, '2026-08-22 17:27:00', 2000.00, 0.00, 0.00, '', '2026-08-22 22:27:55', 'pendiente'),
(26, 17, 7, 11, 3, '2026-08-22 17:27:00', 250.00, 0.00, 0.00, '', '2026-08-22 22:27:55', 'pendiente'),
(27, 17, 7, 11, 1, '2026-08-27 13:39:00', 100.00, 0.00, 0.00, 'Exelente', '2026-08-27 18:40:07', 'pagado'),
(28, 17, 7, 11, 1, '2026-08-27 13:47:00', 123.00, 0.00, 0.00, '', '2026-08-27 18:47:51', 'pendiente'),
(29, 17, 7, 11, 1, '2026-08-27 13:52:00', 123.00, 0.00, 0.00, '', '2026-08-27 18:52:53', 'pendiente'),
(30, 17, 7, 11, 2, '2026-08-27 13:58:00', 1234.00, 0.00, 0.00, '', '2026-08-27 18:58:19', 'pagado'),
(31, 17, 7, 11, 1, '2026-08-27 14:03:00', 125.00, 0.00, 0.00, '', '2026-08-27 19:04:00', 'pagado'),
(32, 17, 7, 11, 1, '2026-09-01 13:57:00', 123456.00, 0.00, 0.00, '', '2026-09-01 18:57:37', 'pendiente'),
(33, 17, 7, 11, 2, '2026-09-01 13:57:00', 158.00, 0.00, 0.00, '', '2026-09-01 18:57:37', 'pendiente'),
(34, 17, 7, 11, 3, '2026-09-01 13:57:00', 250.00, 0.00, 0.00, '', '2026-09-01 18:57:37', 'pagado'),
(35, 17, 7, 11, 1, '2026-09-01 14:18:00', 123.00, 0.00, 0.00, '', '2026-09-01 19:18:35', 'pendiente'),
(36, 17, 7, 11, 2, '2026-09-01 14:18:00', 250.00, 0.00, 0.00, '', '2026-09-01 19:18:35', 'pagado'),
(37, 17, 7, 11, 1, '2026-09-01 14:32:00', 150.00, 0.00, 0.00, '', '2026-09-01 19:32:20', 'pagado'),
(38, 20, 9, 21, 1, '2026-09-13 09:31:00', 56.00, 800.00, 44800.00, '', '2026-09-13 14:32:26', 'pagado'),
(39, 20, 9, 21, 2, '2026-09-13 09:31:00', 89.00, 800.00, 71200.00, '', '2026-09-13 14:32:26', 'pagado'),
(40, 20, 9, 21, 3, '2026-09-13 09:31:00', 103.00, 800.00, 82400.00, '', '2026-09-13 14:32:26', 'pagado'),
(41, 20, 9, 21, 1, '2026-09-13 09:32:00', 74.00, 800.00, 59200.00, 'mala calidad', '2026-09-13 14:32:50', 'pagado'),
(42, 20, 9, 21, 1, '2026-09-13 09:35:00', 97.00, 800.00, 77600.00, '', '2026-09-13 14:35:23', 'pendiente'),
(43, 20, 9, 21, 1, '2026-09-13 09:35:00', 35.00, 800.00, 28000.00, '', '2026-09-13 14:35:44', 'pendiente'),
(44, 20, 9, 21, 2, '2026-09-13 09:35:00', 98.00, 800.00, 78400.00, '', '2026-09-13 14:35:44', 'pendiente'),
(45, 20, 9, 21, 3, '2026-09-13 09:35:00', 167.00, 800.00, 133600.00, '', '2026-09-13 14:35:44', 'pendiente'),
(46, 22, 9, 21, 1, '2026-09-13 10:49:00', 46.00, 800.00, 36800.00, 'mucho verde', '2026-09-13 15:50:29', 'pagado'),
(47, 22, 9, 21, 2, '2026-09-13 10:49:00', 84.00, 800.00, 67200.00, 'mucha hoja', '2026-09-13 15:50:29', 'pagado'),
(48, 22, 9, 21, 3, '2026-09-13 10:49:00', 200.00, 800.00, 160000.00, 'muy buena calidad', '2026-09-13 15:50:29', 'pagado'),
(49, 22, 9, 20, 1, '2026-09-13 12:11:00', 47.00, 800.00, 37600.00, '', '2026-09-13 17:12:12', 'pagado'),
(50, 22, 9, 20, 2, '2026-09-13 12:11:00', 85.00, 800.00, 68000.00, '', '2026-09-13 17:12:12', 'pagado'),
(51, 22, 9, 20, 3, '2026-09-13 12:11:00', 578.00, 800.00, 462400.00, '', '2026-09-13 17:12:12', 'pagado'),
(52, 22, 9, 21, 1, '2026-09-13 14:55:00', 64.00, 1000.00, 64000.00, '', '2026-09-13 19:55:37', 'pagado'),
(53, 22, 9, 21, 2, '2026-09-13 14:55:00', 36.00, 1000.00, 36000.00, '', '2026-09-13 19:55:37', 'pendiente'),
(54, 22, 9, 21, 3, '2026-09-13 14:55:00', 97.00, 1000.00, 97000.00, '', '2026-09-13 19:55:37', 'pendiente'),
(55, 22, 9, 21, 1, '2026-09-13 14:55:00', 648.00, 1000.00, 648000.00, '', '2026-09-13 19:55:56', 'pendiente'),
(56, 22, 9, 21, 2, '2026-09-13 14:55:00', 63489.00, 1000.00, 63489000.00, '', '2026-09-13 19:55:56', 'pendiente'),
(57, 22, 9, 21, 3, '2026-09-13 14:55:00', 476.00, 1000.00, 476000.00, '', '2026-09-13 19:55:56', 'pendiente'),
(58, 22, 9, 20, 1, '2026-09-13 14:56:00', 8509.00, 1000.00, 8509000.00, '', '2026-09-13 19:56:51', 'pendiente'),
(59, 22, 9, 20, 2, '2026-09-13 14:56:00', 849.00, 1000.00, 849000.00, '', '2026-09-13 19:56:51', 'pendiente'),
(60, 22, 9, 20, 3, '2026-09-13 14:56:00', 7689.00, 1000.00, 7689000.00, '', '2026-09-13 19:56:51', 'pendiente'),
(61, 23, 10, 21, 1, '2026-09-13 15:33:00', 64.00, 1200.00, 76800.00, '', '2026-09-13 20:33:35', 'pendiente'),
(62, 23, 10, 21, 2, '2026-09-13 15:33:00', 53.00, 1200.00, 63600.00, '', '2026-09-13 20:33:35', 'pendiente'),
(63, 23, 10, 21, 3, '2026-09-13 15:33:00', 96.00, 1200.00, 115200.00, '', '2026-09-13 20:33:35', 'pendiente'),
(64, 23, 10, 21, 1, '2026-09-13 15:33:00', 34.00, 1200.00, 40800.00, '', '2026-09-13 20:34:22', 'pendiente'),
(65, 23, 10, 21, 2, '2026-09-13 15:33:00', 87.00, 1200.00, 104400.00, '', '2026-09-13 20:34:22', 'pendiente'),
(66, 23, 10, 21, 3, '2026-09-13 15:33:00', 123.00, 1200.00, 147600.00, '', '2026-09-13 20:34:22', 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recolectores`
--

CREATE TABLE `recolectores` (
  `id_usuario` int(11) NOT NULL,
  `id_finca` int(11) NOT NULL,
  `fecha_vinculacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recolectores`
--

INSERT INTO `recolectores` (`id_usuario`, `id_finca`, `fecha_vinculacion`) VALUES
(11, 10, '2026-09-13 20:25:34'),
(12, 1, '2026-08-20 20:23:34'),
(17, 7, '2026-08-18 22:21:31'),
(19, 6, '2026-08-18 22:24:10'),
(20, 9, '2026-09-13 14:30:50'),
(22, 9, '2026-09-13 15:49:23'),
(23, 10, '2026-09-13 20:29:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `documento` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('caficultor','mayordomo','recolector') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `foto_perfil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `documento`, `nombres`, `apellidos`, `telefono`, `correo`, `usuario`, `contrasena`, `rol`, `estado`, `fecha_registro`, `foto_perfil`) VALUES
(11, '1084924015', 'TATIANA', 'PENAGOS', '3214691830', 'tatianasilvapenagos@gmail.com', 'Tatiana', '$2y$10$773PWDZ97qlHAidx8hejceRTN718d/Q6pDjEaIeMmuPB6EzQx6ple', 'caficultor', 'activo', '2026-08-13 13:35:13', NULL),
(12, '1084924000', 'CARLOS', 'LOPEZ', '3100000000', 'carlos@gmail.com', 'carlos_recolector', '$2y$10$abcdefghijklmnopqrstuuu', 'recolector', 'activo', '2026-08-20 20:23:34', NULL),
(17, '1004494015', 'Juan', 'Chezar', '3144002275', 'jdchezar@gmail.com', 'Jd', '$2y$10$N7Og.LxPgFYkEynrkakyjO1nOg4H6s8phbtJ5sj4ygBfKBkXJco9u', 'recolector', 'activo', '2026-08-18 22:19:07', NULL),
(19, '26593880', 'Tania', 'Penagos', '3122169062', 'taniapenagos2017@gmail.com', 'tania', '$2y$10$neG4vb4qH92daXm3PI1QseVsOA2jMndgPAmwTZFCt8YUYGWMQ6qwi', 'mayordomo', 'activo', '2026-08-18 22:24:10', NULL),
(20, '28964298', 'tomas', 'zapata', '346894494230', 'tomas@gmail.com', 'tomas', '$2y$10$DPQabbEgYbtJT/rTzz4AzOd8gW8BHZKlKChiKw6l7ij5CfHYZqWpq', 'mayordomo', 'activo', '2026-09-12 01:17:45', 'uploads/perfiles/perfil_20_1790803537_cb92a1a800.jpg'),
(21, '98623', 'milan', 'zapata', '0974402487', 'milan@gmail.com', 'milan', '$2y$10$vlskLQM/6WGpSjNsMZ9GeeY75dne9YkdhHrWPYfQQhD9FT2zmALzu', 'caficultor', 'activo', '2026-09-12 14:11:12', 'uploads/perfiles/perfil_21_1790208523_6ab46a0b02012.jpg'),
(22, '82640924', 'andres', 'sanchez', '498709470924', 'andres@gmail.com', 'andres', '$2y$10$LjWCF9wFjpYG5FJPpBiC3.U7yVUWDR35Y/fxx6vAGYQCCeFz5qQIy', 'recolector', 'activo', '2026-09-13 15:47:50', NULL),
(23, '1084923493', 'Tatiana', 'sanchez', '3144206051', 'tatiana@gmail.com', 'Tati', '$2y$10$ks1KmeD2ODmN28pDq6A8fOqxjnmpPYYappQJgViggvTg2kEb6gJcu', 'recolector', 'activo', '2026-09-13 20:28:10', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `fincas`
--
ALTER TABLE `fincas`
  ADD PRIMARY KEY (`id_finca`),
  ADD KEY `fk_fincas_caficultor` (`id_caficultor`);

--
-- Indices de la tabla `historial_precios`
--
ALTER TABLE `historial_precios`
  ADD PRIMARY KEY (`id_precio`),
  ADD KEY `fk_historial_finca` (`id_finca`),
  ADD KEY `fk_historial_usuario` (`id_actualizado_por`);

--
-- Indices de la tabla `perfiles_recolectores`
--
ALTER TABLE `perfiles_recolectores`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indices de la tabla `perfiles_usuario`
--
ALTER TABLE `perfiles_usuario`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indices de la tabla `permisos_mayordomo`
--
ALTER TABLE `permisos_mayordomo`
  ADD PRIMARY KEY (`id_mayordomo`,`id_finca`),
  ADD KEY `fk_permisos_finca` (`id_finca`);

--
-- Indices de la tabla `recolecciones`
--
ALTER TABLE `recolecciones`
  ADD PRIMARY KEY (`id_recoleccion`),
  ADD KEY `fk_recolecciones_recolector` (`id_recolector`),
  ADD KEY `fk_recolecciones_finca` (`id_finca`),
  ADD KEY `fk_recolecciones_registrador` (`id_registrado_por`);

--
-- Indices de la tabla `recolectores`
--
ALTER TABLE `recolectores`
  ADD PRIMARY KEY (`id_usuario`,`id_finca`),
  ADD KEY `fk_recolectores_finca` (`id_finca`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `fincas`
--
ALTER TABLE `fincas`
  MODIFY `id_finca` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `historial_precios`
--
ALTER TABLE `historial_precios`
  MODIFY `id_precio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `recolecciones`
--
ALTER TABLE `recolecciones`
  MODIFY `id_recoleccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `fincas`
--
ALTER TABLE `fincas`
  ADD CONSTRAINT `fk_fincas_caficultor` FOREIGN KEY (`id_caficultor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_precios`
--
ALTER TABLE `historial_precios`
  ADD CONSTRAINT `fk_historial_finca` FOREIGN KEY (`id_finca`) REFERENCES `fincas` (`id_finca`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_actualizado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `permisos_mayordomo`
--
ALTER TABLE `permisos_mayordomo`
  ADD CONSTRAINT `fk_permisos_finca` FOREIGN KEY (`id_finca`) REFERENCES `fincas` (`id_finca`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_permisos_mayordomo` FOREIGN KEY (`id_mayordomo`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `recolecciones`
--
ALTER TABLE `recolecciones`
  ADD CONSTRAINT `fk_recolecciones_finca` FOREIGN KEY (`id_finca`) REFERENCES `fincas` (`id_finca`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recolecciones_recolector` FOREIGN KEY (`id_recolector`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recolecciones_registrador` FOREIGN KEY (`id_registrado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `recolectores`
--
ALTER TABLE `recolectores`
  ADD CONSTRAINT `fk_recolectores_finca` FOREIGN KEY (`id_finca`) REFERENCES `fincas` (`id_finca`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recolectores_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
