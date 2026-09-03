<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: notas_venta.php");
    exit();
}

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

    // 1. Obtener y bloquear el correlativo siguiente seguro
    $stmtSeq = $db->query("
        SELECT numero 
        FROM notas_venta 
        WHERE numero LIKE 'T001-%'
        ORDER BY id DESC 
        LIMIT 1
        FOR UPDATE
    ");
    $last_numero = $stmtSeq->fetchColumn();
    if ($last_numero) {
        $parts = explode('-', $last_numero);
        $num = intval($parts[1]) + 1;
    } else {
        $num = 1;
    }
    $numero = 'T001-' . str_pad($num, 6, '0', STR_PAD_LEFT);

    // 2. Calcular total real sumando los items
    $total_calculado = 0.0;
    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $precio_unitario = floatval($item['precio_unitario'] ?? 0);
        $total_calculado += ($cantidad * $precio_unitario);
    }

    $local_id = !empty($_POST['local_id']) ? intval($_POST['local_id']) : ($user_local_id ?? 1);
    $stmtLocName = $db->prepare("SELECT nombre FROM locales WHERE id = :id");
    $stmtLocName->execute([':id' => $local_id]);
    $local_nombre = $stmtLocName->fetchColumn() ?: 'Almacén Principal';

    // 3. Insertar Cabecera de Nota de Venta
    $stmtInsert = $db->prepare("
        INSERT INTO notas_venta (numero, fecha, cliente_nombre, cliente_documento, cliente_direccion, cliente_telefono, vendedor, total, metodo_pago, observaciones, estado, local_id, local_nombre, usuario_id)
        VALUES (:numero, :fecha, :cliente_nombre, :cliente_documento, :cliente_direccion, :cliente_telefono, :vendedor, :total, :metodo_pago, :observaciones, 'Activa', :local_id, :local_nombre, :usuario_id)
        RETURNING id
    ");
    
    $vendedor_nombre = $_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Vendedor';
    
    $stmtInsert->execute([
        ':numero' => $numero,
        ':fecha' => $fecha,
        ':cliente_nombre' => $cliente_nombre,
        ':cliente_documento' => $cliente_documento !== '' ? $cliente_documento : null,
        ':cliente_direccion' => $cliente_direccion !== '' ? $cliente_direccion : null,
        ':cliente_telefono' => $cliente_telefono !== '' ? $cliente_telefono : null,
        ':vendedor' => $vendedor_nombre,
        ':total' => $total_calculado,
        ':metodo_pago' => $metodo_pago,
        ':observaciones' => $observaciones !== '' ? $observaciones : null,
        ':local_id' => $local_id,
        ':local_nombre' => $local_nombre,
        ':usuario_id' => $_SESSION['user_id'] ?? null
    ]);
    
    $nota_id = $stmtInsert->fetchColumn();

    // 4. Insertar Detalles
    $stmtInsertDet = $db->prepare("
        INSERT INTO notas_venta_detalle (nota_id, cantidad, descripcion, precio_unitario, importe)
        VALUES (:nota_id, :cantidad, :descripcion, :precio_unitario, :importe)
    ");

    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $descripcion = trim($item['descripcion'] ?? '');
        $precio_unitario = floatval($item['precio_unitario'] ?? 0);
        $importe = $cantidad * $precio_unitario;

        if ($cantidad > 0 && $descripcion !== '') {
            $stmtInsertDet->execute([
                ':nota_id' => $nota_id,
                ':cantidad' => $cantidad,
                ':descripcion' => $descripcion,
                ':precio_unitario' => $precio_unitario,
                ':importe' => $importe
            ]);
        }
    }

    $db->commit();
    header("Location: nota_view.php?id=" . $nota_id . "&success=1");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error al guardar la Nota de Venta: " . $e->getMessage());
}
?>
