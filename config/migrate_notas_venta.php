<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Crear tabla notas_venta si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS notas_venta (
            id SERIAL PRIMARY KEY,
            numero VARCHAR(20) UNIQUE NOT NULL,
            fecha DATE NOT NULL,
            cliente_nombre VARCHAR(255) NOT NULL,
            cliente_documento VARCHAR(50),
            cliente_direccion TEXT,
            cliente_telefono VARCHAR(50),
            vendedor VARCHAR(100) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            metodo_pago VARCHAR(50) NOT NULL,
            observaciones TEXT,
            estado VARCHAR(20) DEFAULT 'Activa',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Crear tabla notas_venta_detalle si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS notas_venta_detalle (
            id SERIAL PRIMARY KEY,
            nota_id INT REFERENCES notas_venta(id) ON DELETE CASCADE,
            cantidad DECIMAL(10,2) NOT NULL,
            descripcion TEXT NOT NULL,
            precio_unitario DECIMAL(10,2) NOT NULL,
            importe DECIMAL(10,2) NOT NULL
        );
    ");

    $db->commit();
    echo "Migración de Notas de Venta completada con éxito.\n";
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error durante la migración de Notas de Venta: " . $e->getMessage() . "\n");
}
?>
