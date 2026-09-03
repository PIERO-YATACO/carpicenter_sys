<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de venta no proporcionado.");

// Obtener cabecera de la venta
$stmt = $db->prepare("
    SELECT v.*, c.nombre as cliente_nombre, c.dni_ruc as cliente_documento, c.direccion as cliente_direccion, c.email as cliente_email
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE v.id = :id
");
$stmt->execute([':id' => $id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) die("Venta no encontrada.");

// Obtener detalles de la venta
$stmtDet = $db->prepare("
    SELECT vd.*, p.nombre as producto_nombre
    FROM venta_detalles vd
    LEFT JOIN productos p ON vd.producto_id = p.id
    WHERE vd.venta_id = :id
    ORDER BY vd.id ASC
");
$stmtDet->execute([':id' => $id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Si tiene cotización asociada, obtener detalles de la cotización para recuperar descripciones personalizadas
$cotizacion_detalles = [];
if (!empty($venta['cotizacion_id'])) {
    $stmtCotDet = $db->prepare("
        SELECT * FROM cotizacion_detalle 
        WHERE cotizacion_id = :cot_id 
        ORDER BY id ASC
    ");
    $stmtCotDet->execute([':cot_id' => $venta['cotizacion_id']]);
    $cotizacion_detalles = $stmtCotDet->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante <?= htmlspecialchars($venta['serie'] . '-' . $venta['numero']) ?></title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #525659;
            display: flex;
            justify-content: center;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            background-color: white;
            position: relative;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            margin: 20mm auto;
            box-sizing: border-box;
            padding: 20mm 15mm;
            display: flex;
            flex-direction: column;
            color: #333;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .company-details {
            flex: 1.5;
        }
        .company-details h2 {
            margin: 0;
            color: #E31E24;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .company-details p {
            margin: 3px 0;
            font-size: 11px;
            color: #666;
        }
        .invoice-box {
            flex: 1;
            border: 2px solid #E31E24;
            border-radius: 8px;
            text-align: center;
            padding: 15px;
            background: #fff5f5;
        }
        .invoice-box h3 {
            margin: 0;
            font-size: 15px;
            color: #333;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .invoice-box h2 {
            margin: 5px 0;
            font-size: 20px;
            color: #E31E24;
        }
        .invoice-box h4 {
            margin: 0;
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            padding: 15px 0;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .info-section h4 {
            margin: 0 0 8px 0;
            color: #E31E24;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .info-section p {
            margin: 4px 0;
            line-height: 1.4;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #E31E24;
            color: white;
            text-align: left;
            padding: 8px 10px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .items-table tr:last-child td {
            border-bottom: 2px solid #E31E24;
        }
        .totals-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 12px;
        }
        .payment-status-box {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px;
            width: 50%;
            background: #fafafa;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            margin-left: 5px;
        }
        .badge-PAGADO { background-color: #e8f5e9; color: #2e7d32; }
        .badge-PENDIENTE { background-color: #fff8e1; color: #f57f17; }
        .badge-VENCIDO { background-color: #ffebee; color: #E31E24; }
        .badge-NO_ENVIADO { background-color: #eceff1; color: #455a64; }
        
        .totals-box {
            width: 40%;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
        }
        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #E31E24;
            border-top: 1px solid #ddd;
            padding-top: 6px;
            margin-top: 4px;
        }
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
        }
        .btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            cursor: pointer;
            border-radius: 6px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-secondary { background-color: #6c757d; }
        .btn-print { background-color: #E31E24; }
        
        @media print {
            body { background-color: white; }
            .page { box-shadow: none; margin: 0; width: 100%; border: none; padding: 10mm 10mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="controls no-print">
        <a href="ventas.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Panel de Ventas</a>
        <?php if (!empty($venta['cotizacion_id'])): ?>
            <a href="/carpicenter_sys/modules/cotizaciones/cotizacion_view.php?id=<?= $venta['cotizacion_id'] ?>" class="btn"><i class="fas fa-file-invoice"></i> Ver Cotización</a>
        <?php endif; ?>
        <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / PDF</button>
        <a href="/carpicenter_sys/views/guia_nueva.php?venta_id=<?= $id ?>" class="btn" style="background-color:#2E7D32;"><i class="fas fa-truck"></i> Generar Guía</a>
    </div>

    <div class="page">
        <!-- Encabezado -->
        <div class="invoice-header">
            <div class="company-details">
                <h2>INDUSTRIAS CARPICENTER</h2>
                <p><strong>Muebles de Madera y Diseños Exclusivos</strong></p>
                <p>Calle Unión Mz L1 Lt 33 Parque Industrial, Villa El Salvador, Lima</p>
                <p>Teléfono: 961 848 993 | Email: ventas@carpicenter.com</p>
            </div>
            <div class="invoice-box">
                <h3>R.U.C. 20555889616</h3>
                <h2><?= htmlspecialchars($venta['tipo_comprobante']) ?></h2>
                <h4><?= htmlspecialchars($venta['serie'] . '-' . $venta['numero']) ?></h4>
            </div>
        </div>

        <!-- Información del Cliente y Pago -->
        <div class="info-grid">
            <div class="info-section">
                <h4>Datos del Cliente</h4>
                <p><strong>Señor(es):</strong> <?= htmlspecialchars($venta['cliente_nombre']) ?></p>
                <p><strong>Documento (DNI/RUC):</strong> <?= htmlspecialchars($venta['cliente_documento'] ?: '-') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($venta['cliente_direccion'] ?: '-') ?></p>
                <?php if (!empty($venta['cliente_email'])): ?>
                    <p><strong>Email:</strong> <?= htmlspecialchars($venta['cliente_email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="info-section" style="border-left: 1px solid #eee; padding-left: 20px;">
                <h4>Detalles de Facturación</h4>
                <p><strong>Fecha Emisión:</strong> <?= date('d/m/Y', strtotime($venta['fecha_emision'])) ?></p>
                <p><strong>Fecha Pago:</strong> <?= $venta['fecha_pago'] ? date('d/m/Y', strtotime($venta['fecha_pago'])) : '-' ?></p>
                <p><strong>Moneda:</strong> Soles (S/)</p>
                <p><strong>Medio de Pago:</strong> Transferencia Bancaria</p>
            </div>
        </div>

        <!-- Tabla de Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%; text-align:center;">Cant.</th>
                    <th>Descripción</th>
                    <th style="width: 20%; text-align: right;">Precio Unitario</th>
                    <th style="width: 20%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $index => $det): 
                    // Obtener la descripción del detalle de la cotización si existe, o usar el nombre del producto, o fallback
                    $descripcion = "Producto / Servicio General";
                    if (!empty($det['producto_nombre'])) {
                        $descripcion = $det['producto_nombre'];
                    } elseif (isset($cotizacion_detalles[$index])) {
                        $descripcion = $cotizacion_detalles[$index]['descripcion'];
                    }
                    
                    $subtotal = $det['cantidad'] * $det['precio_historico'];
                ?>
                <tr>
                    <td style="text-align:center;"><?= $det['cantidad'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($descripcion) ?></strong>
                        <?php if (isset($cotizacion_detalles[$index]) && !empty($cotizacion_detalles[$index]['especificaciones'])): ?>
                            <div style="font-size: 10px; color: #666; margin-top:3px; line-height: 1.3;">
                                <?= nl2br(htmlspecialchars($cotizacion_detalles[$index]['especificaciones'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">S/ <?= number_format($det['precio_historico'], 2) ?></td>
                    <td style="text-align:right; font-weight:bold;">S/ <?= number_format($subtotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totales y Estado -->
        <div class="totals-section">
            <div class="payment-status-box">
                <h4 style="margin: 0 0 8px 0; font-size:11px; color:#E31E24; text-transform:uppercase;">Estado Administrativo</h4>
                <p style="margin:4px 0;">Estado de Pago: <span class="status-badge badge-<?= $venta['estado_pago'] ?>"><?= $venta['estado_pago'] ?></span></p>
                <p style="margin:4px 0;">Estado SUNAT: <span class="status-badge badge-<?= $venta['estado_sunat'] ?>"><?= $venta['estado_sunat'] ?></span></p>
                
                <?php if ($venta['estado_sunat'] !== 'NO_ENVIADO'): ?>
                    <p style="margin:4px 0; font-size:10px; color:#555;">Hash CDR: <code style="font-size:9px;"><?= htmlspecialchars($venta['sunat_hash']) ?></code></p>
                <?php endif; ?>
            </div>
            
            <div class="totals-box">
                <div class="total-row">
                    <span>Op. Gravada (Neto):</span>
                    <span>S/ <?= number_format($venta['total'] / 1.18, 2) ?></span>
                </div>
                <div class="total-row">
                    <span>I.G.V. (18%):</span>
                    <span>S/ <?= number_format($venta['total'] - ($venta['total'] / 1.18), 2) ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>TOTAL COMPROBANTE:</span>
                    <span>S/ <?= number_format($venta['total'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Paquete Completo de Documentos Venta / Logística -->
        <?php
        // Buscar Guías asociadas a esta venta
        $stmtG = $db->prepare("SELECT * FROM guias_remision WHERE venta_id = :vid");
        $stmtG->execute([':vid' => $id]);
        $guiasVenta = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        $cargosTotales = [];
        foreach ($guiasVenta as $gV) {
            $stmtC = $db->prepare("SELECT * FROM documentos_adjuntos WHERE referencia_id = :gid AND tipo LIKE 'guia_%'");
            $stmtC->execute([':gid' => $gV['id']]);
            $cargosTotales = array_merge($cargosTotales, $stmtC->fetchAll(PDO::FETCH_ASSOC));
        }
        ?>
        <div style="margin-top: 25px; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa; font-size: 11px;" class="no-print">
            <h4 style="margin: 0 0 8px 0; color: #E31E24; text-transform: uppercase; font-size: 11px;"><i class="fas fa-folder-open"></i> Expediente Documentario Integrado</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                <div>
                    <strong>Cotización:</strong> 
                    <?php if (!empty($venta['cotizacion_id'])): ?>
                        <a href="/carpicenter_sys/modules/cotizaciones/cotizacion_view.php?id=<?= $venta['cotizacion_id'] ?>" target="_blank" style="color: #1565c0; text-decoration: none;">
                            <i class="fas fa-file-invoice"></i> COT-<?= str_pad($venta['cotizacion_id'], 5, '0', STR_PAD_LEFT) ?>
                        </a>
                    <?php else: ?>
                        <span style="color:#888;">—</span>
                    <?php endif; ?>
                </div>

                <div>
                    <strong>Comprobante:</strong> 
                    <span style="color: #2e7d32;"><i class="fas fa-receipt"></i> <?= htmlspecialchars($venta['tipo_comprobante'] . ' ' . $venta['serie'] . '-' . $venta['numero']) ?></span>
                </div>

                <div>
                    <strong>Guía de Remisión:</strong>
                    <?php if (!empty($guiasVenta)): ?>
                        <?php foreach($guiasVenta as $gV): ?>
                            <a href="guia_view.php?id=<?= $gV['id'] ?>" target="_blank" style="color: #1565c0; text-decoration: none; margin-right: 5px;">
                                <i class="fas fa-truck"></i> <?= htmlspecialchars($gV['codigo']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#888;">Sin guía</span>
                    <?php endif; ?>
                </div>

                <div>
                    <strong>Cargos Firmados / Evidencias:</strong>
                    <?php if (!empty($cargosTotales)): ?>
                        <span style="color: #2e7d32; font-weight: bold;"><i class="fas fa-check-circle"></i> <?= count($cargosTotales) ?> documento(s) adjunto(s)</span>
                    <?php else: ?>
                        <span style="color:#f57f17;"><i class="fas fa-clock"></i> Pendiente de adjuntar</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer del documento -->
        <div style="margin-top: auto; border-top:1px solid #ddd; padding-top:15px; font-size:10px; color:#777; text-align:center;">
            <p>Representación impresa de la Factura de Venta interna de Industrias Carpicenter.</p>
            <p>Gracias por su preferencia.</p>
        </div>
    </div>
</body>
</html>
