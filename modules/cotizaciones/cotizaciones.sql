CREATE TABLE IF NOT EXISTS cotizaciones (
    id SERIAL PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    cliente_nombre VARCHAR(255) NOT NULL,
    cliente_documento VARCHAR(50),
    cliente_direccion TEXT,
    fecha DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_validez DATE,
    total NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    observaciones TEXT,
    condiciones TEXT,
    gastos_logisticos NUMERIC(10, 2) DEFAULT 0.00,
    modificacion_orden_compra NUMERIC(10, 2) DEFAULT 0.00,
    movilidad NUMERIC(10, 2) DEFAULT 0.00,
    usuario_id INT,
    estado VARCHAR(20) DEFAULT 'Pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cotizacion_detalle (
    id SERIAL PRIMARY KEY,
    cotizacion_id INT REFERENCES cotizaciones(id) ON DELETE CASCADE,
    producto_id INT,
    descripcion VARCHAR(255) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario NUMERIC(10, 2) NOT NULL,
    subtotal NUMERIC(10, 2) NOT NULL,
    especificaciones TEXT,
    imagen VARCHAR(255)
);
