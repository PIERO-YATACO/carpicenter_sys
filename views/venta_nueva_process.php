<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /carpicenter_sys/views/ventas.php");
    exit;
}

$cotizacion_id = !empty($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : null;
$tipo_comprobante = $_POST['tipo_comprobante'] ?? '';
$serie = $_POST['serie'] ?? '';
$numero = $_POST['numero'] ?? '';
$fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
$fecha_pago = !empty($_POST['fecha_pago']) ? $_POST['fecha_pago'] : null;
$estado_pago = $_POST['estado_pago'] ?? 'PENDIENTE';
$cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
$cliente_documento = trim($_POST['cliente_documento'] ?? '');
$cliente_direccion = trim($_POST['cliente_direccion'] ?? '');
$total = floatval($_POST['total'] ?? 0);
$productos = $_POST['productos'] ?? [];

if (empty($cliente_nombre) || empty($tipo_comprobante) || empty($serie) || empty($numero)) {
    die("Error: Faltan datos requeridos para registrar la venta.");
}

try {
    $db->beginTransaction();

    // 1. Buscar o registrar al cliente en la tabla 'clientes'
    $cliente_id = null;
    if (!empty($cliente_documento)) {
        $stmtCli = $db->prepare("SELECT id FROM clientes WHERE dni_ruc = :dni_ruc LIMIT 1");
        $stmtCli->execute([':dni_ruc' => $cliente_documento]);
        $cliente_id = $stmtCli->fetchColumn();
    } else {
        $stmtCli = $db->prepare("SELECT id FROM clientes WHERE nombre = :nombre LIMIT 1");
        $stmtCli->execute([':nombre' => $cliente_nombre]);
        $cliente_id = $stmtCli->fetchColumn();
    }

    if (!$cliente_id) {
        // Registrar nuevo cliente
        $tipo_doc = (strlen($cliente_documento) === 11) ? 'RUC' : 'DNI';
        $tipo_cliente = ($tipo_doc === 'RUC') ? 'Persona Jurídica' : 'Persona Natural';
        
        $stmtInsCli = $db->prepare("
            INSERT INTO clientes (nombre, dni_ruc, direccion, tipo_doc, tipo_cliente, estado) 
            VALUES (:nombre, :dni_ruc, :direccion, :tipo_doc, :tipo_cliente, 'Activo') 
            RETURNING id
        ");
        $stmtInsCli->execute([
            ':nombre' => $cliente_nombre,
            ':dni_ruc' => $cliente_documento ?: null,
            ':direccion' => $cliente_direccion ?: null,
            ':tipo_doc' => $tipo_doc,
            ':tipo_cliente' => $tipo_cliente
        ]);
        $cliente_id = $stmtInsCli->fetchColumn();
    }

    // 2. Insertar cabecera de la venta
    // Estado general de la venta: si ya está pagado es 'Completada', sino es 'Pendiente'
    $estado_venta = ($estado_pago === 'PAGADO') ? 'Completada' : 'Pendiente';
    
    $stmtInsVenta = $db->prepare("
        INSERT INTO ventas (
            cliente_id, fecha, total, estado, tipo_comprobante, serie, numero, 
            fecha_emision, fecha_pago, estado_pago, cotizacion_id, estado_sunat
        ) VALUES (
            :cliente_id, CURRENT_TIMESTAMP, :total, :estado, :tipo_comprobante, :serie, :numero,
            :fecha_emision, :fecha_pago, :estado_pago, :cotizacion_id, 'NO_ENVIADO'
        ) RETURNING id
    ");
    
    $stmtInsVenta->execute([
        ':cliente_id' => $cliente_id,
        ':total' => $total,
        ':estado' => $estado_venta,
        ':tipo_comprobante' => $tipo_comprobante,
        ':serie' => $serie,
        ':numero' => $numero,
        ':fecha_emision' => $fecha_emision,
        ':fecha_pago' => $fecha_pago,
        ':estado_pago' => $estado_pago,
        ':cotizacion_id' => $cotizacion_id
    ]);
    
    $venta_id = $stmtInsVenta->fetchColumn();

    // 3. Insertar detalles de la venta
    $stmtInsDet = $db->prepare("
        INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_historico) 
        VALUES (:venta_id, :producto_id, :cantidad, :precio_historico)
    ");

    foreach ($productos as $prod) {
        $prod_id = !empty($prod['producto_id']) ? intval($prod['producto_id']) : null;
        $qty = intval($prod['cantidad']);
        $price = floatval($prod['precio_unitario']);
        
        $stmtInsDet->execute([
            ':venta_id' => $venta_id,
            ':producto_id' => $prod_id,
            ':cantidad' => $qty,
            ':precio_historico' => $price
        ]);
    }

    // 4. Si se originó desde una cotización, actualizar su estado a 'Facturada'
    if ($cotizacion_id) {
        $stmtUpdCot = $db->prepare("UPDATE cotizaciones SET estado = 'Facturada' WHERE id = :cotizacion_id");
        $stmtUpdCot->execute([':cotizacion_id' => $cotizacion_id]);
    }

    // 5. Registrar acción en la tabla de auditoría si existe
    $stmtAud = $db->prepare("
        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, detalle, fecha) 
        VALUES (:usuario_id, 'INSERT', 'ventas', :detalle, CURRENT_TIMESTAMP)
    ");
    $stmtAud->execute([
        ':usuario_id' => $_SESSION['usuario_id'] ?? 1,
        ':detalle' => "Registró venta {$tipo_comprobante} {$serie}-{$numero} por S/ " . number_format($total, 2)
    ]);

    $db->commit();
    
    header("Location: /carpicenter_sys/views/venta_view.php?id=" . $venta_id);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error al procesar la venta: " . $e->getMessage());
}
?>
