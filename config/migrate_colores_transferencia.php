<?php
require_once __DIR__ . '/db.php';

try {
    $db->exec("
        TRUNCATE TABLE transferencia_detalles RESTART IDENTITY CASCADE;
        TRUNCATE TABLE transferencias RESTART IDENTITY CASCADE;
        TRUNCATE TABLE inventario_local RESTART IDENTITY CASCADE;

        ALTER TABLE inventario_local DROP CONSTRAINT IF EXISTS inventario_local_producto_id_local_id_key;
        ALTER TABLE inventario_local ADD COLUMN IF NOT EXISTS color_id INT REFERENCES colores(id) ON DELETE CASCADE;
        ALTER TABLE inventario_local ADD CONSTRAINT inventario_local_prod_loc_col_key UNIQUE(producto_id, local_id, color_id);

        ALTER TABLE transferencia_detalles ADD COLUMN IF NOT EXISTS color_id INT REFERENCES colores(id);

        INSERT INTO inventario_local (producto_id, color_id, local_id, stock_actual)
        SELECT pc.producto_id, pc.color_id, (SELECT id FROM locales WHERE nombre = 'Almacén Principal' LIMIT 1), pc.stock
        FROM producto_colores pc
        WHERE pc.stock > 0
        ON CONFLICT DO NOTHING;
    ");
    echo "Migración de colores en transferencias completada.\n";
} catch (PDOException $e) {
    die("Error en migración: " . $e->getMessage());
}
?>
