<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ordenes_egreso.php");
    exit();
}

$local_origen_id = intval($_POST['local_origen_id'] ?? 1);
$local_destino_nombre = trim($_POST['local_destino_nombre'] ?? '');
$motivo_egreso = trim($_POST['motivo_egreso'] ?? 'ENTREGA A CLIENTE POR CONTRATO');
$fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
$hora_emision = $_POST['hora_emision'] ?? date('H:i:s');
$fecha_aprox_llegada = !empty($_POST['fecha_aprox_llegada']) ? $_POST['fecha_aprox_llegada'] : null;
$recepcionado_nombre = trim($_POST['recepcionado_nombre'] ?? '');
$recepcionado_dni = trim($_POST['recepcionado_dni'] ?? '');
$contrato_id = !empty($_POST['contrato_id']) ? intval($_POST['contrato_id']) : null;
$nota_venta_id = !empty($_POST['nota_venta_id']) ? intval($_POST['nota_venta_id']) : null;
$items = $_POST['items'] ?? [];

if (empty($local_destino_nombre)) {
    die("Error: El destino de la orden es obligatorio.");
}
if (empty($recepcionado_nombre) || empty($recepcionado_dni)) {
    die("Error: Los datos de quien recepciona (Nombre y DNI) son obligatorios.");
}
if (empty($items)) {
    die("Error: El detalle de productos de la orden no puede estar vacío.");
}

try {
    $db->beginTransaction();

    // 1. Obtener nombre del local origen
    $stmtLoc = $db->prepare("SELECT nombre FROM locales WHERE id = :id");
    $stmtLoc->execute([':id' => $local_origen_id]);
    $local_origen_nombre = $stmtLoc->fetchColumn() ?: 'Almacén Principal';

    // 2. Correlativo seguro
    $stmtSeq = $db->query("SELECT numero FROM ordenes_egreso ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $lastNum = $stmtSeq->fetchColumn();
    if ($lastNum) {
        $numOnly = intval(preg_replace('/[^0-9]/', '', $lastNum)) + 1;
    } else {
        $numOnly = 14152;
    }
    $numero = str_pad($numOnly, 8, '0', STR_PAD_LEFT);

    // 3. Insertar Cabecera de Orden de Egreso
    $stmtIns = $db->prepare("
        INSERT INTO ordenes_egreso (
            numero, fecha_emision, hora_emision, local_origen_id, local_origen_nombre,
            local_destino_nombre, motivo_egreso, contrato_id, nota_venta_id,
            fecha_aprox_llegada, recepcionado_nombre, recepcionado_dni, estado, usuario_id
        ) VALUES (
            :numero, :fecha_emision, :hora_emision, :local_origen_id, :local_origen_nombre,
            :local_destino_nombre, :motivo_egreso, :contrato_id, :nota_venta_id,
            :fecha_aprox_llegada, :recepcionado_nombre, :recepcionado_dni, 'Emitida', :usuario_id
        ) RETURNING id
    ");

    $user_id = $_SESSION['user_id'] ?? null;

    $stmtIns->execute([
        ':numero' => $numero,
        ':fecha_emision' => $fecha_emision,
        ':hora_emision' => $hora_emision,
        ':local_origen_id' => $local_origen_id,
        ':local_origen_nombre' => $local_origen_nombre,
        ':local_destino_nombre' => $local_destino_nombre,
        ':motivo_egreso' => $motivo_egreso,
        ':contrato_id' => $contrato_id,
        ':nota_venta_id' => $nota_venta_id,
        ':fecha_aprox_llegada' => $fecha_aprox_llegada,
        ':recepcionado_nombre' => $recepcionado_nombre,
        ':recepcionado_dni' => $recepcionado_dni,
        ':usuario_id' => $user_id
    ]);

    $orden_id = $stmtIns->fetchColumn();

    // 4. Insertar Detalles y DESCONTAR AL 100% EL INVENTARIO FÍSICO
    $stmtInsDet = $db->prepare("
        INSERT INTO orden_egreso_detalles (orden_egreso_id, producto_id, color_id, descripcion, unidad_medida, cantidad)
        VALUES (:orden_egreso_id, :producto_id, :color_id, :descripcion, :unidad_medida, :cantidad)
    ");

    foreach ($items as $item) {
        $descripcion = trim($item['descripcion'] ?? '');
        $cantidad = floatval($item['cantidad'] ?? 0);
        $unidad = trim($item['unidad_medida'] ?? 'un');
        $prodId = !empty($item['producto_id']) ? intval($item['producto_id']) : null;
        $colorId = !empty($item['color_id']) ? intval($item['color_id']) : null;

        if ($cantidad > 0 && !empty($descripcion)) {
            $stmtInsDet->execute([
                ':orden_egreso_id' => $orden_id,
                ':producto_id' => $prodId,
                ':color_id' => $colorId,
                ':descripcion' => $descripcion,
                ':unidad_medida' => $unidad,
                ':cantidad' => $cantidad
            ]);

            // DESCUENTO 100% EN INVENTARIO FÍSICO (stock_actual), LIBERACIÓN DE RESERVADO Y REGISTRO EN KARDEX
            if ($prodId && $colorId) {
                $stmtUpdInv = $db->prepare("
                    UPDATE inventario_local 
                    SET stock_actual = GREATEST(COALESCE(stock_actual, 0) - :cant, 0),
                        stock_reservado = GREATEST(COALESCE(stock_reservado, 0) - :cant, 0)
                    WHERE producto_id = :p AND color_id = :c AND local_id = :l
                    RETURNING stock_actual
                ");
                $stmtUpdInv->execute([
                    ':cant' => $cantidad,
                    ':p' => $prodId,
                    ':c' => $colorId,
                    ':l' => $local_origen_id
                ]);
                $stockResultante = $stmtUpdInv->fetchColumn() ?: 0;

                // REGISTRAR MOVIMIENTO DE SALIDA EN EL KARDEX
                $stmtKardex = $db->prepare("
                    INSERT INTO kardex (tipo_movimiento, producto_id, color_id, local_id, cantidad, stock_resultante, motivo, documento_referencia, usuario_id)
                    VALUES ('Salida', :p, :c, :l, :cant, :stock_res, :motivo, :doc_ref, :u)
                ");
                $stmtKardex->execute([
                    ':p' => $prodId,
                    ':c' => $colorId,
                    ':l' => $local_origen_id,
                    ':cant' => $cantidad,
                    ':stock_res' => $stockResultante,
                    ':motivo' => $motivo_egreso . ' (Destino: ' . $local_destino_nombre . ')',
                    ':doc_ref' => 'Orden de Egreso N° ' . $numero,
                    ':u' => $user_id
                ]);
            }
        }
    }

    // 5. Si viene vinculado a un contrato, actualizar estado del contrato a Despachado
    if ($contrato_id) {
        $stmtUpdC = $db->prepare("UPDATE contratos SET estado_contrato = 'Despachado' WHERE id = :id");
        $stmtUpdC->execute([':id' => $contrato_id]);
    }

    $db->commit();
    header("Location: egreso_print.php?id=" . $orden_id);
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error al emitir la Orden de Egreso: " . $e->getMessage());
}
