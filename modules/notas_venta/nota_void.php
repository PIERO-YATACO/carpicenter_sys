<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los usuarios Administradores pueden anular notas de venta.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no proporcionado.");
}

try {
    // Actualizar el estado a 'Anulada' en lugar de eliminar físicamente
    $stmt = $db->prepare("
        UPDATE notas_venta 
        SET estado = 'Anulada' 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);

    header("Location: nota_view.php?id=" . $id);
    exit();

} catch (PDOException $e) {
    die("Error al anular la Nota de Venta: " . $e->getMessage());
}
?>
