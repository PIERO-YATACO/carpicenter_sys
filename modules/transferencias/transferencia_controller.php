<?php
require_once __DIR__ . '/transferencia_model.php';
require_once __DIR__ . '/../../config/db.php';

$model = new TransferenciaModel($db);

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
}

switch ($action) {
    case 'create':
        $data = [
            'local_origen_id' => $_POST['local_origen_id'] ?? null,
            'local_destino_id' => $_POST['local_destino_id'] ?? null,
            'observaciones' => $_POST['observaciones'] ?? '',
            'motivo' => $_POST['motivo'] ?? 'Transferencia entre almacenes'
        ];

        if (!$data['local_origen_id'] || !$data['local_destino_id']) {
            die("Orígen y Destino requeridos.");
        }
        if ($data['local_origen_id'] == $data['local_destino_id']) {
            die("El origen y destino no pueden ser el mismo.");
        }

        $detalles = [];
        if (isset($_POST['productos'])) {
            foreach ($_POST['productos'] as $prodKey => $cantidad) {
                if ($cantidad > 0) {
                    list($prodId, $colorId) = explode('_', $prodKey);
                    $detalles[] = [
                        'producto_id' => $prodId,
                        'color_id' => $colorId,
                        'cantidad' => $cantidad
                    ];
                }
            }
        }

        if (empty($detalles)) {
            die("Debe seleccionar al menos un producto.");
        }

        try {
            $id = $model->create($data, $detalles);
            header("Location: /carpicenter_sys/modules/transferencias/transferencias.php?msg=creada");
            exit;
        } catch (Exception $e) {
            die("Error al crear la transferencia: " . $e->getMessage());
        }
        break;

    case 'confirm':
        $id = $_POST['transferencia_id'] ?? null;
        if (!$id) die("ID requerido");

        $recibidas = $_POST['recibida'] ?? []; // format: det_id => qty
        
        try {
            $model->confirmReception($id, $recibidas);
            header("Location: /carpicenter_sys/modules/transferencias/transferencia_view.php?id=" . $id . "&msg=confirmada");
            exit;
        } catch (Exception $e) {
            die("Error al confirmar: " . $e->getMessage());
        }
        break;

    case 'get_stock':
        // AJAX endpoint
        header('Content-Type: application/json');
        $localId = $_GET['local_id'] ?? null;
        if ($localId) {
            echo json_encode($model->getProductosStockLocal($localId));
        } else {
            echo json_encode([]);
        }
        exit;

    default:
        header("Location: /carpicenter_sys/modules/transferencias/transferencias.php");
        exit;
}
?>
