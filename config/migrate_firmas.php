<?php
require_once __DIR__ . '/db.php';
try {
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS firma_token VARCHAR(100)");
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS firma_digital TEXT");
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS tipo_documento VARCHAR(50)");
    echo "Columnas agregadas con exito.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
