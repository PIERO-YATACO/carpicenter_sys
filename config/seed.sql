-- =============================================
-- CARPICENTER - Datos de demostración
-- Ejecutar en la base de datos carpicenter_db
-- =============================================

-- Limpiar datos existentes (en orden por dependencias)
TRUNCATE TABLE receta_detalles, recetas, auditoria, usuario_roles, compra_detalles, venta_detalles, kardex, compras, ventas, productos, materiales, categorias, proveedores, clientes, usuarios, roles RESTART IDENTITY CASCADE;

-- ===== ROLES =====
INSERT INTO roles (nombre) VALUES
('Super Admin'),
('Vendedor'),
('Almacén'),
('Producción');

-- ===== USUARIOS =====
INSERT INTO usuarios (username, password, estado) VALUES
('admin', 'admin123', 'Activo'),
('jflores', 'jflores123', 'Activo'),
('mlopez', 'mlopez123', 'Activo'),
('pramirez', 'pramirez123', 'Inactivo');

-- ===== USUARIO_ROLES =====
INSERT INTO usuario_roles (usuario_id, rol_id) VALUES
(1, 1), (2, 2), (3, 3), (4, 2);

-- ===== CATEGORIAS =====
INSERT INTO categorias (nombre) VALUES
('Mesas'), ('Sillas'), ('Sofás'), ('Camas'), ('Estantes'), ('Escritorios'), ('Juegos de Comedor');

-- ===== PROVEEDORES =====
INSERT INTO proveedores (nombre, ruc, telefono, direccion) VALUES
('Maderas del Perú S.A.C.', '20456789012', '981854329', 'Av. Industrial 456, Ate, Lima'),
('Ferretería Central S.A.C.', '20567890123', '912345678', 'Jr. Comercio 789, SJL, Lima'),
('Tapicería Lima E.I.R.L.', '20678901234', '923465789', 'Av. Grau 321, La Victoria, Lima'),
('Muebles y Más S.R.L.', '20789012345', '934561890', 'Calle Los Pinos 654, Surco, Lima'),
('Química Central S.A.', '20890123456', '945678912', 'Av. Argentina 1100, Callao'),
('Maderas del Sur S.A.C.', '20901234567', '956789123', 'Jr. Puno 890, Juliaca'),
('Import Telas Perú S.A.C.', '20112345678', '967891234', 'Av. Aviación 2345, San Borja, Lima');

-- ===== CLIENTES =====
INSERT INTO clientes (nombre, dni_ruc, telefono) VALUES
('Juan Flores Mendoza', '45678912', '981854329'),
('Maria López García', '32145698', '912345678'),
('Pedro Ramírez Torres', '78912345', '923465789'),
('Ana Torres Vega', '65432198', '934561890'),
('Carlos Gómez Luna', '98765432', '945678912'),
('Lucía Fernández Ríos', '12345678', '956789123'),
('Roberto Díaz Sánchez', '87654321', '967891234'),
('Carmen Ruiz Palacios', '23456789', '978912345'),
('Miguel Herrera Castro', '34567891', '989123456'),
('Patricia Vargas Rojas', '56789123', '991234567');

-- ===== MATERIALES =====
INSERT INTO materiales (nombre, unidad, stock_actual, stock_minimo) VALUES
('Madera Pino', 'm²', 45.50, 20.00),
('Madera Roble', 'm²', 22.00, 10.00),
('Madera Cedro', 'm²', 18.30, 15.00),
('Tinte Nogal', 'L', 3.50, 5.00),
('Tinte Caoba', 'L', 8.20, 5.00),
('Barniz Brillante', 'L', 12.00, 8.00),
('Tornillos 2"', 'kg', 8.20, 5.00),
('Clavos 1"', 'kg', 1.80, 2.00),
('Bisagras Medianas', 'und', 45.00, 20.00),
('Espuma HD 2"', 'm²', 15.00, 10.00),
('Tela Tapiz Gris', 'm', 25.00, 15.00),
('Tela Tapiz Beige', 'm', 18.50, 15.00),
('Pegamento Industrial', 'L', 6.00, 4.00),
('Lija #120', 'und', 30.00, 20.00),
('Lija #220', 'und', 25.00, 20.00);

-- ===== COLORES =====
INSERT INTO colores (nombre) VALUES
('BLANCO'), ('NEGRO'), ('TAUPE'), ('ROJO'), ('AMARILLO'), ('VERDE LIMON'), ('VERDE PASTEL'), ('CELESTE'), ('GRIS OSCURO'), ('GRIS CLARO'), ('AZUL'), ('DUNA'), ('MARRON (VIDRIO)'), ('ROSADO'), ('VERDE'), ('NARANJA'), ('TORTORA(TAUPE)'), ('TURQUESA CLARO'), ('TURQUESA OSCURO');

