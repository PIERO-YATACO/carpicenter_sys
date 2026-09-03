<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/contrato_model.php';

$model = new ContratoModel($db);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_next_numero') {
    $serie = $_GET['serie'] ?? 'T003';
    header('Content-Type: application/json');
    echo json_encode(['numero' => $model->generateNumero($serie)]);
    exit;
}

if ($action === 'anular') {
    try {
        $contrato_id = intval($_POST['contrato_id'] ?? $_GET['id'] ?? 0);
        if (!$is_admin) {
            throw new Exception("Acceso denegado: Solo los usuarios Administradores pueden anular contratos.");
        }
        $model->updateEstado($contrato_id, 'Anulado');
        header("Location: contratos.php?msg=anulado");
        exit;
    } catch (Exception $e) {
        $msg = urlencode($e->getMessage());
        header("Location: contratos.php?error=" . $msg);
        exit;
    }
}

if ($action === 'delete') {
    try {
        $contrato_id = intval($_POST['contrato_id'] ?? $_GET['id'] ?? 0);
        if (!$is_admin) {
            throw new Exception("Acceso denegado: Solo los usuarios Administradores pueden eliminar contratos.");
        }
        $model->deleteContrato($contrato_id);
        header("Location: contratos.php?msg=eliminado");
        exit;
    } catch (Exception $e) {
        $msg = urlencode($e->getMessage());
        header("Location: contratos.php?error=" . $msg);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create') {
            $cliente_id = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null;
            $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
            $cliente_doc = trim($_POST['cliente_doc'] ?? '');
            $cliente_tel = trim($_POST['cliente_telefono'] ?? '');
            $cliente_dir = trim($_POST['cliente_direccion'] ?? '');
            $monto_adelanto = floatval($_POST['monto_adelanto'] ?? 0);

            // 1. BACKEND VALIDATION: ALL CLIENT DATA IS MANDATORY
            if (empty($cliente_nombre) || empty($cliente_doc) || empty($cliente_tel) || empty($cliente_dir)) {
                throw new Exception("Todos los datos del cliente (Nombre, DNI/RUC, Teléfono y Dirección) son obligatorios para guardar el contrato.");
            }

            if (strlen($cliente_doc) !== 8 && strlen($cliente_doc) !== 11) {
                throw new Exception("El número de documento debe tener exactamente 8 dígitos (DNI) u 11 dígitos (RUC).");
            }

            if (strlen($cliente_tel) !== 9) {
                throw new Exception("El número de celular debe tener exactamente 9 dígitos.");
            }

            $monto_total = floatval($_POST['monto_total'] ?? 0);

            // 2. BACKEND VALIDATION: ADVANCE PAYMENT (0 < ABONO < TOTAL) IS MANDATORY
            if ($monto_adelanto <= 0) {
                throw new Exception("No se puede emitir un contrato sin un pago adelantado (abono mayor a S/ 0.00).");
            }

            if ($monto_total > 0 && $monto_adelanto >= $monto_total) {
                throw new Exception("El pago adelantado (A CUENTA) debe ser estrictamente menor al monto total del contrato.");
            }

            if (!$cliente_id) {
                $stmtInsCli = $db->prepare("
                    INSERT INTO clientes (nombre, dni_ruc, telefono, direccion, tipo_doc, estado) 
                    VALUES (:nombre, :dni_ruc, :telefono, :direccion, :tipo_doc, 'Activo') 
                    RETURNING id
                ");
                $tipo_doc = (strlen($cliente_doc) === 11) ? 'RUC' : 'DNI';
                $stmtInsCli->execute([
                    ':nombre' => $cliente_nombre,
                    ':dni_ruc' => $cliente_doc ?: null,
                    ':telefono' => $cliente_tel ?: null,
                    ':direccion' => $cliente_dir ?: null,
                    ':tipo_doc' => $tipo_doc
                ]);
                $cliente_id = $stmtInsCli->fetchColumn();
            } else {
                // Update existing client record with latest phone/address/doc if missing
                $stmtUpdCli = $db->prepare("
                    UPDATE clientes 
                    SET dni_ruc = COALESCE(NULLIF(dni_ruc, ''), :dni_ruc),
                        telefono = COALESCE(NULLIF(telefono, ''), :telefono),
                        direccion = COALESCE(NULLIF(direccion, ''), :direccion)
                    WHERE id = :id
                ");
                $stmtUpdCli->execute([
                    ':dni_ruc' => $cliente_doc,
                    ':telefono' => $cliente_tel,
                    ':direccion' => $cliente_dir,
                    ':id' => $cliente_id
                ]);
            }

            $detallesRaw = $_POST['detalles'] ?? [];
            $detallesProcessed = [];

            foreach ($detallesRaw as $det) {
                $nombreProd = trim($det['nombre_producto'] ?? ($det['descripcion'] ?? ''));
                if (empty($nombreProd)) continue;

                $especificaciones = trim($det['especificaciones'] ?? ($det['observaciones_item'] ?? ''));
                $colorId = (!empty($det['color_id']) && is_numeric($det['color_id'])) ? intval($det['color_id']) : null;
                $colorCustom = trim($det['color_custom'] ?? '');

                // Si se escribió un color personalizado
                if (!empty($colorCustom) && empty($colorId)) {
                    $stmtColorCheck = $db->prepare("SELECT id FROM colores WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
                    $stmtColorCheck->execute([$colorCustom]);
                    $foundColorId = $stmtColorCheck->fetchColumn();
                    if ($foundColorId) {
                        $colorId = intval($foundColorId);
                    } else {
                        $stmtInsColor = $db->prepare("INSERT INTO colores (nombre) VALUES (?) RETURNING id");
                        $stmtInsColor->execute([mb_strtoupper($colorCustom)]);
                        $colorId = intval($stmtInsColor->fetchColumn());
                    }
                }

                $detallesProcessed[] = [
                    'producto_id' => (!empty($det['producto_id']) && is_numeric($det['producto_id'])) ? intval($det['producto_id']) : null,
                    'descripcion' => $nombreProd,
                    'color_id' => $colorId,
                    'cantidad' => max(1, intval($det['cantidad'] ?? 1)),
                    'precio_unitario' => floatval($det['precio_unitario'] ?? 0),
                    'observaciones_item' => $especificaciones,
                    'origen_item' => !empty($det['origen_item']) ? trim($det['origen_item']) : 'Producción'
                ];
            }

            if (empty($detallesProcessed)) {
                throw new Exception("Debe agregar al menos un mueble o producto al contrato.");
            }

            $data = [
                'serie' => $_POST['serie'] ?? 'T003',
                'numero' => $_POST['numero'] ?? '',
                'cliente_id' => $cliente_id,
                'vendedor_id' => $_SESSION['usuario_id'] ?? 1,
                'local_id' => !empty($_POST['local_id']) ? intval($_POST['local_id']) : ($user_local_id ?? 1),
                'fecha_entrega_estimada' => $_POST['fecha_entrega_estimada'] ?? null,
                'tipo_entrega' => $_POST['tipo_entrega'] ?? 'Tienda 1',
                'direccion_entrega' => $_POST['direccion_entrega'] ?? null,
                'referencia_entrega' => $_POST['referencia_entrega'] ?? null,
                'instalacion_incluida' => !empty($_POST['instalacion_incluida']),
                'prioridad' => $_POST['prioridad'] ?? 'Normal',
                'costo_movilidad' => floatval($_POST['costo_movilidad'] ?? 0),
                'monto_total' => floatval($_POST['monto_total'] ?? 0),
                'monto_adelanto' => $monto_adelanto,
                'metodo_pago' => (!empty($_POST['metodos_pago']) && is_array($_POST['metodos_pago'])) ? implode(', ', $_POST['metodos_pago']) : ($_POST['metodo_pago'] ?? 'Efectivo'),
                'observaciones_generales' => $_POST['observaciones_generales'] ?? '',
                'cotizacion_id' => !empty($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : null
            ];

            $contratoId = $model->create($data, $detallesProcessed);
            header("Location: contrato_view.php?id=" . $contratoId . "&msg=creado");
            exit;

        } elseif ($action === 'edit') {
            $contrato_id = intval($_POST['contrato_id'] ?? 0);
            if (!$contrato_id) throw new Exception("ID de contrato no especificado.");

            $cliente_id = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null;
            $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
            $cliente_doc = trim($_POST['cliente_doc'] ?? '');
            $cliente_tel = trim($_POST['cliente_telefono'] ?? '');
            $cliente_dir = trim($_POST['cliente_direccion'] ?? '');
            $monto_adelanto = floatval($_POST['monto_adelanto'] ?? 0);

            if (empty($cliente_nombre) || empty($cliente_doc) || empty($cliente_tel) || empty($cliente_dir)) {
                throw new Exception("Todos los datos del cliente son obligatorios.");
            }

            if (!$cliente_id) {
                $stmtInsCli = $db->prepare("
                    INSERT INTO clientes (nombre, dni_ruc, telefono, direccion, tipo_doc, estado) 
                    VALUES (:nombre, :dni_ruc, :telefono, :direccion, :tipo_doc, 'Activo') 
                    RETURNING id
                ");
                $tipo_doc = (strlen($cliente_doc) === 11) ? 'RUC' : 'DNI';
                $stmtInsCli->execute([
                    ':nombre' => $cliente_nombre,
                    ':dni_ruc' => $cliente_doc ?: null,
                    ':telefono' => $cliente_tel ?: null,
                    ':direccion' => $cliente_dir ?: null,
                    ':tipo_doc' => $tipo_doc
                ]);
                $cliente_id = $stmtInsCli->fetchColumn();
            } else {
                $stmtUpdCli = $db->prepare("
                    UPDATE clientes 
                    SET dni_ruc = COALESCE(NULLIF(dni_ruc, ''), :dni_ruc),
                        telefono = COALESCE(NULLIF(telefono, ''), :telefono),
                        direccion = COALESCE(NULLIF(direccion, ''), :direccion)
                    WHERE id = :id
                ");
                $stmtUpdCli->execute([
                    ':dni_ruc' => $cliente_doc,
                    ':telefono' => $cliente_tel,
                    ':direccion' => $cliente_dir,
                    ':id' => $cliente_id
                ]);
            }

            $detallesRaw = $_POST['detalles'] ?? [];
            $detallesProcessed = [];

            foreach ($detallesRaw as $det) {
                $nombreProd = trim($det['nombre_producto'] ?? ($det['descripcion'] ?? ''));
                if (empty($nombreProd)) continue;

                $especificaciones = trim($det['especificaciones'] ?? ($det['observaciones_item'] ?? ''));
                $colorId = (!empty($det['color_id']) && is_numeric($det['color_id'])) ? intval($det['color_id']) : null;
                $colorCustom = trim($det['color_custom'] ?? '');

                if (!empty($colorCustom) && empty($colorId)) {
                    $stmtColorCheck = $db->prepare("SELECT id FROM colores WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
                    $stmtColorCheck->execute([$colorCustom]);
                    $foundColorId = $stmtColorCheck->fetchColumn();
                    if ($foundColorId) {
                        $colorId = intval($foundColorId);
                    } else {
                        $stmtInsColor = $db->prepare("INSERT INTO colores (nombre) VALUES (?) RETURNING id");
                        $stmtInsColor->execute([mb_strtoupper($colorCustom)]);
                        $colorId = intval($stmtInsColor->fetchColumn());
                    }
                }

                $detallesProcessed[] = [
                    'producto_id' => (!empty($det['producto_id']) && is_numeric($det['producto_id'])) ? intval($det['producto_id']) : null,
                    'descripcion' => $nombreProd,
                    'color_id' => $colorId,
                    'cantidad' => max(1, intval($det['cantidad'] ?? 1)),
                    'precio_unitario' => floatval($det['precio_unitario'] ?? 0),
                    'observaciones_item' => $especificaciones,
                    'origen_item' => !empty($det['origen_item']) ? trim($det['origen_item']) : 'Producción'
                ];
            }

            if (empty($detallesProcessed)) {
                throw new Exception("Debe agregar al menos un mueble o producto al contrato.");
            }

            $data = [
                'cliente_id' => $cliente_id,
                'local_id' => !empty($_POST['local_id']) ? intval($_POST['local_id']) : ($user_local_id ?? 1),
                'fecha_entrega_estimada' => $_POST['fecha_entrega_estimada'] ?? null,
                'tipo_entrega' => $_POST['tipo_entrega'] ?? 'Tienda 1',
                'direccion_entrega' => $_POST['direccion_entrega'] ?? null,
                'referencia_entrega' => $_POST['referencia_entrega'] ?? null,
                'instalacion_incluida' => !empty($_POST['instalacion_incluida']),
                'costo_movilidad' => floatval($_POST['costo_movilidad'] ?? 0),
                'monto_total' => floatval($_POST['monto_total'] ?? 0),
                'monto_adelanto' => $monto_adelanto,
                'metodo_pago' => (!empty($_POST['metodos_pago']) && is_array($_POST['metodos_pago'])) ? implode(', ', $_POST['metodos_pago']) : ($_POST['metodo_pago'] ?? 'Efectivo'),
                'observaciones_generales' => $_POST['observaciones_generales'] ?? ''
            ];

            $model->update($contrato_id, $data, $detallesProcessed);
            header("Location: contrato_view.php?id=" . $contrato_id . "&msg=edit_ok");
            exit;

        } elseif ($action === 'add_abono') {
            $contrato_id = intval($_POST['contrato_id']);
            $monto = floatval($_POST['monto']);
            $metodo_pago = $_POST['metodo_pago'] ?? 'Efectivo';
            $observacion = $_POST['observacion'] ?? 'Abono a cuenta de contrato';

            if ($monto <= 0) {
                throw new Exception("El monto del abono debe ser mayor a S/ 0.00.");
            }

            $model->addAbono($contrato_id, $monto, $metodo_pago, $observacion, $_SESSION['usuario_id'] ?? 1);
            header("Location: contrato_view.php?id=" . $contrato_id . "&msg=abono_ok");
            exit;

        } elseif ($action === 'update_estado') {
            $contrato_id = intval($_POST['contrato_id']);
            $estado_contrato = $_POST['estado_contrato'] ?? 'Pendiente';
            $estado_produccion = $_POST['estado_produccion'] ?? null;

            $model->updateEstado($contrato_id, $estado_contrato, $estado_produccion);
            header("Location: contrato_view.php?id=" . $contrato_id . "&msg=estado_ok");
            exit;
        }

    } catch (Exception $e) {
        $msg = urlencode($e->getMessage());
        header("Location: contratos.php?error=" . $msg);
        exit;
    }
}
