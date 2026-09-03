<?php
require_once __DIR__ . '/db.php';

try {
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS gastos_logisticos NUMERIC(10, 2) DEFAULT 0.00;");
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS modificacion_orden_compra NUMERIC(10, 2) DEFAULT 0.00;");
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS movilidad NUMERIC(10, 2) DEFAULT 0.00;");
    echo "Migración completada con éxito.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
