<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/cotizacion_model.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de cotización no proporcionado.");

$model = new CotizacionModel($db);
$cotizacion = $model->getById($id);
if (!$cotizacion) die("Cotización no encontrada.");

$detalles = $cotizacion['detalles'];

// Verificar si ya existe una venta asociada a esta cotización
$stmtVenta = $db->prepare("SELECT id FROM ventas WHERE cotizacion_id = :cotizacion_id LIMIT 1");
$stmtVenta->execute([':cotizacion_id' => $id]);
$venta_existente = $stmtVenta->fetch(PDO::FETCH_ASSOC);

if (!function_exists('formatEspecificaciones')) {
    function formatEspecificaciones($text) {
        if (empty($text)) return '';
        $lines = explode("\n", $text);
        $result = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $result[] = '';
                continue;
            }
            $lower = mb_strtolower($trimmed, 'UTF-8');
            $first = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8');
            $rest = mb_substr($lower, 1, null, 'UTF-8');
            $result[] = $first . $rest;
        }
        return implode("\n", $result);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización <?= htmlspecialchars($cotizacion['numero']) ?></title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #525659; /* Background for PDF viewer */
            display: flex;
            justify-content: center;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            height: auto;
            background-color: white;
            position: relative;
            box-shadow: 0 0 12px rgba(0,0,0,0.4);
            margin: 15mm auto;
            display: flex;
            align-items: stretch;
            box-sizing: border-box;
            overflow: visible;
        }
        /* Left Red Banner - Thinner style matching reference */
        .banner {
            width: 28mm;
            background-color: #e31e24;
            color: white;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            align-self: stretch;
        }
        .banner-text {
            transform: rotate(-90deg);
            font-size: 24px;
            font-weight: 900;
            white-space: nowrap;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #ffffff;
            position: sticky;
            top: 40vh;
        }
        
        /* Main Content Area */
        .content {
            flex: 1;
            padding: 12mm 15mm 15mm 10mm;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .company-info {
            flex: 2.4;
        }
        .company-info .brand-logo-img img {
            max-width: 395px;
            width: 100%;
            height: auto;
            display: block;
            margin-bottom: 4px;
        }
        .company-info p.company-address {
            margin: 4px 0 3px 0;
            font-size: 11px;
            font-weight: 500;
            color: #111111;
            letter-spacing: 0.1px;
            text-transform: uppercase;
        }
        .company-info p.company-ruc {
            margin: 0;
            font-size: 10.5px;
            color: #111111;
        }
        .company-info p.company-ruc b, .company-info p.company-ruc i {
            font-weight: 700;
            font-style: italic;
        }

        .header-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .header-right .logo-img img {
            max-height: 85px;
            width: auto;
            display: block;
            margin-bottom: 4px;
        }
        .meta-info {
            text-align: right;
            font-size: 10.5px;
            line-height: 1.4;
            margin-bottom: 8px;
        }
        .meta-info p {
            margin: 1px 0;
            color: #111111;
        }
        .meta-info b, .meta-info i {
            font-weight: 700;
            font-style: italic;
        }
        .meta-info .author-name {
            color: #e31e24;
            font-weight: 700;
            font-style: italic;
        }
        .meta-info p { margin: 2px 0; }
        .meta-info span { color: #e31e24; font-weight: bold; }

        /* Client Info */
        .client-info {
            font-size: 11px;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        .client-info .label {
            font-weight: bold;
            color: #e31e24;
            font-size: 11.5px;
        }
        .client-info b { color: #000; }

        /* Payment Methods */
        .payment-methods {
            font-size: 10.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .payment-methods strong { font-size: 11px; white-space: nowrap; }
        .payment-list { line-height: 1.5; font-size: 10.5px; flex: 1; }
        .payment-badges {
            display: inline-flex;
            gap: 4px;
            margin-right: 8px;
            vertical-align: middle;
        }
        .badge-icon {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            color: white;
        }
        .badge-bbva { background: #004481; }
        .badge-bcp { background: #002a8f; }
        .badge-interbank { background: #009933; }
        .badge-yape { background: #731384; }
        .badge-plin { background: #00a9e0; }

        /* Comments & Conditions */
        .comments {
            font-size: 10px;
            line-height: 1.35;
            margin-bottom: 15px;
        }
        .comments .red-label { color: #e31e24; font-weight: bold; text-transform: uppercase; }
        .comments .red-conditions {
            color: #e31e24;
            font-weight: bold;
            font-style: italic;
            margin-top: 3px;
            display: block;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11px;
            border: 1px solid #000;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .items-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
        }
        .items-table .qty-col { width: 80px; }
        .items-table .qty-val { font-weight: bold; text-align: left; }
        .items-table .desc-box {
            min-height: 65px;
        }
        .items-table .prod-title {
            color: #e31e24;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .items-table .prod-desc {
            font-size: 10.5px;
            color: #111;
            line-height: 1.35;
            text-transform: uppercase;
        }
        .items-table .prod-specs {
            margin-top: 6px;
            font-size: 10px;
            color: #333333;
            line-height: 1.45;
            background: #f9f9f9;
            padding: 6px 10px;
            border-left: 3px solid #e31e24;
            border-radius: 0 4px 4px 0;
            font-style: normal;
            word-wrap: break-word;
        }
        .items-table .img-col {
            width: 135px;
            text-align: center;
            vertical-align: middle;
            padding: 0;
            background: #fafafa;
        }
        .items-table .img-header {
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            background: #ffffff;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            color: #333;
            letter-spacing: 0.5px;
        }
        .items-table .img-body {
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 135px;
            height: 115px;
            box-sizing: border-box;
            background: #ffffff;
            margin: 0 auto;
            overflow: hidden;
        }
        .items-table .img-body img {
            max-width: 123px;
            max-height: 103px;
            width: auto;
            height: auto;
            object-fit: contain;
            object-position: center;
            display: block;
        }

        /* Total Bar */
        .total-bar-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .total-bar {
            display: flex;
            width: 280px;
            border: 1.5px solid #000;
            align-items: center;
            height: 36px;
        }
        .total-label {
            background-color: #e31e24;
            color: white;
            font-weight: bold;
            font-size: 18px;
            padding: 0 15px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1.5px solid #000;
            flex: 1.2;
        }
        .total-currency {
            padding: 0 10px;
            font-size: 16px;
            font-weight: bold;
            border-right: 1px solid #000;
            height: 100%;
            display: flex;
            align-items: center;
        }
        .total-amount {
            padding: 0 15px;
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            flex: 1.5;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .quote-number-badge {
            background-color: #e31e24;
            color: #ffffff;
            font-weight: 900;
            font-size: 13.5px;
            padding: 5px 12px;
            border-radius: 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(227, 30, 36, 0.2);
        }

        /* Force background colors and exact print colors */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        /* Print Media */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0 !important;
            }
            html, body {
                background-color: white !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 210mm !important;
                height: auto !important;
                min-height: 100% !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page {
                box-shadow: none !important;
                margin: 0 !important;
                width: 210mm !important;
                min-height: 297mm !important;
                height: auto !important;
                overflow: visible !important;
                border: none !important;
                display: flex !important;
                align-items: stretch !important;
            }
            .banner {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                bottom: 0 !important;
                width: 28mm !important;
                height: 100vh !important;
                background-color: #e31e24 !important;
                z-index: 1000 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .banner-text {
                transform: rotate(-90deg) !important;
                font-size: 22px !important;
                font-weight: 900 !important;
                letter-spacing: 3px !important;
                text-transform: uppercase !important;
                color: #ffffff !important;
                white-space: nowrap !important;
                position: static !important;
                margin: 0 !important;
            }
            .content {
                padding: 10mm 15mm 15mm 35mm !important;
                flex: 1 !important;
            }
            .items-table {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-bottom: 12px !important;
            }
            .header, .meta-info, .client-info, .payment-methods, .comments, .total-bar-container {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .no-print {
                display: none !important;
            }
        }

        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            z-index: 1000;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            background-color: #007bff; color: white; border: none; padding: 8px 14px; cursor: pointer; border-radius: 4px; font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }
        .btn-print { background-color: #e31e24; }
        .btn-edit { background-color: #f39c12; }
    </style>
</head>
<body>
    <div class="controls no-print">
        <a href="cotizaciones.php" class="btn"><i class="fas fa-arrow-left"></i> Volver</a>
        <a href="cotizacion_form.php?id=<?= $id ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Editar</a>
        <a href="cotizacion_form.php?duplicate_id=<?= $id ?>" class="btn" style="background-color: #673AB7;" title="Crear nueva versión o duplicar cotización para este cliente"><i class="fas fa-copy"></i> Duplicar / Nueva Versión</a>
        <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / PDF</button>
        <?php if (($cotizacion['estado'] == 'Aprobada' || $cotizacion['estado'] == 'Aceptada') && !$venta_existente): ?>
            <a href="/carpicenter_sys/views/venta_nueva.php?cotizacion_id=<?= $id ?>" class="btn" style="background-color: #2E7D32;"><i class="fas fa-check"></i> Generar Venta</a>
        <?php elseif ($venta_existente): ?>
            <a href="/carpicenter_sys/views/venta_view.php?id=<?= $venta_existente['id'] ?>" class="btn" style="background-color: #1565C0;"><i class="fas fa-file-invoice"></i> Ver Venta</a>
        <?php elseif ($cotizacion['estado'] == 'Pendiente'): ?>
            <a href="cotizacion_controller.php?action=cambiar_estado&id=<?= $id ?>&nuevo_estado=Anulada" class="btn" style="background-color: #757575;" onclick="return confirm('¿Desea marcar esta cotización como Anulada / No concretada? Su número correlativo se conservará en el sistema.');"><i class="fas fa-ban"></i> Anular Cotización</a>
        <?php endif; ?>
    </div>

    <div class="page">
        <!-- Thin Red Vertical Banner -->
        <?php 
        $numStr = $cotizacion['numero'];
        $numFormatted = (stripos($numStr, 'N°') === false && stripos($numStr, 'Nº') === false) ? 'N° ' . $numStr : $numStr;
        $estadoTag = in_array(strtolower($cotizacion['estado'] ?? ''), ['anulada', 'rechazada']) ? ' (' . strtoupper($cotizacion['estado']) . ')' : '';
        ?>
        <div class="banner">
            <div class="banner-text">COTIZACIÓN <?= htmlspecialchars($numFormatted . $estadoTag) ?></div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Header -->
            <div class="header">
                <div class="company-info">
                    <div class="brand-logo-img">
                        <img src="/carpicenter_sys/assets/img/logo_text_brand.png" alt="INDUSTRIAS CARPICENTER®">
                    </div>
                    <p class="company-address">CALLE UNIÓN MZ L1 LT 33 PARQUE INDUSTRIAL V.E.S.</p>
                    <p class="company-ruc"><b><i>Ruc: 20555889616</i></b></p>
                </div>
                <div class="header-right">
                    <div class="logo-img">
                        <img src="/carpicenter_sys/assets/img/logo_bird_clean.png" alt="Logo Carpicenter">
                    </div>
                    <div class="meta-info">
                        <p><b><i>Fecha:</i></b> <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></p>
                        <p><b><i>Presupuesto válido hasta:</i></b> <?= $cotizacion['fecha_validez'] ? date('d/m/Y', strtotime($cotizacion['fecha_validez'])) : '-' ?></p>
                        <p><b><i>Asesor Comercial:</i></b> <span class="author-name"><?= htmlspecialchars($cotizacion['vendedor_display'] ?? ($_SESSION['nombre_completo'] ?? 'Carpicenter')) ?></span></p>
                        <?php if(!empty($cotizacion['local_display']) && $cotizacion['local_display'] !== 'Sin Tienda'): ?>
                        <p><b><i>Sede / Tienda:</i></b> <span><?= htmlspecialchars($cotizacion['local_display']) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Client info -->
            <div class="client-info">
                <span class="label">Presupuesto para:</span><br>
                <b>Nombre del cliente:</b> <?= htmlspecialchars($cotizacion['cliente_nombre']) ?><br>
                <b>RUC/DNI:</b> <?= htmlspecialchars($cotizacion['cliente_documento'] ?: '—') ?>
                <?php if(!empty($cotizacion['cliente_telefono'])): ?>
                    &nbsp;&nbsp;|&nbsp;&nbsp;<b>Teléfono / Celular:</b> <?= htmlspecialchars($cotizacion['cliente_telefono']) ?>
                <?php endif; ?><br>
                <b>Dirección:</b> <?= htmlspecialchars($cotizacion['cliente_direccion'] ?: '—') ?>
            </div>

            <!-- Payment methods -->
            <div class="payment-methods">
                <strong>Formas de pago:</strong>
                <div class="payment-list">
                    <span class="badge-icon badge-bbva">BBVA</span> CTA: 0011-0234-0100016675 CCI: 011-234-000100-016675-29<br>
                    <span class="badge-icon badge-bcp">BCP</span> CTA: 194-2263097-0-64 CCI: 002-194-002263097064-92<br>
                    <span class="badge-icon badge-interbank">INTERBANK</span> CTA: 200-3004922679 CCI: 003-200-003004922679-35<br>
                    <span class="badge-icon badge-yape">YAPE</span> / <span class="badge-icon badge-plin">PLIN</span>: 961 848 993
                </div>
            </div>

            <!-- Comments & Conditions -->
            <div class="comments">
                <span class="red-label">Comentarios y/o Observaciones:</span> <?= nl2br(htmlspecialchars($cotizacion['observaciones'])) ?>
                <?php if(!empty($cotizacion['condiciones'])): ?>
                <span class="red-conditions"><?= nl2br(htmlspecialchars($cotizacion['condiciones'])) ?></span>
                <?php endif; ?>
            </div>

            <!-- Product Items -->
            <?php foreach($detalles as $det): ?>
            <table class="items-table">
                <tr>
                    <td class="qty-col">Cantidad</td>
                    <td class="qty-val" style="width: 55%;"><?= htmlspecialchars($det['cantidad']) ?></td>
                    <td class="img-col" rowspan="4">
                        <div class="img-header">IMAGEN REFERENCIAL</div>
                        <div class="img-body">
                            <?php if(!empty($det['imagen'])): ?>
                            <img src="<?= htmlspecialchars($det['imagen']) ?>" alt="Producto">
                            <?php else: ?>
                            <div style="font-size: 10px; color: #aaa;">Sin imagen</div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="desc-box">
                        <div class="prod-title"><?= htmlspecialchars($det['descripcion']) ?></div>
                        <?php if(!empty($det['color'])): ?>
                        <div style="font-weight: bold; color: #222; font-size: 11px; margin-top: 3px;">
                            COLOR: <span style="color: #e31e24; text-transform: uppercase;"><?= htmlspecialchars($det['color']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($det['especificaciones'])): ?>
                        <div class="prod-specs">
                            <?= nl2br(htmlspecialchars(formatEspecificaciones($det['especificaciones']))) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="qty-col">Precio por unidad</td>
                    <td style="text-align: right;"><?= number_format($det['precio_unitario'], 2) ?></td>
                </tr>
                <tr>
                    <td class="qty-col">Importe</td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($det['subtotal'], 2) ?></td>
                </tr>
            </table>
            <?php endforeach; ?>

            <!-- Optional Costs -->
            <div style="display: flex; flex-direction: column; align-items: flex-end; margin-top: 10px; margin-bottom: 5px; font-size: 11px; padding-right: 5px;">
                <?php if (!empty($cotizacion['gastos_logisticos']) && $cotizacion['gastos_logisticos'] > 0): ?>
                <div style="display: flex; width: 280px; justify-content: space-between; margin-bottom: 3px;">
                    <span style="font-weight: bold;">GASTOS LOGÍSTICOS:</span>
                    <span>S/ <?= number_format($cotizacion['gastos_logisticos'], 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($cotizacion['modificacion_orden_compra']) && $cotizacion['modificacion_orden_compra'] > 0): ?>
                <div style="display: flex; width: 280px; justify-content: space-between; margin-bottom: 3px;">
                    <span style="font-weight: bold;">MODIF. ORDEN COMPRA:</span>
                    <span>S/ <?= number_format($cotizacion['modificacion_orden_compra'], 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($cotizacion['movilidad']) && $cotizacion['movilidad'] > 0): ?>
                <div style="display: flex; width: 280px; justify-content: space-between; margin-bottom: 3px;">
                    <span style="font-weight: bold;">MOVILIDAD:</span>
                    <span>S/ <?= number_format($cotizacion['movilidad'], 2) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Total Bar -->
            <div class="total-bar-container" style="margin-top: 5px;">
                <div class="total-bar">
                    <div class="total-label">TOTAL</div>
                    <div class="total-currency">S/</div>
                    <div class="total-amount"><?= number_format($cotizacion['total'], 2) ?></div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
