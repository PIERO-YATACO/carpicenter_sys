<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/contrato_model.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de contrato requerido.");

$model = new ContratoModel($db);
$contrato = $model->getById($id);

if (!$contrato) die("Contrato no encontrado.");

$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
if ($isSeller && !empty($contrato['vendedor_id']) && $contrato['vendedor_id'] != ($_SESSION['user_id'] ?? 0)) {
    die("Acceso restringido: Solo puedes imprimir los contratos emitidos por tu usuario.");
}

$metodoPagoRaw = strtoupper($contrato['metodo_pago'] ?? 'EFECTIVO');
$metodoEntregaActual = strtoupper($contrato['tipo_entrega'] ?? 'TIENDA');

// Standout bold red checkmark
$chkRed = '<span style="color:#e31e24; font-size:16px; font-weight:900; line-height:1;">✓</span>';

function checkPayMethod($key, $rawUpper, $chkRed) {
    if (str_contains($rawUpper, strtoupper($key))) {
        return $chkRed;
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato <?= htmlspecialchars($contrato['codigo_completo']) ?> - Carpicenter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 8mm 6mm 8mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.25;
            display: flex;
            justify-content: center;
        }
        .contrato-page {
            width: 210mm;
            min-height: 297mm;
            background-color: #ffffff;
            position: relative;
            box-shadow: 0 0 12px rgba(0,0,0,0.35);
            margin: 6mm auto;
            padding: 7mm 10mm 5mm 10mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* TOP HEADER BLOCK (Estilo idéntico a Talonario Físico) */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .logo-area {
            flex: 1.5;
        }
        .logo-img-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .logo-img-wrapper img {
            max-height: 100px;
            width: auto;
            object-fit: contain;
        }
        .company-contacts {
            font-size: 9.5px;
            color: #222;
            line-height: 1.45;
        }
        .company-contacts i {
            color: #e31e24;
            margin-right: 3px;
        }

        /* HEADER RIGHT BOX (RUC + CONTRATO DE VENTA - MÁS GRANDE Y ESPACIOSO) */
        .header-right-box {
            width: 275px;
            border: 2px solid #e31e24;
            border-radius: 6px;
            text-align: center;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .ruc-text {
            font-size: 14.5px;
            font-weight: 800;
            padding: 6px 0 5px 0;
            color: #000;
            letter-spacing: 0.8px;
        }
        .contrato-banner-title {
            background: #e31e24;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 900;
            padding: 5px 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .contrato-num-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            padding: 8px 10px;
        }
        .contrato-serie-text {
            font-size: 19px;
            font-weight: 900;
            color: #111827;
            letter-spacing: 1px;
        }
        .contrato-correlativo-text {
            font-size: 19px;
            font-weight: 900;
            color: #e31e24;
            letter-spacing: 1.5px;
        }

        /* DATES ROW */
        .dates-row {
            display: flex;
            justify-content: flex-end;
            gap: 25px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 6px;
            margin-bottom: 6px;
            padding-right: 5px;
        }

        /* BANNERS */
        .section-banner-black {
            background: #000000;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 900;
            text-align: center;
            padding: 3.5px 0;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .section-banner-red {
            background: #e31e24;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 900;
            text-align: center;
            padding: 3.5px 0;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        /* CLIENT DETAILS FORM TABLE */
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .client-table td {
            padding: 2.5px 3px;
            font-size: 11px;
            vertical-align: middle;
        }
        .line-underline {
            border-bottom: 1px dotted #333;
            font-weight: bold;
            color: #000;
            padding-left: 5px;
        }

        /* ITEMS TABLE */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .items-table th {
            background: #e31e24;
            color: #ffffff;
            font-weight: 900;
            font-size: 10.5px;
            padding: 4px;
            border: 1px solid #e31e24;
            text-align: center;
            text-transform: uppercase;
        }
        .items-table td {
            border: 1px solid #777;
            padding: 3.5px 5px;
            font-size: 10.5px;
        }

        .total-bar-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }
        .total-box {
            display: flex;
            border: 2px solid #e31e24;
            width: 235px;
        }
        .total-lbl {
            background: #e31e24;
            color: #fff;
            font-weight: 900;
            padding: 4px 10px;
            font-size: 11.5px;
            width: 95px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .total-val {
            flex: 1;
            text-align: right;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 900;
            background: #fff;
            white-space: nowrap;
        }

        /* INGRESO DE MERCADERIA TABLE */
        .mercaderia-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 9px;
            text-align: center;
        }
        .mercaderia-table th, .mercaderia-table td {
            border: 1px solid #555;
            padding: 3px 2px;
            height: 22px;
        }
        .mercaderia-table th {
            background: #f0f0f0;
            font-weight: bold;
        }

        /* METHOD 2-COLUMNS (PAYMENT & DELIVERY) */
        .methods-grid {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
        }
        .method-col {
            flex: 1;
        }
        .method-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            text-align: center;
        }
        .method-table th, .method-table td {
            border: 1px solid #555;
            padding: 3px 2px;
            height: 22px;
            vertical-align: middle;
        }

        /* CLAUSES & FINANCIAL SUMMARY */
        .footer-block {
            margin-top: 6px;
        }
        .clauses-text {
            font-size: 8.5px;
            line-height: 1.3;
            text-align: justify;
            color: #222;
            padding-right: 8px;
            flex: 1;
        }
        .clauses-text p {
            margin: 0 0 3px 0;
        }

        /* FINANCIAL SUMMARY TABLE WITH WIDE NON-WRAPPING VALUES */
        .fin-table {
            width: 235px;
            border-collapse: collapse;
            border: 2px solid #e31e24;
            font-size: 11px;
        }
        .fin-table td {
            padding: 5px 8px;
            border: 1px solid #e31e24;
            vertical-align: middle;
        }
        .fin-lbl {
            background: #e31e24;
            color: #fff;
            width: 95px;
            text-align: center;
            font-weight: 900;
            font-size: 10.5px;
            letter-spacing: 0.5px;
        }
        .fin-val {
            background: #fff;
            text-align: right;
            font-size: 12.5px;
            font-weight: bold;
            white-space: nowrap;
        }

        /* SIGNATURES AREA */
        .signatures-row {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-top: 36px;
            margin-bottom: 8px;
        }
        .sig-box {
            width: 250px;
            border-top: 1px dotted #000;
            padding-top: 5px;
            font-size: 10px;
            font-weight: bold;
        }

        /* PRINT BUTTON */
        .no-print-bar {
            position: fixed;
            top: 15px;
            right: 20px;
            z-index: 9999;
        }
        .btn-print-main {
            background: #e31e24;
            color: #fff;
            border: none;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @media print {
            .no-print-bar { display: none !important; }
            body { background: #fff; display: block; }
            .contrato-page {
                box-shadow: none;
                margin: 0;
                width: 100%;
                min-height: 100vh;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn-print-main" onclick="window.print()">🖨️ Imprimir Contrato Talonario</button>
</div>

<div class="contrato-page">

    <div>
        <!-- HEADER TOP (Logo arriba y texto de contacto abajo igual que Nota de Venta) -->
        <div class="header-section">
            <div class="logo-area">
                <div class="logo-img-wrapper">
                    <img src="/carpicenter_sys/assets/img/logo_carpicenter_official.png" alt="Carpicenter Logo">
                </div>
                <div class="company-contacts">
                    <p style="margin:2px 0;"><i class="fab fa-facebook-square"></i> Carpicenterperu &nbsp;|&nbsp; <i class="fas fa-globe"></i> www.carpicenter.com.pe</p>
                    <p style="margin:2px 0;"><i class="fas fa-map-marker-alt"></i> Calle Unión Mz. J Sub Lote 6B, Parque Industrial II - V.E.S.</p>
                    <p style="margin:2px 0;"><i class="fas fa-phone-alt"></i> (01) 715-8445 &nbsp;|&nbsp; <i class="fas fa-mobile-alt"></i> 961 849 138 - 925 188 921 &nbsp;|&nbsp; <i class="far fa-envelope"></i> tienda1@carpicenter.com.pe</p>
                </div>
            </div>
            <div>
                <div class="header-right-box">
                    <div class="ruc-text">RUC N° 20555889616</div>
                    <div class="contrato-banner-title">CONTRATO DE VENTA</div>
                    <div class="contrato-num-row">
                        <span class="contrato-serie-text"><?= htmlspecialchars($contrato['serie']) ?> -</span>
                        <span class="contrato-correlativo-text">N° <?= htmlspecialchars($contrato['numero']) ?></span>
                    </div>
                </div>
                <!-- DATES ROW -->
                <div class="dates-row">
                    <div>Fecha: <span style="border-bottom:1px dotted #000; padding:0 6px;"><?= date('d / m / Y', strtotime($contrato['fecha_emision'])) ?></span></div>
                    <div>Fecha de entrega: <span style="border-bottom:1px dotted #000; padding:0 6px;"><?= !empty($contrato['fecha_entrega_estimada']) ? date('d / m / Y', strtotime($contrato['fecha_entrega_estimada'])) : '__ / __ / ____' ?></span></div>
                </div>
            </div>
        </div>

        <!-- DATOS DEL CLIENTE BANNER -->
        <div class="section-banner-black">DATOS DEL CLIENTE</div>

        <!-- CLIENT INFO FORM -->
        <table class="client-table">
            <tr>
                <td style="width: 165px; font-weight: bold;">Apellidos y Nombres / Razón Social:</td>
                <td class="line-underline" colspan="3"><?= htmlspecialchars($contrato['cliente_nombre'] ?? '') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Dirección:</td>
                <td class="line-underline" colspan="3"><?= htmlspecialchars($contrato['direccion_entrega'] ?: ($contrato['cliente_direccion_base'] ?: '—')) ?></td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top: 3px;">
                    <span style="font-weight:bold;">Distribuidor:</span> <span style="border:1px solid #000; padding:0 4px; font-size:10px;">&nbsp;&nbsp;</span> &nbsp;&nbsp;&nbsp;&nbsp;
                    <span style="font-weight:bold;">Cliente Final:</span> <span style="border:1px solid #000; padding:0 3px; font-weight:bold; font-size:11px;"><?= $chkRed ?></span> &nbsp;&nbsp;&nbsp;&nbsp;
                    <span style="font-weight:bold;">DNI / RUC:</span> <span class="line-underline" style="display:inline-block; min-width:130px;"><?= htmlspecialchars($contrato['cliente_doc'] ?: '—') ?></span> &nbsp;&nbsp;&nbsp;&nbsp;
                    <span style="font-weight:bold;">Telf / Cel:</span> <span class="line-underline" style="display:inline-block; min-width:110px;"><?= htmlspecialchars($contrato['cliente_telefono'] ?: '—') ?></span>
                </td>
            </tr>
        </table>

        <!-- ITEMS TABLE (RED HEADER BANNER) -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50px;">CANT.</th>
                    <th>DESCRIPCION</th>
                    <th style="width: 85px;">P.UNIT.</th>
                    <th style="width: 100px;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $items = $contrato['detalles'];
                $costoFlete = floatval($contrato['costo_movilidad'] ?? 0);
                $maxRows = 8;
                $rowCounter = 0;

                foreach ($items as $item):
                    if ($rowCounter >= $maxRows) break;
                    $codeTag = !empty($item['producto_codigo']) ? '[' . $item['producto_codigo'] . '] ' : '';
                    $descText = $codeTag . $item['descripcion'];
                    if (!empty($item['color_nombre'])) {
                        $colCode = !empty($item['color_codigo']) ? ' (' . $item['color_codigo'] . ')' : '';
                        $descText .= ' - Color: ' . $item['color_nombre'] . $colCode;
                    }
                    
                    $orig = $item['origen_item'] ?? 'Producción';
                    if ($orig === 'Stock') $descText .= ' [📦 Stock]';
                    elseif ($orig === 'Proveedor') $descText .= ' [🏭 Proveedor]';

                    if (!empty($item['observaciones_item'])) $descText .= ' (' . $item['observaciones_item'] . ')';
                    $rowCounter++;
                ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;"><?= str_pad($item['cantidad'], 2, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($descText) ?></td>
                    <td style="text-align: right;">S/ <?= number_format($item['precio_unitario'], 2) ?></td>
                    <td style="text-align: right; font-weight: bold;">S/ <?= number_format($item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if ($costoFlete > 0 && $rowCounter < $maxRows): $rowCounter++; ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;">01</td>
                    <td style="font-weight: bold; color: #e31e24;">🚚 SERVICIO DE MOVILIDAD / FLETE DE ENTREGA</td>
                    <td style="text-align: right;">S/ <?= number_format($costoFlete, 2) ?></td>
                    <td style="text-align: right; font-weight: bold;">S/ <?= number_format($costoFlete, 2) ?></td>
                </tr>
                <?php endif; ?>

                <?php for ($i = $rowCounter; $i < $maxRows; $i++): ?>
                <tr>
                    <td style="height: 22px;"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- TOTAL ROW AT TABLE FOOTER -->
        <div class="total-bar-row">
            <div class="total-box">
                <div class="total-lbl">TOTAL</div>
                <div class="total-val">S/ <?= number_format($contrato['monto_total'], 2) ?></div>
            </div>
        </div>

        <!-- INGRESO DE MERCADERÍA BANNER & TABLE -->
        <div class="section-banner-red">INGRESO DE MERCADERÍA</div>
        <table class="mercaderia-table">
            <thead>
                <tr>
                    <th style="width: 110px;">CANTIDAD</th>
                    <th>TIENDA 1</th>
                    <th>TIENDA 2</th>
                    <th>TIENDA 3</th>
                    <th>ALFARO</th>
                    <th>FABRICA</th>
                    <th>ALMACEN PRINC.</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight:bold;"><?= array_sum(array_column($items, 'cantidad')) ?> un.</td>
                    <td><?= str_contains($metodoEntregaActual, 'TIENDA 1') ? $chkRed : '' ?></td>
                    <td><?= str_contains($metodoEntregaActual, 'TIENDA 2') ? $chkRed : '' ?></td>
                    <td><?= str_contains($metodoEntregaActual, 'TIENDA 3') ? $chkRed : '' ?></td>
                    <td></td>
                    <td></td>
                    <td><?= (!str_contains($metodoEntregaActual, 'TIENDA')) ? $chkRed : '' ?></td>
                </tr>
            </tbody>
        </table>

        <!-- METHODS GRID (PAYMENT & DELIVERY WITH BLACK HEADERS) -->
        <div class="methods-grid">
            <!-- Método de Pago -->
            <div class="method-col">
                <div class="section-banner-black" style="font-size:9px; padding:3px 0;">MÉTODO DE PAGO</div>
                <table class="method-table">
                    <thead>
                        <tr>
                            <th>BBVA</th>
                            <th>BCP</th>
                            <th>PLIN</th>
                            <th>YAPE</th>
                            <th>MASTER CARD</th>
                            <th>VISA</th>
                            <th>EFECTIVO</th>
                            <th>OTRO BANCO</th>
                            <th>INTERBANK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= checkPayMethod('BBVA', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('BCP', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('PLIN', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('YAPE', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('MASTERCARD', $metodoPagoRaw, $chkRed) ?: checkPayMethod('MASTER CARD', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('VISA', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('EFECTIVO', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('OTROS BANCOS', $metodoPagoRaw, $chkRed) ?: checkPayMethod('OTRO BANCO', $metodoPagoRaw, $chkRed) ?></td>
                            <td><?= checkPayMethod('INTERBANK', $metodoPagoRaw, $chkRed) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Método de Entrega -->
            <div class="method-col">
                <div class="section-banner-black" style="font-size:9px; padding:3px 0;">MÉTODO DE ENTREGA</div>
                <table class="method-table">
                    <thead>
                        <tr>
                            <th>ARMADO</th>
                            <th>DESARMADO</th>
                            <th>CAJA</th>
                            <th>TIENDA</th>
                            <th>PROVINCIA</th>
                            <th>RUTA PROGRAMADA</th>
                            <th>DELIVERY</th>
                            <th>MARVISUR</th>
                            <th>SHALOM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><?= str_contains($metodoEntregaActual, 'TIENDA') ? $chkRed : '' ?></td>
                            <td><?= str_contains($metodoEntregaActual, 'PROVINCIA') ? $chkRed : '' ?></td>
                            <td><?= str_contains($metodoEntregaActual, 'RUTA') ? $chkRed : '' ?></td>
                            <td><?= str_contains($metodoEntregaActual, 'DELIVERY') ? $chkRed : '' ?></td>
                            <td><?= str_contains($metodoEntregaActual, 'MARVISUR') ? $chkRed : '' ?></td>
                            <td><?= str_contains($metodoEntregaActual, 'SHALOM') ? $chkRed : '' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- FOOTER BLOCK (CLAUSES + ADELANTO/SALDO/TOTAL + SIGNATURES + ERGOSEN STRIP) -->
    <div class="footer-block">
        
        <div style="display:flex; gap:12px; justify-content:space-between; align-items:center;">
            <!-- Legal Clauses -->
            <div class="clauses-text">
                <p><strong>PRIMERA:</strong> Si el comprador se desistiera de este Contrato, sea cual fuere el importe abonado, la casa vendedora se acoge a lo presupuestado por el artículo 1349 y siguiente del Código Civil, relativo al concepto de arras. El suscriptor en caso que desistiera de la compra por el importe de la separación podrá llevar un artículo del stock existente.</p>
                <p><strong>SEGUNDO:</strong> Si después de 30 días de Vencido el Contrato, el cliente no recoje su pedido, la empresa dará por anulado el contrato sin lugar a reclamo.</p>
                <p><strong>TERCERO:</strong> Este Pedido no incluye IGV, ni movilidad.</p>
            </div>

            <!-- Financial Summary Box (Fixed Width & No Wrapping S/) -->
            <table class="fin-table">
                <tr>
                    <td class="fin-lbl">A CUENTA</td>
                    <td class="fin-val" style="color:#2E7D32;">S/ <?= number_format($contrato['monto_adelanto'], 2) ?></td>
                </tr>
                <tr>
                    <td class="fin-lbl">SALDO</td>
                    <td class="fin-val" style="color:#e31e24;">S/ <?= number_format($contrato['monto_saldo'], 2) ?></td>
                </tr>
                <tr>
                    <td class="fin-lbl" style="background:#e31e24; font-size:11px;">TOTAL</td>
                    <td class="fin-val" style="font-size:13px; font-weight:900;">S/ <?= number_format($contrato['monto_total'], 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Signatures Area -->
        <div class="signatures-row" style="align-items: flex-end;">
            <div style="display:flex; flex-direction:column; align-items:center;">
                <?php if (!empty($contrato['firma_digital'])): ?>
                    <img src="<?= htmlspecialchars($contrato['firma_digital']) ?>" alt="Firma Cliente" style="max-width:180px; max-height:65px; margin-bottom:2px;">
                    <div style="font-size:8px; color:#059669; font-weight:bold; margin-bottom:3px;">✓ Firma Digital Registrada <?= !empty($contrato['fecha_firma']) ? '· ' . date('d/m/Y H:i', strtotime($contrato['fecha_firma'])) : '' ?></div>
                <?php endif; ?>
                <div class="sig-box">
                    CLIENTE -<br>
                    <span style="font-weight:normal; font-size:9.5px;">N° de Contacto: <?= htmlspecialchars($contrato['cliente_telefono'] ?: '_________________') ?></span>
                </div>
            </div>
            <div style="display:flex; flex-direction:column; align-items:center;">
                <div class="sig-box">
                    p. INDUSTRIAS CARPICENTER S.A.C.<br>
                    <span style="font-weight:normal; font-size:9.5px;">(Ejecutivo de Ventas - N° de Contacto)</span>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
