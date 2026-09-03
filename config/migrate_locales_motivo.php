<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Add missing locales
    $db->exec("
        INSERT INTO locales (nombre, tipo) VALUES 
        ('Almacén Auxiliar', 'Almacen'),
        ('Taller de Producción', 'Almacen'),
        ('Distribuidor', 'Tienda')
        ON CONFLICT DO NOTHING;
    ");
    echo "Locales adicionales agregados.\n";

    // 2. Add 'motivo' field to transferencias
    $db->exec("
        ALTER TABLE transferencias ADD COLUMN IF NOT EXISTS motivo VARCHAR(100) DEFAULT 'Transferencia entre almacenes';
    ");
    echo "Campo 'motivo' agregado a transferencias.\n";

    echo "Migración completada con éxito.\n";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
