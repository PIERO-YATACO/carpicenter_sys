<?php
require_once dirname(__DIR__) . '/config/db.php';
try {
    $db->beginTransaction();
    $updates = [
        22 => '2026 009 001',
        23 => '2026 009 002',
        24 => '2026 009 003'
    ];
    $stmt = $db->prepare("UPDATE cotizaciones SET numero = ? WHERE id = ?");
    foreach ($updates as $id => $nuevoNum) {
        $stmt->execute([$nuevoNum, $id]);
        echo "Cotización ID $id actualizada a: $nuevoNum\n";
    }
    $db->commit();
    echo "¡Actualización de correlativos de Septiembre completada con éxito!\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