-- ===== PRODUCTOS =====
INSERT INTO productos (nombre, categoria_id, precio_compra, precio_venta, stock_minimo, es_fabricado, fecha_creacion) VALUES
('Mesa Moderna Pino', 1, 180.00, 350.00, 5, true, '2024-01-15'),
('Mesa Comedor Roble', 1, 320.00, 580.00, 8, 3, true, '2024-01-20'),
('Mesa Centro Cedro', 1, 150.00, 290.00, 15, 5, true, '2024-02-01'),
('Silla Tapizada Clásica', 2, 85.00, 180.00, 45, 10, true, '2024-01-10'),
('Silla Ejecutiva', 2, 120.00, 250.00, 20, 8, true, '2024-02-15'),
('Silla Comedor Simple', 2, 60.00, 130.00, 35, 10, true, '2024-01-25'),
('Sofá 3 Cuerpos', 3, 650.00, 1350.00, 5, 2, true, '2024-02-10'),
('Sofá 2 Cuerpos', 3, 480.00, 950.00, 7, 3, true, '2024-02-12'),
('Sofá Esquinero', 3, 850.00, 1800.00, 3, 2, true, '2024-03-01'),
('Cama Queen Tapizada', 4, 520.00, 1100.00, 4, 2, true, '2024-01-30'),
('Cama King Premium', 4, 700.00, 1500.00, 3, 2, true, '2024-02-20'),
('Cama 1.5 Plaza', 4, 280.00, 580.00, 10, 4, true, '2024-02-25'),
('Estante Flotante 3 Niv.', 5, 65.00, 175.00, 18, 8, true, '2024-01-18'),
('Estante Pared Roble', 5, 95.00, 220.00, 12, 5, true, '2024-02-05'),
('Escritorio Ejecutivo', 6, 250.00, 480.00, 6, 3, true, '2024-03-05'),
('Escritorio Gamer', 6, 200.00, 420.00, 8, 3, true, '2024-03-10'),
('Comedor 6 Sillas Roble', 7, 800.00, 1650.00, 3, 2, true, '2024-02-01'),
('Comedor 4 Sillas Pino', 7, 550.00, 1100.00, 5, 2, true, '2024-02-15');

-- ===== VENTAS =====
INSERT INTO ventas (cliente_id, fecha, total, estado) VALUES
(1, '2024-03-15 10:30:00', 1500.00, 'Completada'),
(2, '2024-03-14 14:20:00', 2350.00, 'Completada'),
(3, '2024-03-13 09:15:00', 1710.00, 'Completada'),
(4, '2024-03-12 16:45:00', 580.00, 'Pendiente'),
(5, '2024-03-11 11:00:00', 2300.00, 'Completada'),
(6, '2024-03-10 13:30:00', 950.00, 'Completada'),
(7, '2024-03-09 10:00:00', 1800.00, 'Completada'),
(8, '2024-03-08 15:20:00', 350.00, 'Cancelada'),
(9, '2024-03-07 09:45:00', 1100.00, 'Completada'),
(10, '2024-03-06 14:00:00', 480.00, 'Completada'),
(1, '2024-02-28 10:15:00', 1350.00, 'Completada'),
(2, '2024-02-25 11:30:00', 2200.00, 'Completada'),
(3, '2024-02-20 16:00:00', 870.00, 'Completada'),
(5, '2024-02-15 09:30:00', 1650.00, 'Completada'),
(6, '2024-02-10 13:45:00', 420.00, 'Completada'),
(1, '2024-01-28 10:00:00', 1100.00, 'Completada'),
(4, '2024-01-20 14:30:00', 580.00, 'Completada'),
(7, '2024-01-15 11:15:00', 950.00, 'Completada'),
(8, '2024-01-10 09:00:00', 290.00, 'Completada'),
(9, '2024-01-05 15:30:00', 1800.00, 'Completada');

-- ===== VENTA DETALLES =====
INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_historico) VALUES
(1, 1, 2, 350.00), (1, 4, 4, 180.00),
(2, 7, 1, 1350.00), (2, 13, 4, 175.00),
(3, 10, 1, 1100.00), (3, 4, 3, 180.00),
(4, 13, 2, 175.00), (4, 14, 1, 220.00),
(5, 9, 1, 1800.00), (5, 6, 3, 130.00),
(6, 8, 1, 950.00),
(7, 9, 1, 1800.00),
(8, 1, 1, 350.00),
(9, 10, 1, 1100.00),
(10, 15, 1, 480.00),
(11, 7, 1, 1350.00),
(12, 17, 1, 1650.00), (12, 4, 3, 180.00),
(13, 3, 3, 290.00),
(14, 17, 1, 1650.00),
(15, 16, 1, 420.00),
(16, 10, 1, 1100.00),
(17, 12, 1, 580.00),
(18, 8, 1, 950.00),
(19, 3, 1, 290.00),
(20, 9, 1, 1800.00);

-- ===== COMPRAS =====
INSERT INTO compras (proveedor_id, fecha, total) VALUES
(1, '2024-03-15 08:00:00', 1450.00),
(2, '2024-03-12 09:30:00', 1280.00),
(3, '2024-03-10 10:00:00', 580.00),
(6, '2024-03-08 11:30:00', 1650.00),
(1, '2024-03-05 08:00:00', 980.00),
(5, '2024-03-01 14:00:00', 320.00),
(7, '2024-02-25 09:00:00', 750.00),
(2, '2024-02-20 10:30:00', 460.00);

