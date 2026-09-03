<?php
require_once __DIR__ . '/db.php';

try {
    $sql = "ALTER TABLE cotizacion_detalle ADD COLUMN IF NOT EXISTS especificaciones TEXT;";
    $db->exec($sql);
    echo "Columna especificaciones agregada con éxito.";
} catch (PDOException $e) {
    die("Error al alterar tabla: " . $e->getMessage());
}
?>
