-- Creamos el usuario para la replicacion
CREATE USER IF NOT EXISTS 'replicador'@'%' IDENTIFIED WITH mysql_native_password BY 'repl123';
GRANT REPLICATION SLAVE ON *.* TO 'replicador'@'%';
FLUSH PRIVILEGES;

-- Creamos la base de datos
CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

-- Creamos tus 3 tablas
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    rol VARCHAR(50),
    email VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS catalogo_muebles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    articulo VARCHAR(100),
    precio DECIMAL(10,2),
    stock INT
);

CREATE TABLE IF NOT EXISTS catalogo_zapateria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modelo VARCHAR(100),
    tALLA VARCHAR(10),
    precio DECIMAL(10,2),
    stock INT
);

-- Insertamos los datos de prueba
INSERT INTO usuarios (nombre, rol, email) VALUES ('Prueba Final CETI', 'Usu', 'ceti@final.com');
INSERT INTO catalogo_muebles (articulo, precio, stock) VALUES ('Silla Gamer Ejecutiva', 2850.50, 15);
INSERT INTO catalogo_zapateria (modelo, tALLA, precio, stock) VALUES ('Tenis Deportivos Running', '27.5', 1200.00, 20);
