<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los Administradores pueden anular Órdenes de Egreso.");
}

$id = $_GET['id'] ?? null;
if (!$id) die("ID de orden no proporcionado.");

try {
    $db->beginTransaction();

    // Obtener orden
    $stmt = $db->prepare("SELECT * FROM ordenes_egreso WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden) die("Orden de egreso no encontrada.");
    if ($orden['estado'] === 'Anulada') die("La orden de egreso ya fue anulada previamente.");

    // Cambiar estado a Anulada
    $stmtUpd = $db->prepare("UPDATE ordenes_egreso SET estado = 'Anulada' WHERE id = :id");
    $stmtUpd->execute([':id' => $id]);

    // Obtener detalles para restaurar inventario
    $stmtDet = $db->prepare("SELECT * FROM orden_egreso_detalles WHERE orden_egreso_id = :id");
    $stmtDet->execute([':id' => $id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $user_id = $_SESSION['user_id'] ?? null;
    $local_id = intval($orden['local_origen_id'] ?? 1);

    foreach ($detalles as $det) {
        $prodId = intval($det['producto_id'] ?? 0);
        $colId = intval($det['color_id'] ?? 0);
        $cant = floatval($det['cantidad'] ?? 0);

        if ($prodId && $colId && $cant > 0) {
            // RESTAURAR INVENTARIO FÍSICO (stock_actual = stock_actual + cantidad)
            $stmtUpdInv = $db->prepare("
                UPDATE inventario_local 
                SET stock_actual = COALESCE(stock_actual, 0) + :cant
                WHERE producto_id = :p AND color_id = :c AND local_id = :l
                RETURNING stock_actual
            ");
            $stmtUpdInv->execute([
                ':cant' => $cant,
                ':p' => $prodId,
                ':c' => $colId,
                ':l' => $local_id
            ]);
            $stockResultante = $stmtUpdInv->fetchColumn() ?: 0;

            // REGISTRAR ANULACIÓN EN EL KARDEX
            $stmtKardex = $db->prepare("
                INSERT INTO kardex (tipo_movimiento, producto_id, color_id, local_id, cantidad, stock_resultante, motivo, documento_referencia, usuario_id)
                VALUES ('Entrada', :p, :c, :l, :cant, :stock_res, :motivo, :doc_ref, :u)
            ");
            $stmtKardex->execute([
                ':p' => $prodId,
                ':c' => $colId,
                ':l' => $local_id,
                ':cant' => $cant,
                ':stock_res' => $stockResultante,
                ':motivo' => 'ANULACIÓN DE ORDEN DE EGRESO N° ' . $orden['numero'],
                ':doc_ref' => 'Anulación ' . $orden['numero'],
                ':u' => $user_id
            ]);
        }
    }

    // Si estaba vinculada a un contrato, restaurar contrato a Pendiente
    if (!empty($orden['contrato_id'])) {
        $stmtC = $db->prepare("UPDATE contratos SET estado_contrato = 'Pendiente' WHERE id = :id");
        $stmtC->execute([':id' => $orden['contrato_id']]);
    }

    $db->commit();
    header("Location: ordenes_egreso.php?voided=1");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error al anular la Orden de Egreso: " . $e->getMessage());
}
