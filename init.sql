
-- Asegurar la contraseña del usuario root con una compleja
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'RootSuperSeguro2026!';
FLUSH PRIVILEGES;

-- 1. Creamos el usuario para la replicación (Maestro-Esclavo)
CREATE USER IF NOT EXISTS 'replicador'@'%' IDENTIFIED WITH mysql_native_password BY 'repl123';
GRANT REPLICATION SLAVE ON *.* TO 'replicador'@'%';
FLUSH PRIVILEGES;

-- 2. Creamos la base de datos de la tienda
CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

-- 3. Creamos tus 3 tablas oficiales
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

-- 4. Insertamos los datos de prueba iniciales
INSERT INTO usuarios (nombre, rol, email) VALUES ('Prueba Final CETI', 'Usu', 'ceti@final.com');
INSERT INTO catalogo_muebles (articulo, precio, stock) VALUES ('Silla Gamer Ejecutiva', 2850.50, 15);
INSERT INTO catalogo_zapateria (modelo, tALLA, precio, stock) VALUES ('Tenis Deportivos Running', '27.5', 1200.00, 20);

-- 5. ASEGURAR EL SGBD (Puntos del pizarrón del profe):
-- Creamos un usuario de aplicación con permisos limitados (Solo sobre la bd 'ecommerce' y no un *.* total)
CREATE USER IF NOT EXISTS 'app_user'@'%' IDENTIFIED WITH mysql_native_password BY 'PasswordSeguro123!';
GRANT SELECT, INSERT, UPDATE, DELETE ON ecommerce.* TO 'app_user'@'%';

-- Aplicamos los cambios de privilegios
FLUSH PRIVILEGES;
