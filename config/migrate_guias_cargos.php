<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // Agregar columna estado_entrega a guias_remision si no existe
    $db->exec("ALTER TABLE guias_remision ADD COLUMN IF NOT EXISTS estado_entrega VARCHAR(50) DEFAULT 'PENDIENTE'");
    $db->exec("ALTER TABLE guias_remision ADD COLUMN IF NOT EXISTS fecha_entrega TIMESTAMP DEFAULT NULL");

    // Asegurar tabla documentos_adjuntos
    $db->exec("
        CREATE TABLE IF NOT EXISTS documentos_adjuntos (
            id            SERIAL PRIMARY KEY,
            tipo          VARCHAR(80)   NOT NULL,
            referencia_id INT           NOT NULL,
            ruta          VARCHAR(255)  NOT NULL,
            fecha_subida  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $db->commit();
    echo "Migración de cargos y entregas exitosa.";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error migración: " . $e->getMessage();
}
?>
