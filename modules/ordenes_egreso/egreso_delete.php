<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los usuarios Administradores pueden eliminar órdenes de egreso.");
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("ID no proporcionado.");
}

try {
    $db->beginTransaction();

    // 1. Obtener los ítems de la orden para restaurar stock si no estaba anulada
    $stmtEg = $db->prepare("SELECT * FROM ordenes_egreso WHERE id = :id");
    $stmtEg->execute([':id' => $id]);
    $egreso = $stmtEg->fetch(PDO::FETCH_ASSOC);

    if ($egreso && $egreso['estado'] !== 'Anulada') {
        $stmtItems = $db->prepare("SELECT * FROM orden_egreso_detalles WHERE orden_egreso_id = :id");
        $stmtItems->execute([':id' => $id]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $localId = intval($egreso['local_origen_id'] ?? $egreso['local_id'] ?? 1);
        foreach ($items as $item) {
            $prodId = intval($item['producto_id'] ?? 0);
            $colId = intval($item['color_id'] ?? 0);
            $cant = floatval($item['cantidad'] ?? 0);

            if ($prodId && $colId && $cant > 0) {
                $stmtStock = $db->prepare("
                    UPDATE inventario_local 
                    SET stock_actual = COALESCE(stock_actual, 0) + :cant
                    WHERE producto_id = :p AND color_id = :c AND local_id = :l
                ");
                $stmtStock->execute([':cant' => $cant, ':p' => $prodId, ':c' => $colId, ':l' => $localId]);
            }
        }
    }

    // 2. Eliminar detalles y cabecera
    $db->prepare("DELETE FROM orden_egreso_detalles WHERE orden_egreso_id = :id")->execute([':id' => $id]);
    $db->prepare("DELETE FROM ordenes_egreso WHERE id = :id")->execute([':id' => $id]);

    $db->commit();

    header("Location: /carpicenter_sys/modules/ordenes_egreso/ordenes_egreso.php?msg=eliminado");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error al eliminar la Orden de Egreso: " . $e->getMessage());
}
?>
