<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

function redirect($msg='', $type='ok') {
    $q = $msg ? '?msg=' . urlencode($msg) . '&type=' . $type : '';
    header("Location: /carpicenter_sys/views/guias.php$q");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('Método no permitido', 'error');
}

$guia_id        = intval($_POST['guia_id'] ?? 0);
$estado_entrega = trim($_POST['estado_entrega'] ?? 'PENDIENTE');
$tipo_documento = trim($_POST['tipo_documento'] ?? 'cargo_firmado');
$observaciones  = trim($_POST['observaciones'] ?? '');

if (!$guia_id) {
    redirect('Guía no especificada.', 'error');
}

try {
    $db->beginTransaction();

    // 1. Actualizar estado de la guía de remisión
    $fecha_entrega = ($estado_entrega === 'ENTREGADO') ? date('Y-m-d H:i:s') : null;
    $stmtUpd = $db->prepare("
        UPDATE guias_remision
        SET estado_entrega = :estado,
            fecha_entrega = COALESCE(:fecha_entrega, fecha_entrega),
            observaciones = CASE 
                WHEN :obs != '' THEN COALESCE(observaciones || ' | ', '') || :obs 
                ELSE observaciones 
            END
        WHERE id = :id
    ");
    $stmtUpd->execute([
        ':estado'        => $estado_entrega,
        ':fecha_entrega' => $fecha_entrega,
        ':obs'           => $observaciones,
        ':id'            => $guia_id
    ]);

    // 2. Procesar la subida del documento si viene un archivo
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['documento'];
        $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 10 * 1024 * 1024; // 10 MB

        if ($file['size'] <= $max_size) {
            $mime = mime_content_type($file['tmp_name']);
            if (in_array($mime, $allowed)) {
                $uploadDir = __DIR__ . '/../assets/uploads/documentos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = 'guia_cargo_' . $guia_id . '_' . uniqid() . '.' . strtolower($ext);
                $dest = $uploadDir . $name;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $tipo_reg = 'guia_' . $tipo_documento;
                    $stmtDoc = $db->prepare("
                        INSERT INTO documentos_adjuntos (tipo, referencia_id, ruta)
                        VALUES (:tipo, :ref, :ruta)
                    ");
                    $stmtDoc->execute([
                        ':tipo' => $tipo_reg,
                        ':ref'  => $guia_id,
                        ':ruta' => 'assets/uploads/documentos/' . $name
                    ]);
                }
            }
        }
    }

    $db->commit();
    redirect('Evidencia de despacho y cargo de entrega registrados correctamente.');

} catch (Exception $e) {
    $db->rollBack();
    redirect('Error al guardar evidencia: ' . $e->getMessage(), 'error');
}
?>