-- ===== COMPRA DETALLES =====
INSERT INTO compra_detalles (compra_id, material_id, producto_id, cantidad, precio_unitario) VALUES
(1, 1, NULL, 20.00, 24.80), (1, 2, NULL, 10.00, 45.00), (1, NULL, NULL, 5.00, 12.00),
(2, 7, NULL, 5.00, 12.00), (2, 8, NULL, 3.00, 8.50), (2, 9, NULL, 30.00, 35.00),
(3, 10, NULL, 8.00, 35.00), (3, 11, NULL, 12.00, 18.50),
(4, 3, NULL, 25.00, 42.00), (4, 1, NULL, 15.00, 24.80),
(5, 1, NULL, 15.00, 24.80), (5, 6, NULL, 10.00, 28.00),
(6, 13, NULL, 8.00, 22.00), (6, 14, NULL, 20.00, 3.50),
(7, 11, NULL, 15.00, 18.50), (7, 12, NULL, 20.00, 16.00),
(8, 7, NULL, 8.00, 12.00), (8, 9, NULL, 25.00, 14.00);

-- ===== KARDEX =====
INSERT INTO kardex (tipo_movimiento, producto_id, material_id, cantidad, stock_resultante, motivo, fecha, usuario_id) VALUES
('Entrada', 1, NULL, 10, 12, 'Producción completada', '2024-03-20 10:00:00', 1),
('Salida', 1, NULL, 2, 10, 'Venta VTA-001', '2024-03-15 10:30:00', 2),
('Entrada', 4, NULL, 20, 45, 'Producción completada', '2024-03-18 09:00:00', 1),
('Salida', 4, NULL, 4, 41, 'Venta VTA-001', '2024-03-15 10:30:00', 2),
('Salida', 7, NULL, 1, 5, 'Venta VTA-002', '2024-03-14 14:20:00', 2),
('Entrada', 7, NULL, 3, 8, 'Producción completada', '2024-03-10 08:00:00', 1),
('Salida', 10, NULL, 1, 4, 'Venta VTA-003', '2024-03-13 09:15:00', 2),
('Entrada', NULL, 1, 20, 45.5, 'Compra COM-001', '2024-03-15 08:00:00', 3),
('Entrada', NULL, 2, 10, 22, 'Compra COM-001', '2024-03-15 08:00:00', 3),
('Salida', NULL, 1, 5, 40.5, 'Uso en producción', '2024-03-16 09:00:00', 3),
('Entrada', NULL, 4, 10, 13.5, 'Compra COM-005', '2024-03-05 08:00:00', 3),
('Salida', NULL, 4, 10, 3.5, 'Uso en producción', '2024-03-12 11:00:00', 3),
('Salida', 9, NULL, 1, 3, 'Venta VTA-005', '2024-03-11 11:00:00', 2),
('Entrada', 13, NULL, 10, 18, 'Producción completada', '2024-03-08 09:00:00', 1),
('Salida', 13, NULL, 4, 14, 'Venta VTA-002', '2024-03-14 14:20:00', 2),
('Ajuste', 8, NULL, 2, 7, 'Ajuste de inventario físico', '2024-03-05 15:00:00', 1);

-- ===== RECETAS =====
INSERT INTO recetas (producto_id, descripcion_proceso) VALUES
(1, 'Cortar madera pino según medidas, ensamblar estructura, lijar, aplicar tinte y barniz'),
(4, 'Cortar madera, tornear patas, tapizar asiento con espuma y tela, ensamblar'),
(7, 'Construir estructura madera, colocar espuma HD, tapizar con tela seleccionada');

-- ===== RECETA DETALLES =====
INSERT INTO receta_detalles (receta_id, material_id, cantidad) VALUES
(1, 1, 2.50), (1, 4, 0.50), (1, 6, 0.30), (1, 7, 0.20), (1, 14, 2.00),
(2, 1, 1.00), (2, 10, 0.50), (2, 11, 1.00), (2, 7, 0.10), (2, 13, 0.05),
(3, 1, 4.00), (3, 10, 3.00), (3, 11, 5.00), (3, 7, 0.30), (3, 13, 0.20);

-- ===== AUDITORIA =====
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, detalle, fecha) VALUES
(1, 'INSERT', 'productos', 'Creó producto: Mesa Moderna Pino', '2024-01-15 10:00:00'),
(2, 'INSERT', 'ventas', 'Registró venta VTA-001 por S/ 1,500.00', '2024-03-15 10:30:00'),
(3, 'INSERT', 'compras', 'Registró compra COM-001 por S/ 1,450.00', '2024-03-15 08:00:00'),
(1, 'UPDATE', 'productos', 'Actualizó stock de Mesa Moderna Pino', '2024-03-20 10:00:00'),
(3, 'INSERT', 'kardex', 'Registró entrada de Madera Pino x 20 m²', '2024-03-15 08:00:00');
