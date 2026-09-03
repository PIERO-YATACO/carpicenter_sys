<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Add fields to 'ventas' table
    $db->exec("
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS tipo_comprobante VARCHAR(20);
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS serie VARCHAR(10);
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS numero VARCHAR(20);
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS fecha_emision DATE;
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS fecha_pago DATE;
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado_pago VARCHAR(20) DEFAULT 'PENDIENTE';
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS cotizacion_id INT REFERENCES cotizaciones(id) ON DELETE SET NULL;
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado_sunat VARCHAR(20) DEFAULT 'NO_ENVIADO';
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS sunat_ticket VARCHAR(100);
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS sunat_hash VARCHAR(100);
        ALTER TABLE ventas ADD COLUMN IF NOT EXISTS sunat_response TEXT;
    ");

    // 2. Create 'guias_remision' table
    $db->exec("
        CREATE TABLE IF NOT EXISTS guias_remision (
            id SERIAL PRIMARY KEY,
            codigo VARCHAR(20) UNIQUE NOT NULL,
            venta_id INT REFERENCES ventas(id) ON DELETE SET NULL,
            cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
            fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            estado_facturacion VARCHAR(20) DEFAULT 'NO_FACTURADA',
            estado VARCHAR(20) DEFAULT 'Emitida',
            destinatario_nombre VARCHAR(255) NOT NULL,
            destinatario_documento VARCHAR(50),
            punto_partida TEXT,
            punto_llegada TEXT,
            motivo_traslado VARCHAR(100) DEFAULT 'Venta',
            observaciones TEXT
        );
    ");

    $db->commit();
    echo "Migración completada con éxito.\n";
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error durante la migración: " . $e->getMessage() . "\n");
}
?>
