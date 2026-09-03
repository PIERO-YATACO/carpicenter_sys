<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT v.*, c.nombre as cliente_nombre, c.dni_ruc as cliente_documento, c.direccion as cliente_direccion
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta) {
        $venta['success'] = true;
        echo json_encode($venta);
    } else {
        echo json_encode(['success' => false, 'error' => 'Venta no encontrada']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
