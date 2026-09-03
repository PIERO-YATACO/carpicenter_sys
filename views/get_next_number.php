<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? '';
$serie = $_GET['serie'] ?? '';

if (empty($tipo) || empty($serie)) {
    echo json_encode(['error' => 'Tipo y serie son requeridos']);
    exit;
}

try {
    // Obtener el último número numérico registrado para este tipo de comprobante y serie
    $stmt = $db->prepare("
        SELECT numero 
        FROM ventas 
        WHERE tipo_comprobante = :tipo AND serie = :serie AND numero ~ '^[0-9]+$'
        ORDER BY CAST(numero AS INTEGER) DESC 
        LIMIT 1
    ");
    $stmt->execute([':tipo' => $tipo, ':serie' => $serie]);
    $last_numero = $stmt->fetchColumn();

    if ($last_numero) {
        $next_num = intval($last_numero) + 1;
    } else {
        $next_num = 1;
    }

    $padded = str_pad($next_num, 6, '0', STR_PAD_LEFT);
    echo json_encode(['next_number' => $padded]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
