CREATE TABLE IF NOT EXISTS locales (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) DEFAULT 'Tienda',
    direccion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO locales (nombre, tipo) VALUES 
('Almacén Principal', 'Almacen'), 
('Tienda 1', 'Tienda'), 
('Tienda 2', 'Tienda'), 
('Tienda 3', 'Tienda'), 
('Tienda 4', 'Tienda')
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS inventario_local (
    id SERIAL PRIMARY KEY,
    producto_id INT REFERENCES productos(id) ON DELETE CASCADE,
    local_id INT REFERENCES locales(id) ON DELETE CASCADE,
    stock_actual INT DEFAULT 0,
    UNIQUE(producto_id, local_id)
);

CREATE TABLE IF NOT EXISTS transferencias (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    local_origen_id INT REFERENCES locales(id),
    local_destino_id INT REFERENCES locales(id),
    estado VARCHAR(20) DEFAULT 'En Tránsito',
    usuario_envia_id INT,
    usuario_recibe_id INT,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_recepcion TIMESTAMP,
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS transferencia_detalles (
    id SERIAL PRIMARY KEY,
    transferencia_id INT REFERENCES transferencias(id) ON DELETE CASCADE,
    producto_id INT REFERENCES productos(id),
    cantidad_enviada INT NOT NULL,
    cantidad_recibida INT DEFAULT 0
);
