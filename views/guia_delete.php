<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los usuarios Administradores pueden eliminar guías de remisión.");
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("ID de guía no proporcionado.");
}

try {
    $db->beginTransaction();

    // 1. Eliminar documentos adjuntos vinculados a esta guía
    $stmtDocs = $db->prepare("DELETE FROM documentos_adjuntos WHERE referencia_id = :id AND tipo LIKE 'guia_%'");
    $stmtDocs->execute([':id' => $id]);

    // 2. Eliminar cabecera de la guía de remisión
    $stmtMain = $db->prepare("DELETE FROM guias_remision WHERE id = :id");
    $stmtMain->execute([':id' => $id]);

    $db->commit();

    header("Location: /carpicenter_sys/views/guias.php?msg=eliminado");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error al eliminar la guía de remisión: " . $e->getMessage());
}
?>
