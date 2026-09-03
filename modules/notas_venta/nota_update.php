<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los Administradores pueden modificar notas de venta.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: notas_venta.php");
    exit();
}

$id = intval($_POST['id'] ?? 0);
if (!$id) die("ID de nota inválido.");

// Verificar existencia
$stmtCheck = $db->prepare("SELECT id, estado FROM notas_venta WHERE id = :id");
$stmtCheck->execute([':id' => $id]);
$notaExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$notaExistente) die("Nota de venta no encontrada.");
if ($notaExistente['estado'] !== 'Activa') die("No se puede modificar una nota de venta anulada.");

$local_id = !empty($_POST['local_id']) ? intval($_POST['local_id']) : 1;
$stmtLocName = $db->prepare("SELECT nombre FROM locales WHERE id = :id");
$stmtLocName->execute([':id' => $local_id]);
$local_nombre = $stmtLocName->fetchColumn() ?: 'Almacén Principal';

$fecha = $_POST['fecha'] ?? date('Y-m-d');
$cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
$cliente_documento = trim($_POST['cliente_documento'] ?? '');
$cliente_direccion = trim($_POST['cliente_direccion'] ?? '');
$cliente_telefono = trim($_POST['cliente_telefono'] ?? '');
$metodo_pago = $_POST['metodo_pago'] ?? 'Efectivo';
$observaciones = trim($_POST['observaciones'] ?? '');
$items = $_POST['items'] ?? [];

if (empty($cliente_nombre)) {
    die("Error: El nombre del cliente es obligatorio.");
}
if (empty($items)) {
    die("Error: El detalle de productos no puede estar vacío.");
}

try {
    $db->beginTransaction();

    // 1. Recalcular total real
    $total_calculado = 0.0;
    $items_procesados = [];
    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $descripcion = trim($item['descripcion'] ?? '');
        $precio_unitario = floatval($item['precio_unitario'] ?? 0);
        $importe = $cantidad * $precio_unitario;

        if ($cantidad > 0 && $descripcion !== '') {
            $total_calculado += $importe;
            $items_procesados[] = [
                'cantidad' => $cantidad,
                'descripcion' => $descripcion,
                'precio_unitario' => $precio_unitario,
                'importe' => $importe
            ];
        }
    }

    if (empty($items_procesados)) {
        throw new Exception("Debe ingresar al menos un producto válido.");
    }

    // 2. Actualizar Cabecera
    $stmtUpdate = $db->prepare("
        UPDATE notas_venta 
        SET fecha = :fecha,
            cliente_nombre = :cliente_nombre,
            cliente_documento = :cliente_documento,
            cliente_direccion = :cliente_direccion,
            cliente_telefono = :cliente_telefono,
            total = :total,
            metodo_pago = :metodo_pago,
            observaciones = :observaciones,
            local_id = :local_id,
            local_nombre = :local_nombre
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        ':fecha' => $fecha,
        ':cliente_nombre' => $cliente_nombre,
        ':cliente_documento' => $cliente_documento !== '' ? $cliente_documento : null,
        ':cliente_direccion' => $cliente_direccion !== '' ? $cliente_direccion : null,
        ':cliente_telefono' => $cliente_telefono !== '' ? $cliente_telefono : null,
        ':total' => $total_calculado,
        ':metodo_pago' => $metodo_pago,
        ':observaciones' => $observaciones !== '' ? $observaciones : null,
        ':local_id' => $local_id,
        ':local_nombre' => $local_nombre,
        ':id' => $id
    ]);

    // 3. Eliminar detalles anteriores y volver a insertar
    $stmtDel = $db->prepare("DELETE FROM notas_venta_detalle WHERE nota_id = :id");
    $stmtDel->execute([':id' => $id]);

    $stmtInsDet = $db->prepare("
        INSERT INTO notas_venta_detalle (nota_id, cantidad, descripcion, precio_unitario, importe)
        VALUES (:nota_id, :cantidad, :descripcion, :precio_unitario, :importe)
    ");

    foreach ($items_procesados as $det) {
        $stmtInsDet->execute([
            ':nota_id' => $id,
            ':cantidad' => $det['cantidad'],
            ':descripcion' => $det['descripcion'],
            ':precio_unitario' => $det['precio_unitario'],
            ':importe' => $det['importe']
        ]);
    }

    $db->commit();
    header("Location: nota_view.php?id=" . $id . "&updated=1");
    exit();

} catch (Exception $e) {
    $db->rollBack();
    die("Error al actualizar la nota de venta: " . $e->getMessage());
}
