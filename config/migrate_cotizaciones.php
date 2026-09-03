<?php
require_once __DIR__ . '/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../modules/cotizaciones/cotizaciones.sql');
    $db->exec($sql);
    $db->exec("ALTER TABLE cotizaciones ADD COLUMN IF NOT EXISTS cliente_telefono VARCHAR(50);");
    $db->exec("ALTER TABLE cotizacion_detalle ADD COLUMN IF NOT EXISTS color VARCHAR(100);");
    echo "Tablas de cotizaciones actualizadas con éxito.";
} catch (PDOException $e) {
    die("Error al actualizar tablas: " . $e->getMessage());
}
?>
