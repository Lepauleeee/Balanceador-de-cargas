CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

-- Tabla de Usuarios (Confidencialidad / Roles)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50),
    rol ENUM('Admin', 'Usu'),
    email VARCHAR(50)
);

-- Catálogo de Muebles
CREATE TABLE IF NOT EXISTS catalogo_muebles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    articulo VARCHAR(100),
    precio DECIMAL(10,2),
    stock INT
);

-- Catálogo de Zapatería
CREATE TABLE IF NOT EXISTS catalogo_zapateria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modelo VARCHAR(100),
    talla VARCHAR(10),
    precio DECIMAL(10,2)
);

-- Pasarela de Pagos (Confidencialidad)
CREATE TABLE IF NOT EXISTS pasarela_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    monto DECIMAL(10,2),
    estado VARCHAR(20),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserción de datos demo
INSERT INTO usuarios (nombre, rol, email) VALUES 
('Admin_Principal', 'Admin', 'admin@tienda.com'),
('Cliente_1', 'Usu', 'cliente@gmail.com');

INSERT INTO catalogo_muebles (articulo, precio, stock) VALUES 
('Comedor de Madera 6 Sillas', 7500.00, 10),
('Sillón Reclinable', 4200.50, 5);

INSERT INTO catalogo_zapateria (modelo, talla, precio) VALUES 
('Bota de Piel Casual', '27.5', 1250.00),
('Tenis Deportivos', '28.0', 980.00);

INSERT INTO pasarela_pagos (id_usuario, monto, estado) VALUES 
(2, 1250.00, 'Aprobado');

-- Usuario con Mínimo Privilegio (C de CIA)
CREATE USER IF NOT EXISTS 'cliente_web'@'%' IDENTIFIED BY 'PublicPass123!';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'cliente_web'@'%';
GRANT SELECT ON ecommerce.catalogo_muebles TO 'cliente_web'@'%';
GRANT SELECT ON ecommerce.catalogo_zapateria TO 'cliente_web'@'%';
FLUSH PRIVILEGES;
