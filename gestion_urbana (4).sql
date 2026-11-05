-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-11-2026 a las 21:31:51
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
-- Base de datos: `gestion_urbana`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `problemas`
--

CREATE TABLE `problemas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `prioridad` varchar(20) NOT NULL,
  `descripcion` text NOT NULL,
  `ubicacion` varchar(255) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `respuesta_admin` text DEFAULT NULL,
  `imagen_respuesta` varchar(255) DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `problemas`
--

INSERT INTO `problemas` (`id`, `usuario_id`, `codigo`, `titulo`, `categoria`, `prioridad`, `descripcion`, `ubicacion`, `imagen`, `estado`, `fecha_registro`, `respuesta_admin`, `imagen_respuesta`, `fecha_respuesta`) VALUES
(1, 1, 'URB-9E1D54', 'mircobasural', 'Alumbrado Público', 'Media', 'basura', 'av,inga', NULL, 'Pendiente', '2026-08-27 20:59:37', NULL, NULL, NULL),
(2, 1, 'URB-C9BFCF', 'tghfty', 'Alumbrado Público', 'Media', 'hhh', 'av,inga', 'uploads/1787864620_23f77322-9a3d-431c-af41-984ad6a27e64 (2).jfif', 'En Proceso', '2026-08-27 21:03:40', '', NULL, '2026-10-27 15:07:38'),
(4, 1, 'URB-0A1D0B', 'mircobasural', 'Calles y baches', 'Alta', 'no che', 'no che | Coordenadas: -17.773306, -63.242240', 'uploads/img_6ae9dd90a3e4d6.43056668.png', 'Solucionado', '2026-11-03 13:11:44', 'no molestes', NULL, '2026-11-03 09:15:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas`
--

CREATE TABLE `respuestas` (
  `id` int(11) NOT NULL,
  `problema_id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `respuestas`
--

INSERT INTO `respuestas` (`id`, `problema_id`, `usuario`, `mensaje`, `imagen`, `fecha`) VALUES
(3, 2, 'admin', 'tdtysyse', NULL, '2026-09-10 17:18:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `rol` varchar(20) NOT NULL DEFAULT 'usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `email`, `password`, `fecha_registro`, `rol`) VALUES
(1, 'ddd', 'dddd', 'ddd@sdff.dfsd', '$2y$10$P6uh2s8im0G6RqXDIlSMHuk4EmC/36ltZnL/3kUWMDaaUnM6/hZUu', '2026-08-27 20:53:41', 'usuario'),
(3, 'pablo aranibar', 'admin', 'pablo@gmail.com', '$2y$10$EwdFWaWEIhsmyBYB4qYsWe0LikUhwvmOy0RnIy.68hyWtn16Ew0uK', '2026-09-10 19:12:24', 'admin');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `problemas`
--
ALTER TABLE `problemas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `problema_id` (`problema_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `problemas`
--
ALTER TABLE `problemas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `problemas`
--
ALTER TABLE `problemas`
  ADD CONSTRAINT `problemas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD CONSTRAINT `respuestas_ibfk_1` FOREIGN KEY (`problema_id`) REFERENCES `problemas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
