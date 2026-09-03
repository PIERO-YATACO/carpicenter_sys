<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los usuarios Administradores pueden eliminar notas de venta.");
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("ID no proporcionado.");
}

try {
    $db->beginTransaction();

    // 1. Eliminar detalles de la nota de venta
    $stmtDet = $db->prepare("DELETE FROM notas_venta_detalle WHERE nota_id = :id");
    $stmtDet->execute([':id' => $id]);

    // 2. Eliminar cabecera de la nota de venta
    $stmtMain = $db->prepare("DELETE FROM notas_venta WHERE id = :id");
    $stmtMain->execute([':id' => $id]);

    $db->commit();

    header("Location: /carpicenter_sys/modules/notas_venta/notas_venta.php?msg=eliminado");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error al eliminar la Nota de Venta: " . $e->getMessage());
}
?>
