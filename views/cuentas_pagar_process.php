<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$action = $_POST['action'] ?? '';
$id     = intval($_POST['id'] ?? 0);

function redirect($msg='', $type='ok') {
    $q = $msg ? '?msg=' . urlencode($msg) . '&type=' . $type : '';
    header("Location: /carpicenter_sys/views/cuentas_pagar.php$q");
    exit;
}

try {
    if ($action === 'create') {
        $stmt = $db->prepare("
            INSERT INTO cuentas_pagar
              (proveedor_id, tipo_credito, monto, fecha_emision, fecha_vencimiento, banco, numero_operacion, estado, observaciones)
            VALUES
              (:proveedor_id, :tipo_credito, :monto, :fecha_emision, :fecha_vencimiento, :banco, :numero_operacion, :estado, :observaciones)
            RETURNING id
        ");
        $stmt->execute([
            ':proveedor_id'      => $_POST['proveedor_id'],
            ':tipo_credito'      => $_POST['tipo_credito'],
            ':monto'             => floatval($_POST['monto']),
            ':fecha_emision'     => $_POST['fecha_emision'],
            ':fecha_vencimiento' => $_POST['fecha_vencimiento'],
            ':banco'             => trim($_POST['banco'] ?? ''),
            ':numero_operacion'  => trim($_POST['numero_operacion'] ?? ''),
            ':estado'            => $_POST['estado'],
            ':observaciones'     => trim($_POST['observaciones'] ?? ''),
        ]);
        $new_id = $stmt->fetchColumn();

        // Subir documentos adjuntos si los hay
        if (!empty($_FILES['documentos']['name'][0])) {
            uploadDocumentos($_FILES['documentos'], $new_id, 'cp_documento', $db);
        }

        redirect('Cuenta registrada correctamente.');

    } elseif ($action === 'edit') {
        $stmt = $db->prepare("
            UPDATE cuentas_pagar SET
              proveedor_id      = :proveedor_id,
              tipo_credito      = :tipo_credito,
              monto             = :monto,
              fecha_emision     = :fecha_emision,
              fecha_vencimiento = :fecha_vencimiento,
              banco             = :banco,
              numero_operacion  = :numero_operacion,
              estado            = :estado,
              observaciones     = :observaciones
            WHERE id = :id
        ");
        $stmt->execute([
            ':proveedor_id'      => $_POST['proveedor_id'],
            ':tipo_credito'      => $_POST['tipo_credito'],
            ':monto'             => floatval($_POST['monto']),
            ':fecha_emision'     => $_POST['fecha_emision'],
            ':fecha_vencimiento' => $_POST['fecha_vencimiento'],
            ':banco'             => trim($_POST['banco'] ?? ''),
            ':numero_operacion'  => trim($_POST['numero_operacion'] ?? ''),
            ':estado'            => $_POST['estado'],
            ':observaciones'     => trim($_POST['observaciones'] ?? ''),
            ':id'                => $id,
        ]);
        redirect('Cuenta actualizada correctamente.');

    } elseif ($action === 'delete') {
        // Eliminar documentos adjuntos asociados
        $docs = $db->prepare("SELECT ruta FROM documentos_adjuntos WHERE referencia_id=:id AND tipo LIKE 'cp_%'");
        $docs->execute([':id' => $id]);
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $ruta) {
            $full = __DIR__ . '/../../' . $ruta;
            if (file_exists($full)) unlink($full);
        }
        $db->prepare("DELETE FROM documentos_adjuntos WHERE referencia_id=:id AND tipo LIKE 'cp_%'")->execute([':id' => $id]);
        $db->prepare("DELETE FROM cuentas_pagar WHERE id=:id")->execute([':id' => $id]);
        redirect('Cuenta eliminada.');
    } else {
        redirect('Acción no válida.', 'error');
    }

} catch (Exception $e) {
    redirect('Error: ' . $e->getMessage(), 'error');
}

// ── Helper: subir múltiples archivos ──
function uploadDocumentos($files, $refId, $tipo, $db) {
    $allowed = ['image/jpeg','image/png','application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    $uploadDir = __DIR__ . '/../../assets/uploads/documentos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > $maxSize) continue;
        $mime = mime_content_type($files['tmp_name'][$i]);
        if (!in_array($mime, $allowed)) continue;

        $ext  = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $name = uniqid('doc_', true) . '.' . $ext;
        $dest = $uploadDir . $name;
        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $db->prepare("INSERT INTO documentos_adjuntos (tipo, referencia_id, ruta) VALUES (:tipo, :ref, :ruta)")
               ->execute([':tipo' => $tipo, ':ref' => $refId, ':ruta' => 'assets/uploads/documentos/' . $name]);
        }
    }
}
?>
