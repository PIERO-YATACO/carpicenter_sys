<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // Table: contratos
    $db->exec("
        CREATE TABLE IF NOT EXISTS contratos (
            id SERIAL PRIMARY KEY,
            serie VARCHAR(20) DEFAULT 'T003',
            numero VARCHAR(20) NOT NULL,
            codigo_completo VARCHAR(50) UNIQUE,
            cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
            vendedor_id INT REFERENCES usuarios(id) ON DELETE SET NULL,
            local_id INT REFERENCES locales(id) ON DELETE SET NULL,
            fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_entrega_estimada DATE,
            tipo_entrega VARCHAR(50) DEFAULT 'Recojo en Tienda',
            direccion_entrega TEXT,
            referencia_entrega TEXT,
            instalacion_incluida BOOLEAN DEFAULT FALSE,
            prioridad VARCHAR(20) DEFAULT 'Normal',
            estado_contrato VARCHAR(50) DEFAULT 'Pendiente',
            estado_produccion VARCHAR(50) DEFAULT 'Pendiente',
            monto_total NUMERIC(10,2) DEFAULT 0.00,
            monto_adelanto NUMERIC(10,2) DEFAULT 0.00,
            monto_saldo NUMERIC(10,2) DEFAULT 0.00,
            metodo_pago VARCHAR(50) DEFAULT 'Efectivo',
            observaciones_generales TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Table: contrato_detalles
    $db->exec("
        CREATE TABLE IF NOT EXISTS contrato_detalles (
            id SERIAL PRIMARY KEY,
            contrato_id INT REFERENCES contratos(id) ON DELETE CASCADE,
            producto_id INT REFERENCES productos(id) ON DELETE SET NULL,
            descripcion VARCHAR(255) NOT NULL,
            color_id INT REFERENCES colores(id) ON DELETE SET NULL,
            cantidad INT NOT NULL DEFAULT 1,
            precio_unitario NUMERIC(10,2) NOT NULL DEFAULT 0.00,
            subtotal NUMERIC(10,2) NOT NULL DEFAULT 0.00,
            observaciones_item TEXT
        );
    ");

    // Table: contrato_abonos (Historial de pagos a cuenta)
    $db->exec("
        CREATE TABLE IF NOT EXISTS contrato_abonos (
            id SERIAL PRIMARY KEY,
            contrato_id INT REFERENCES contratos(id) ON DELETE CASCADE,
            monto NUMERIC(10,2) NOT NULL,
            metodo_pago VARCHAR(50) DEFAULT 'Efectivo',
            observacion VARCHAR(255),
            usuario_id INT REFERENCES usuarios(id),
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $db->commit();
    echo "Tablas de contratos migradas con éxito.\n";

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error en migración de contratos: " . $e->getMessage() . "\n");
}
