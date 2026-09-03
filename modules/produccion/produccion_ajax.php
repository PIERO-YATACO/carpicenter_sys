<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/contratos/contrato_model.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$contrato_id = intval($_POST['contrato_id'] ?? 0);
$action = $_POST['action'] ?? '';
$nuevo_estado = $_POST['estado_contrato'] ?? '';

if (!$contrato_id) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

try {
    $model = new ContratoModel($db);

    if ($action === 'delete') {
        $model->deleteContrato($contrato_id);
        echo json_encode([
            'success' => true,
            'message' => 'Cartilla de producción eliminada exitosamente.'
        ]);
        exit;
    }

    $valid_estados = ['Pendiente', 'En Producción', 'Listo para Entrega', 'Despachado', 'Anulado'];
    if (!in_array($nuevo_estado, $valid_estados)) {
        echo json_encode(['success' => false, 'error' => 'Estado inválido']);
        exit;
    }

    $model->updateEstado($contrato_id, $nuevo_estado);

    echo json_encode([
        'success' => true,
        'contrato_id' => $contrato_id,
        'nuevo_estado' => $nuevo_estado,
        'message' => 'Estado de fabricación actualizado correctamente.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
