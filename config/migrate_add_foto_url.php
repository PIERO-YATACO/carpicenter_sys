<?php
require_once __DIR__ . '/db.php';

try {
    $db->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_url VARCHAR(255);");
    echo "Columna foto_url agregada a la tabla usuarios con éxito.\n";
} catch (PDOException $e) {
    die("Error al ejecutar migración de foto_url: " . $e->getMessage() . "\n");
}
?>
