<?php
/**
 * upload_document.php — Handler genérico para subir documentos adjuntos
 * Soporta: guías de remisión transportista, firmas de cliente, documentos de cuentas por pagar
 */
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size      = 5 * 1024 * 1024; // 5 MB

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$referencia_id = intval($_POST['referencia_id'] ?? 0);
$tipo          = trim($_POST['tipo'] ?? '');
$subtipo       = trim($_POST['subtipo'] ?? '');

if (!$referencia_id || !$tipo) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros requeridos']);
    exit;
}

if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la subida']);
    exit;
}

$file = $_FILES['documento'];

// Validar tamaño
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'El archivo excede el tamaño máximo de 5 MB']);
    exit;
}

// Validar MIME
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo PDF, JPG o PNG']);
    exit;
}

// Crear directorio de destino si no existe
$uploadDir = __DIR__ . '/assets/uploads/documentos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Nombre único
$ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
$name = uniqid($tipo . '_', true) . '.' . strtolower($ext);
$dest = $uploadDir . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor']);
    exit;
}

// Registrar en BD
$tipo_registro = $subtipo ? $tipo . '_' . $subtipo : $tipo;
try {
    $stmt = $db->prepare("
        INSERT INTO documentos_adjuntos (tipo, referencia_id, ruta)
        VALUES (:tipo, :ref, :ruta)
        RETURNING id
    ");
    $stmt->execute([
        ':tipo' => $tipo_registro,
        ':ref'  => $referencia_id,
        ':ruta' => 'assets/uploads/documentos/' . $name,
    ]);
    $doc_id = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'id'      => $doc_id,
        'ruta'    => '/carpicenter_sys/assets/uploads/documentos/' . $name,
        'nombre'  => $file['name'],
    ]);
} catch (Exception $e) {
    // Borrar el archivo si falla la BD
    if (file_exists($dest)) unlink($dest);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
