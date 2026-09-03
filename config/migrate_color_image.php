<?php
require_once 'db.php';

try {
    $db->exec("ALTER TABLE producto_colores ADD COLUMN imagen_url VARCHAR(255) DEFAULT NULL");
    echo "Columna imagen_url agregada a producto_colores exitosamente.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "La columna imagen_url ya existe en producto_colores.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
