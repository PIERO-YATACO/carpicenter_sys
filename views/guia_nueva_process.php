<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /carpicenter_sys/views/guias.php");
    exit;
}

$codigo = trim($_POST['codigo'] ?? '');
$venta_id = !empty($_POST['venta_id']) ? intval($_POST['venta_id']) : null;
$destinatario_nombre = trim($_POST['destinatario_nombre'] ?? '');
$destinatario_documento = trim($_POST['destinatario_documento'] ?? '');
$punto_partida = trim($_POST['punto_partida'] ?? '');
$punto_llegada = trim($_POST['punto_llegada'] ?? '');
$motivo_traslado = $_POST['motivo_traslado'] ?? 'Venta';
$fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d H:i:s');
$observaciones = trim($_POST['observaciones'] ?? '');

if (empty($codigo) || empty($destinatario_nombre) || empty($punto_llegada)) {
    die("Error: Faltan datos requeridos para registrar la guía de remisión.");
}

// Determinar estado de facturacion
// Regla: Si existe una venta asociada -> "FACTURADA", si no -> "NO_FACTURADA"
$estado_facturacion = ($venta_id !== null) ? 'FACTURADA' : 'NO_FACTURADA';

try {
    $db->beginTransaction();

    // Intentar buscar un cliente_id correspondiente al destinatario_documento si existe
    $cliente_id = null;
    if (!empty($destinatario_documento)) {
        $stmtC = $db->prepare("SELECT id FROM clientes WHERE dni_ruc = :dni_ruc LIMIT 1");
        $stmtC->execute([':dni_ruc' => $destinatario_documento]);
        $cliente_id = $stmtC->fetchColumn() ?: null;
    }

    // Insertar guía de remisión
    $stmtGR = $db->prepare("
        INSERT INTO guias_remision (
            codigo, venta_id, cliente_id, fecha_emision, estado_facturacion, 
            estado, destinatario_nombre, destinatario_documento, punto_partida, 
            punto_llegada, motivo_traslado, observaciones
        ) VALUES (
            :codigo, :venta_id, :cliente_id, :fecha_emision, :estado_facturacion, 
            'Emitida', :destinatario_nombre, :destinatario_documento, :punto_partida, 
            :punto_llegada, :motivo_traslado, :observaciones
        ) RETURNING id
    ");

    $stmtGR->execute([
        ':codigo' => $codigo,
        ':venta_id' => $venta_id,
        ':cliente_id' => $cliente_id,
        ':fecha_emision' => $fecha_emision,
        ':estado_facturacion' => $estado_facturacion,
        ':destinatario_nombre' => $destinatario_nombre,
        ':destinatario_documento' => $destinatario_documento ?: null,
        ':punto_partida' => $punto_partida,
        ':punto_llegada' => $punto_llegada,
        ':motivo_traslado' => $motivo_traslado,
        ':observaciones' => $observaciones ?: null
    ]);

    $guia_id = $stmtGR->fetchColumn();

    // Guardar auditoría si existe la tabla
    $stmtAud = $db->prepare("
        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, detalle, fecha) 
        VALUES (:usuario_id, 'INSERT', 'guias_remision', :detalle, CURRENT_TIMESTAMP)
    ");
    $stmtAud->execute([
        ':usuario_id' => $_SESSION['usuario_id'] ?? 1,
        ':detalle' => "Generó guía de remisión {$codigo} para {$destinatario_nombre} (Facturación: {$estado_facturacion})"
    ]);

    $db->commit();

    header("Location: /carpicenter_sys/views/guia_view.php?id=" . $guia_id);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error al registrar la guía de remisión: " . $e->getMessage());
}
?>
