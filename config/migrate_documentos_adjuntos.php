<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();
    $db->exec("CREATE TABLE IF NOT EXISTS documentos_adjuntos (
        id SERIAL PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        referencia_id INT NOT NULL,
        ruta VARCHAR(255) NOT NULL,
        fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
    $db->commit();
    echo "Migración documentos_adjuntos exitosa.";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error migración documentos_adjuntos: " . $e->getMessage();
}
?>
