<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de guía no proporcionado.");

// Obtener detalles de la guía de remisión
$stmt = $db->prepare("
    SELECT g.*, v.tipo_comprobante as venta_tipo, v.serie as venta_serie, v.numero as venta_numero, v.total as venta_total, v.fecha as venta_fecha
    FROM guias_remision g
    LEFT JOIN ventas v ON g.venta_id = v.id
    WHERE g.id = :id
");
$stmt->execute([':id' => $id]);
$guia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guia) die("Guía de remisión no encontrada.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía de Remisión <?= htmlspecialchars($guia['codigo']) ?></title>
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
        .header-section {
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
        .document-box {
            flex: 1;
            border: 2px solid #E31E24;
            border-radius: 8px;
            text-align: center;
            padding: 15px;
            background: #fff5f5;
        }
        .document-box h3 {
            margin: 0;
            font-size: 14px;
            color: #333;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .document-box h2 {
            margin: 5px 0;
            font-size: 18px;
            color: #E31E24;
            font-weight: 700;
        }
        .document-box h4 {
            margin: 0;
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        .details-panel {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: #fafafa;
            font-size: 12px;
        }
        .details-panel h4 {
            margin: 0 0 10px 0;
            color: #E31E24;
            font-size: 12px;
            text-transform: uppercase;
        }
        .details-panel p {
            margin: 5px 0;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            margin-left: 5px;
        }
        .badge-FACTURADA { background-color: #e8f5e9; color: #2e7d32; }
        .badge-NO_FACTURADA { background-color: #fff8e1; color: #f57f17; }
        
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
        <a href="guias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Panel de Guías</a>
        <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / PDF</button>
    </div>

    <div class="page">
        <!-- Encabezado -->
        <div class="header-section">
            <div class="company-details">
                <h2>INDUSTRIAS CARPICENTER</h2>
                <p><strong>Despacho y Traslado de Bienes</strong></p>
                <p>Calle Unión Mz L1 Lt 33 Parque Industrial, Villa El Salvador, Lima</p>
                <p>Teléfono: 961 848 993 | Email: logistica@carpicenter.com</p>
            </div>
            <div class="document-box">
                <h3>R.U.C. 20555889616</h3>
                <h2>GUÍA DE REMISIÓN</h2>
                <h4><?= htmlspecialchars($guia['codigo']) ?></h4>
            </div>
        </div>

        <!-- Información de Ruta y Envío -->
        <div class="info-grid">
            <div class="info-section">
                <h4>Puntos de Traslado</h4>
                <p><strong>Punto de Partida:</strong> <?= htmlspecialchars($guia['punto_partida']) ?></p>
                <p><strong>Punto de Llegada:</strong> <?= htmlspecialchars($guia['punto_llegada']) ?></p>
                <p><strong>Motivo de Traslado:</strong> <?= htmlspecialchars($guia['motivo_traslado']) ?></p>
            </div>
            <div class="info-section" style="border-left: 1px solid #eee; padding-left: 20px;">
                <h4>Datos del Destinatario</h4>
                <p><strong>Señor(es):</strong> <?= htmlspecialchars($guia['destinatario_nombre']) ?></p>
                <p><strong>DNI / RUC:</strong> <?= htmlspecialchars($guia['destinatario_documento'] ?: '-') ?></p>
                <p><strong>Fecha Emisión:</strong> <?= date('d/m/Y H:i', strtotime($guia['fecha_emision'])) ?></p>
            </div>
        </div>

        <!-- Panel de Relación Administrativa (Venta) -->
        <div class="details-panel">
            <h4>Asociación de Facturación y Estado de Entrega</h4>
            <p><strong>Estado de Facturación:</strong> <span class="status-badge badge-<?= $guia['estado_facturacion'] ?>"><?= $guia['estado_facturacion'] ?></span></p>
            <p style="margin-top:5px;"><strong>Estado del Despacho:</strong> <span class="status-badge" style="background:#e3f2fd;color:#1565c0;"><?= htmlspecialchars($guia['estado_entrega'] ?? 'PENDIENTE') ?></span></p>
            
            <?php if (!empty($guia['venta_id'])): ?>
                <div style="margin-top: 10px; padding: 10px; border: 1px dashed rgba(198, 40, 40, 0.3); border-radius: 6px; background: rgba(198, 40, 40, 0.02);">
                    <p style="margin: 3px 0;"><strong>Comprobante Relacionado:</strong> <?= htmlspecialchars($guia['venta_tipo']) ?> <?= htmlspecialchars($guia['venta_serie']) ?>-<?= htmlspecialchars($guia['venta_numero']) ?></p>
                    <p style="margin: 3px 0;"><strong>Monto Comprobante:</strong> S/ <?= number_format($guia['venta_total'], 2) ?></p>
                    <p style="margin: 3px 0;"><strong>Fecha Comprobante:</strong> <?= date('d/m/Y H:i', strtotime($guia['venta_fecha'])) ?></p>
                </div>
            <?php else: ?>
                <div style="margin-top: 10px; padding: 10px; border: 1px dashed #ddd; border-radius: 6px; background: #fafafa; color: #666;">
                    <i class="fas fa-exclamation-triangle"></i> Esta guía no se encuentra asociada a ningún comprobante de pago interno (Factura o Boleta).
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Obtener evidencias de entrega adjuntas
        $stmtCargos = $db->prepare("SELECT * FROM documentos_adjuntos WHERE referencia_id = :id AND tipo LIKE 'guia_%' ORDER BY fecha_subida DESC");
        $stmtCargos->execute([':id' => $id]);
        $cargosAdjuntos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (!empty($cargosAdjuntos)): ?>
            <div class="details-panel no-print" style="background: #f0f7ff; border-color: #bbdefb;">
                <h4 style="color:#1565c0;"><i class="fas fa-paperclip"></i> Evidencias Documentarias Adjuntas (Cargos Firmados / Guía Transportista)</h4>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
                    <?php foreach($cargosAdjuntos as $doc): 
                        $tipoLabel = str_replace(['guia_', '_'], ['', ' '], $doc['tipo']);
                        $isImg = preg_match('/\.(jpg|jpeg|png)$/i', $doc['ruta']);
                    ?>
                        <div style="border: 1px solid #90caf9; border-radius: 6px; padding: 8px; background: white; text-align: center; font-size: 11px;">
                            <?php if ($isImg): ?>
                                <a href="/carpicenter_sys/<?= htmlspecialchars($doc['ruta']) ?>" target="_blank">
                                    <img src="/carpicenter_sys/<?= htmlspecialchars($doc['ruta']) ?>" alt="Evidencia" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block; margin-bottom: 4px;">
                                </a>
                            <?php else: ?>
                                <a href="/carpicenter_sys/<?= htmlspecialchars($doc['ruta']) ?>" target="_blank" style="display:block; width:80px; height:80px; line-height:80px; background:#e3f2fd; border-radius:4px; font-size:24px; color:#1565c0;">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            <?php endif; ?>
                            <span style="font-weight:bold; text-transform:uppercase; font-size:9px; color:#333;"><?= htmlspecialchars($tipoLabel) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Firma e Instrucciones -->
        <div style="margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; text-align: center; font-size: 11px;">
            <div style="border-top: 1px solid #888; padding-top: 8px; margin-top: 40px;">
                Firma del Conductor / Transportista
            </div>
            <div style="border-top: 1px solid #888; padding-top: 8px; margin-top: 40px;">
                Firma del Destinatario (Recibí Conforme)
            </div>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($guia['observaciones'])): ?>
            <div class="details-panel" style="margin-top: 40px;">
                <h4 style="margin: 0 0 5px 0; font-size: 10px; color:#555;">Observaciones Adicionales</h4>
                <p style="margin: 0; color:#555; line-height: 1.4; font-style: italic; font-size: 11px;">
                    "<?= nl2br(htmlspecialchars($guia['observaciones'])) ?>"
                </p>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="margin-top: auto; border-top:1px solid #ddd; padding-top:15px; font-size:10px; color:#777; text-align:center;">
            <p>Guía de Remisión oficial del transportista de Industrias Carpicenter.</p>
            <p>Villa El Salvador, Lima - Perú</p>
        </div>
    </div>
</body>
</html>
