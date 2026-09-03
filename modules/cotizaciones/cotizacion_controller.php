<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/cotizacion_model.php';
require_once __DIR__ . '/../../config/db.php';

$model = new CotizacionModel($db);

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
}

$upload_dir = __DIR__ . '/../../assets/uploads/cotizaciones/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

switch ($action) {
    case 'create':
    case 'update':
        $isUpdate = ($action === 'update');
        $id = $isUpdate ? ($_POST['id'] ?? null) : null;
        if ($isUpdate && !$id) die("ID requerido para actualización.");

        $data = [
            'numero' => trim($_POST['numero'] ?? ''),
            'cliente_nombre' => $_POST['cliente_nombre'] ?? '',
            'cliente_documento' => $_POST['cliente_documento'] ?? '',
            'cliente_telefono' => $_POST['cliente_telefono'] ?? '',
            'cliente_direccion' => $_POST['cliente_direccion'] ?? '',
            'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
            'fecha_validez' => $_POST['fecha_validez'] ?? '',
            'total' => $_POST['total'] ?? 0,
            'observaciones' => $_POST['observaciones'] ?? '',
            'condiciones' => $_POST['condiciones'] ?? '',
            'estado' => $_POST['estado'] ?? 'Pendiente',
            'gastos_logisticos' => isset($_POST['gastos_logisticos']) && $_POST['gastos_logisticos'] !== '' ? floatval($_POST['gastos_logisticos']) : 0,
            'modificacion_orden_compra' => isset($_POST['modificacion_orden_compra']) && $_POST['modificacion_orden_compra'] !== '' ? floatval($_POST['modificacion_orden_compra']) : 0,
            'movilidad' => isset($_POST['movilidad']) && $_POST['movilidad'] !== '' ? floatval($_POST['movilidad']) : 0,
            'usuario_id' => $_SESSION['user_id'] ?? null,
            'vendedor_id' => !empty($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : ($_SESSION['user_id'] ?? null),
            'local_id' => !empty($_POST['local_id']) ? intval($_POST['local_id']) : ($_SESSION['local_id'] ?? null),
            'vendedor_nombre' => trim($_POST['vendedor_nombre'] ?? '')
        ];

        if (empty($data['numero'])) {
            $data['numero'] = $model->generateNextNumero($data['fecha']);
        }

        $detalles = [];
        if (isset($_POST['productos']) && is_array($_POST['productos'])) {
            foreach ($_POST['productos'] as $idx => $prod) {
                $imagen_path = $prod['imagen'] ?? null;

                // Verificar si se subió un archivo de imagen específico para este renglón
                if (isset($_FILES['producto_imagen_' . $idx]) && $_FILES['producto_imagen_' . $idx]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['producto_imagen_' . $idx];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $validExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array($ext, $validExts)) {
                        $filename = uniqid('cot_item_') . '.' . $ext;
                        $targetPath = $upload_dir . $filename;
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $imagen_path = '/carpicenter_sys/assets/uploads/cotizaciones/' . $filename;
                        }
                    }
                }

                $detalles[] = [
                    'producto_id' => !empty($prod['producto_id']) ? $prod['producto_id'] : (!empty($prod['id']) ? $prod['id'] : null),
                    'descripcion' => $prod['descripcion'] ?? '',
                    'cantidad' => $prod['cantidad'] ?? 1,
                    'precio_unitario' => $prod['precio_unitario'] ?? 0,
                    'subtotal' => $prod['subtotal'] ?? 0,
                    'especificaciones' => $prod['especificaciones'] ?? null,
                    'imagen' => $imagen_path,
                    'color' => $prod['color'] ?? null
                ];
            }
        }

        try {
            if ($isUpdate) {
                $model->update($id, $data, $detalles);
                $cotId = $id;
            } else {
                $cotId = $model->create($data, $detalles);
            }
            header("Location: /carpicenter_sys/modules/cotizaciones/cotizacion_view.php?id=" . $cotId);
            exit;
        } catch (Exception $e) {
            die("Error al guardar la cotización: " . $e->getMessage());
        }
        break;

    case 'cambiar_estado':
        $id = $_GET['id'] ?? ($_POST['id'] ?? null);
        $nuevo_estado = $_GET['nuevo_estado'] ?? ($_POST['nuevo_estado'] ?? 'Anulada');
        if ($id) {
            $stmt = $db->prepare("UPDATE cotizaciones SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id]);
        }
        header("Location: /carpicenter_sys/modules/cotizaciones/cotizaciones.php?msg=estado_actualizado");
        exit;

    case 'delete':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $model->delete($id);
        }
        header("Location: /carpicenter_sys/modules/cotizaciones/cotizaciones.php");
        exit;
        
    case 'aprobar_firma':
        $id = $_POST['id'] ?? null;
        $tipo_documento = $_POST['tipo_documento'] ?? 'CONTRATO';
        if ($id) {
            $token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("UPDATE cotizaciones SET estado = 'Aprobada', firma_token = ?, tipo_documento = ? WHERE id = ?");
            $stmt->execute([$token, $tipo_documento, $id]);
        }
        header("Location: /carpicenter_sys/modules/cotizaciones/cotizaciones.php?msg=aprobado");
        exit;
        
    case 'guardar_firma':
        $token = $_POST['token'] ?? null;
        $firma_base64 = $_POST['firma_digital'] ?? null;
        if ($token && $firma_base64) {
            $stmt = $db->prepare("UPDATE cotizaciones SET firma_digital = ?, estado = 'Aceptada' WHERE firma_token = ?");
            $stmt->execute([$firma_base64, $token]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Faltan datos']);
        }
        exit;
}
?>
