
CREATE DATABASE IF NOT EXISTS escuela_crud
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE escuela_crud;

CREATE TABLE IF NOT EXISTS alumnos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    edad TINYINT UNSIGNED NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO alumnos (nombre, correo, edad)
VALUES
    ('Andrea López', 'andrea@ejemplo.com', 18),
    ('Luis Hernández', 'luis@ejemplo.com', 17);