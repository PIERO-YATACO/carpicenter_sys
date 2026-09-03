<?php
require_once 'db.php';

try {
    $db->exec("ALTER TABLE productos ADD COLUMN imagen_url VARCHAR(255) DEFAULT NULL");
    echo "Columna imagen_url agregada exitosamente.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "La columna imagen_url ya existe.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
