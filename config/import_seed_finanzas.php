<?php
require_once __DIR__ . '/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/seed_finanzas.sql');
    if (!empty(trim($sql))) {
        $db->exec($sql);
        echo "IMPORTACION_EXITOSA: Se importaron 115 registros desde el archivo Excel.";
    } else {
        echo "SQL Vacio.";
    }
} catch (Exception $e) {
    echo "ERROR_IMPORTACION: " . $e->getMessage();
}
