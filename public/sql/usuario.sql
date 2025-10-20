-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-10-2025 a las 17:36:27
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
-- Base de datos: `gymbro`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `nombre` varchar(20) NOT NULL,
  `apellido1` varchar(20) NOT NULL,
  `apellido2` varchar(20) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `tipo` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id`, `email`, `password`, `nombre`, `apellido1`, `apellido2`, `telefono`, `direccion`, `tipo`) VALUES
(9, 'hola@mail.com', '1234', 'hola', 'mani', 'mano', '222222', 'nueva1', 'alumno'),
(10, 'profe@mail.com', '1234', 'profe', 'rodrifo', 'rodrifa', '111111111', 'calle ancha 2', 'profesor'),
(11, 'admin@gmail.com', '1234', 'admintrador', 'huga', 'hugo', '555555555', 'calle guitarra', 'administrador'),
(12, 'profe2@gmail.com', '1234', 'profe2', 'josan', 'sanchez', '3333333', 'nueva1', 'profesor'),
(13, 'profe3@gmail.com', '1234', 'Profe3', 'koko', 'kaka', '9999999', 'calle playa', 'profesor'),
(14, 'adios@mail.com', '1234', 'adios', 'hola', 'holo', '4444444', 'calle guitarra', 'alumno'),
(15, 'jorge@gmail.com', '1234', 'jorge', 'beiro', 'perdigones', '66666666', 'calle guitarra', 'alumno'),
(16, 'manuela@gmail.com', '1234', 'manuela', 'nunez', 'nunez', '55555555', 'calle ancha 2', 'alumno'),
(17, 'pepe@gmail.com', '1234', 'pepe', 'pepon', 'gutierrez', '6666666', 'av la granja', 'alumno');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
