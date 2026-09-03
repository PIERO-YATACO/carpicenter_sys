<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'create') {
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));

    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del color o acabado no puede estar vacío.']);
        exit;
    }

    if (empty($codigo)) {
        $clean = preg_replace('/[^A-Z0-9]/', '', $nombre);
        $codigo = mb_substr($clean, 0, 6);
    }

    try {
        // Verificar si ya existe
        $stmtCheck = $db->prepare("SELECT id, nombre, codigo FROM colores WHERE UPPER(nombre) = :n LIMIT 1");
        $stmtCheck->execute([':n' => $nombre]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'success' => true,
                'message' => 'El color ya existe en la base de datos.',
                'color' => $existing,
                'is_existing' => true
            ]);
            exit;
        }

        // Insertar nuevo color
        $stmtIns = $db->prepare("INSERT INTO colores (nombre, codigo) VALUES (:n, :c) RETURNING id");
        $stmtIns->execute([':n' => $nombre, ':c' => $codigo]);
        $id = $stmtIns->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => '¡Nuevo color agregado exitosamente!',
            'color' => ['id' => $id, 'nombre' => $nombre, 'codigo' => $codigo],
            'is_existing' => false
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar el color: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'list') {
    try {
        $stmt = $db->query("SELECT id, nombre FROM colores ORDER BY nombre ASC");
        $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'colores' => $colores]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
exit;
?>
