<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $cols = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='contratos'")->fetchAll(PDO::FETCH_COLUMN);
    echo "COLUMNAS EN CONTRATOS:\n";
    print_r($cols);

    if (!in_array('costo_movilidad', $cols)) {
        echo "Añadiendo costo_movilidad a contratos...\n";
        $db->exec("ALTER TABLE contratos ADD COLUMN costo_movilidad NUMERIC(10,2) DEFAULT 0.00");
        echo "Columna costo_movilidad añadida con éxito.\n";
    } else {
        echo "Columna costo_movilidad ya existe.\n";
    }

    $detCols = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='contrato_detalles'")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nCOLUMNAS EN CONTRATO_DETALLES:\n";
    print_r($detCols);

    if (!in_array('origen_item', $detCols)) {
        echo "Añadiendo origen_item a contrato_detalles...\n";
        $db->exec("ALTER TABLE contrato_detalles ADD COLUMN origen_item VARCHAR(50) DEFAULT 'Producción'");
        echo "Columna origen_item añadida con éxito.\n";
    } else {
        echo "Columna origen_item ya existe.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
