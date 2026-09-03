<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ordenes_egreso.php");
    exit();
}

$id = intval($_POST['id'] ?? 0);
if (!$id) die("ID de orden inválido.");

if (!isset($_FILES['foto_firmada']) || $_FILES['foto_firmada']['error'] !== UPLOAD_ERR_OK) {
    die("Error: No se recibió ningún archivo válido.");
}

$file = $_FILES['foto_firmada'];
$allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExts)) {
    die("Error: Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, WEBP) o PDF.");
}

$targetDir = __DIR__ . '/../../uploads/ordenes_egreso/';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$filename = 'egreso_firmado_' . $id . '_' . time() . '.' . $ext;
$targetPath = $targetDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $webPath = '/carpicenter_sys/uploads/ordenes_egreso/' . $filename;
    
    $stmt = $db->prepare("UPDATE ordenes_egreso SET foto_documento_firmado = :path WHERE id = :id");
    $stmt->execute([':path' => $webPath, ':id' => $id]);

    header("Location: ordenes_egreso.php?uploaded=1");
    exit();
} else {
    die("Error al guardar la imagen en el servidor.");
}
