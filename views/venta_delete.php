<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los usuarios Administradores pueden eliminar comprobantes de venta.");
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("ID de venta no proporcionado.");
}

try {
    $db->beginTransaction();

    // 1. Eliminar detalles de la venta
    $stmtDet = $db->prepare("DELETE FROM venta_detalles WHERE venta_id = :id");
    $stmtDet->execute([':id' => $id]);

    // 2. Desvincular referencias en guias_remision
    $stmtGuia = $db->prepare("UPDATE guias_remision SET venta_id = NULL WHERE venta_id = :id");
    $stmtGuia->execute([':id' => $id]);

    // 3. Eliminar cabecera de venta
    $stmtMain = $db->prepare("DELETE FROM ventas WHERE id = :id");
    $stmtMain->execute([':id' => $id]);

    $db->commit();

    header("Location: /carpicenter_sys/views/ventas.php?msg=eliminado");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error al eliminar la venta: " . $e->getMessage());
}
?>
