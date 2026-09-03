<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de nota de venta no proporcionado.");

// Obtener cabecera de la nota de venta
$stmt = $db->prepare("
    SELECT * 
    FROM notas_venta 
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$nota = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nota) die("Nota de venta no encontrada.");

// Obtener detalles de la nota
$stmtDet = $db->prepare("
    SELECT * 
    FROM notas_venta_detalle 
    WHERE nota_id = :id
    ORDER BY id ASC
");
$stmtDet->execute([':id' => $id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$is_active = $nota['estado'] === 'Activa';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Venta <?= htmlspecialchars($nota['numero']) ?> - Imprimir</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'Inter', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            display: flex;
            justify-content: center;
            color: #333;
        }
        
        /* Contenedor A4 - Diseño idéntico al visor */
        .page {
            width: 210mm;
            min-height: 297mm;
            background-color: white;
            position: relative;
            margin: 0 auto;
            box-sizing: border-box;
            padding: 15mm 12mm;
            display: flex;
            flex-direction: column;
            background-image: radial-gradient(rgba(0,0,0,0.02) 1px, transparent 0);
            background-size: 24px 24px;
        }

        /* Si está anulada, mostrar banner de fondo */
        .void-watermark {
            position: absolute;
            top: 40%;
            left: 20%;
            right: 20%;
            transform: rotate(-15deg);
            border: 8px solid rgba(227, 30, 36, 0.4);
            color: rgba(227, 30, 36, 0.4);
            font-size: 5rem;
            font-weight: 900;
            text-align: center;
            text-transform: uppercase;
            padding: 1rem;
            border-radius: 16px;
            pointer-events: none;
            z-index: 10;
            letter-spacing: 5px;
        }

        /* Encabezado */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .logo-area {
            flex: 1.8;
        }
        .logo-img-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .logo-img-wrapper img {
            max-height: 95px;
            object-fit: contain;
        }
        .company-contacts {
            font-size: 9.5px;
            color: #444;
            line-height: 1.4;
        }
        .company-contacts i {
            color: #e31e24;
            margin-right: 3px;
        }
        
        /* Caja de Comprobante Roja */
        .invoice-box {
            flex: 1;
            border: 3px solid #e31e24;
            border-radius: 8px;
            text-align: center;
            padding: 10px;
            background: #fffdfd;
            box-shadow: 0 4px 6px rgba(227, 30, 36, 0.05);
        }
        .invoice-box h2 {
            margin: 0;
            font-size: 15px;
            color: #e31e24;
            font-weight: 800;
            letter-spacing: 2px;
        }
        .invoice-box .comprobante-title {
            background-color: #e31e24;
            color: white;
            font-size: 14px;
            font-weight: bold;
            padding: 4px;
            margin: 8px 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .invoice-box .correlativo {
            margin: 0;
            font-size: 18px;
            color: #333;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .fecha-layout {
            display: flex;
            justify-content: flex-end;
            margin-top: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        .fecha-layout span {
            border-bottom: 1px dashed #333;
            padding: 0 8px;
        }

        /* Datos del Cliente */
        .section-title-bar {
            background-color: #2a2a2a;
            color: white;
            font-size: 10.5px;
            font-weight: bold;
            padding: 4px 10px;
            margin-top: 10px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 3px;
        }
        .client-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            font-size: 11px;
            line-height: 1.6;
        }
        .client-col p {
            margin: 3px 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
        }
        .client-col p strong {
            color: #555;
            display: inline-block;
            width: 110px;
        }

        /* Tabla de Items */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 12px;
        }
        .items-table th {
            background-color: #e31e24;
            color: white;
            text-align: left;
            padding: 6px 8px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
            border: 1px solid #e31e24;
        }
        .items-table td {
            padding: 6px 8px;
            border-left: 1px solid #e31e24;
            border-right: 1px solid #e31e24;
            border-bottom: 1px solid #f0f0f0;
            height: 24px;
        }
        .items-table tr.empty-row td {
            color: transparent;
            border-bottom: 1px solid #f9f9f9;
        }
        .items-table th.cant, .items-table td.cant {
            width: 8%;
            text-align: center;
        }
        .items-table th.price, .items-table td.price {
            width: 15%;
            text-align: right;
        }
        .items-table th.amount, .items-table td.amount {
            width: 15%;
            text-align: right;
        }
        .items-table td.amount {
            font-weight: 600;
        }
        .items-table tr.total-row-item td {
            border: 1px solid #e31e24;
            background: #fff;
            padding: 8px;
        }

        /* Estructura de Totales al Pie de la Tabla */
        .table-footer-grid {
            display: flex;
            justify-content: flex-end;
            margin-top: -1px;
        }
        .total-box-wrapper {
            width: 30%;
            border: 1px solid #e31e24;
            border-top: none;
            display: flex;
        }
        .total-box-label {
            background-color: #e31e24;
            color: white;
            font-weight: 700;
            font-size: 11px;
            padding: 6px;
            width: 50%;
            text-align: center;
        }
        .total-box-value {
            padding: 6px;
            font-size: 11px;
            font-weight: 700;
            text-align: right;
            width: 50%;
            background: #fff;
        }

        /* Ingreso de Mercadería */
        .mercaderia-title {
            background-color: #e31e24;
            color: white;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            margin-top: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #e31e24;
        }
        .mercaderia-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            text-align: center;
        }
        .mercaderia-table th, .mercaderia-table td {
            border: 1px solid #e31e24;
            padding: 4px;
        }
        .mercaderia-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }

        /* Metodo de Pago */
        .pago-title {
            background-color: #2a2a2a;
            color: white;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #2a2a2a;
        }
        .pago-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            text-align: center;
        }
        .pago-table th, .pago-table td {
            border: 1px solid #2a2a2a;
            padding: 5px;
        }
        .pago-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        .pago-table td.selected {
            background-color: rgba(46, 125, 50, 0.1);
            font-weight: bold;
            color: #2e7d32;
        }
        .pago-table .check-mark {
            font-size: 12px;
            font-weight: bold;
        }

        /* Observaciones y Firmas */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            margin-top: 15px;
            font-size: 9.5px;
        }
        .disclaimer-box {
            line-height: 1.4;
            color: #444;
        }
        .disclaimer-box p {
            margin: 4px 0;
        }
        .signature-box {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            border-top: 1px solid #666;
            margin-top: 35px;
            padding-top: 5px;
            text-align: center;
        }

        /* Impresión */
        @media print {
            body {
                background-color: white;
            }
            .page {
                box-shadow: none;
                margin: 0;
                width: 100%;
                border: none;
                padding: 10mm 10mm;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Hoja A4 del comprobante (Estructura idéntica al visor original) -->
    <div class="page">
        
        <!-- Marca de agua si está anulada -->
        <?php if (!$is_active): ?>
            <div class="void-watermark">Anulado</div>
        <?php endif; ?>

        <!-- Encabezado -->
        <div class="header-section">
            <div class="logo-area">
                <div class="logo-img-wrapper">
                    <img src="/carpicenter_sys/assets/img/logo_carpicenter_official.png" alt="Carpicenter Logo" style="max-height: 95px; width: auto;">
                </div>
                <div class="company-contacts">
                    <p><i class="fab fa-facebook-square"></i> Carpicenterperu &nbsp;|&nbsp; <i class="fas fa-globe"></i> www.carpicenter.com.pe</p>
                    <p><i class="fas fa-map-marker-alt"></i> Calle Unión Mz. J Sub Lote 6B, Parque Industrial II - V.E.S. (Costado de Senati)</p>
                    <p><i class="fas fa-phone-alt"></i> (01) 715-8445 &nbsp;|&nbsp; <i class="fas fa-mobile-alt"></i> 961 849 138 - 925 188 921 &nbsp;|&nbsp; <i class="far fa-envelope"></i> ventas@carpicenter.com.pe</p>
                </div>
            </div>
            <div class="invoice-box">
                <h2>RUC N° 20555889616</h2>
                <div class="comprobante-title">Nota de Venta</div>
                <div class="correlativo">Nº <?= htmlspecialchars($nota['numero']) ?></div>
            </div>
        </div>

        <!-- Fecha alineada a la derecha -->
        <div class="fecha-layout">
            Fecha: &nbsp;<span><?= date('d', strtotime($nota['fecha'])) ?></span>&nbsp; de &nbsp;<span><?= date('m', strtotime($nota['fecha'])) ?></span>&nbsp; del &nbsp;<span><?= date('Y', strtotime($nota['fecha'])) ?></span>
        </div>

        <!-- Datos del Cliente -->
        <div class="section-title-bar">Datos del Cliente</div>
        <div class="client-grid">
            <div class="client-col">
                <p><strong>Apellidos y Nombres / Razón Social:</strong> <?= htmlspecialchars($nota['cliente_nombre']) ?></p>
                <p><strong>DNI / RUC:</strong> <?= htmlspecialchars($nota['cliente_documento'] ?: '—') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($nota['cliente_direccion'] ?: '—') ?></p>
            </div>
            <div class="client-col" style="border-left: 1px solid #f0f0f0; padding-left: 15px;">
                <p><strong>N° Contacto Cliente:</strong> <?= htmlspecialchars($nota['cliente_telefono'] ?: '—') ?></p>
                <p><strong>Ejecutivo en Ventas:</strong> <?= htmlspecialchars($nota['vendedor']) ?></p>
                <p><strong>Tipo de Cliente:</strong> <?= !empty($nota['cliente_documento']) && strlen($nota['cliente_documento']) == 11 ? 'Distribuidor [X] &nbsp; Cliente Final [ ]' : 'Distribuidor [ ] &nbsp; Cliente Final [X]' ?></p>
            </div>
        </div>

        <!-- Tabla de Detalles -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="cant">Cant</th>
                    <th>Descripción</th>
                    <th class="price">P. Unit.</th>
                    <th class="amount">Importe</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $num_items = count($detalles);
                $min_rows = 8; // Mínimo de filas para parecer talonario
                
                foreach ($detalles as $det): 
                ?>
                <tr>
                    <td class="cant"><?= number_format($det['cantidad'], 0) ?></td>
                    <td><strong><?= htmlspecialchars($det['descripcion']) ?></strong></td>
                    <td class="price"><?= number_format($det['precio_unitario'], 2) ?></td>
                    <td class="amount"><?= number_format($det['importe'], 2) ?></td>
                </tr>
                <?php 
                endforeach; 
                if ($num_items < $min_rows) {
                    for ($i = $num_items; $i < $min_rows; $i++) {
                        echo '<tr class="empty-row">
                            <td class="cant">&nbsp;</td>
                            <td>&nbsp;</td>
                            <td class="price">&nbsp;</td>
                            <td class="amount">&nbsp;</td>
                        </tr>';
                    }
                }
                ?>
            </tbody>
        </table>

        <!-- Totales -->
        <div class="table-footer-grid">
            <div class="total-box-wrapper">
                <div class="total-box-label">TOTAL S/</div>
                <div class="total-box-value"><?= number_format($nota['total'], 2) ?></div>
            </div>
        </div>

        <!-- Ingreso de Mercadería -->
        <div class="mercaderia-title">Ingreso de Mercadería</div>
        <table class="mercaderia-table">
            <thead>
                <tr>
                    <th>CANTIDAD</th>
                    <th>ESTABLECIMIENTO</th>
                    <th>TIENDA 1</th>
                    <th>TIENDA 2</th>
                    <th>TIENDA 3</th>
                    <th>ALFARO</th>
                    <th>FÁBRICA</th>
                    <th>ALMACÉN</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>

        <!-- Método de Pago -->
        <div class="pago-title">Método de Pago</div>
        <table class="pago-table">
            <thead>
                <tr>
                    <th>BBVA</th>
                    <th>BCP</th>
                    <th>PLIN</th>
                    <th>YAPE</th>
                    <th>INTERBANK</th>
                    <th>VISA</th>
                    <th>EFECTIVO</th>
                    <th>OTROBANCO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                    $metodos = ['BBVA', 'BCP', 'Plin', 'Yape', 'Interbank', 'Visa', 'Efectivo', 'Otrobanco'];
                    $actual = strtolower($nota['metodo_pago'] ?? '');
                    foreach ($metodos as $m) {
                        $match = false;
                        if (strtolower($m) === $actual) {
                            $match = true;
                        } elseif (strtolower($m) === 'interbank' && $actual === 'transferencia') {
                            $match = true;
                        } elseif (strtolower($m) === 'otrobanco' && $actual === 'otros') {
                            $match = true;
                        }

                        if ($match) {
                            echo '<td class="selected"><span class="check-mark">✓</span></td>';
                        } else {
                            echo '<td>&nbsp;</td>';
                        }
                    }
                    ?>
                </tr>
            </tbody>
        </table>

        <!-- Observaciones y Firmas -->
        <div class="bottom-grid">
            <div class="disclaimer-box">
                <p><strong>OBSERVACIONES:</strong></p>
                <div style="border: 1px solid #ccc; padding: 6px; min-height: 45px; border-radius: 4px; font-size: 10px; margin-bottom: 8px; background-color: #fafafa;">
                    <?= !empty($nota['observaciones']) ? nl2br(htmlspecialchars($nota['observaciones'])) : '<em>Sin observaciones registradas.</em>' ?>
                </div>
                <p style="font-size: 8px; color: #555; font-style: italic;">
                    El cliente declara haber leído y aceptado las Políticas de Cambio descritas al dorso del comprobante manual original. Sin firma este comprobante no cuenta con GARANTÍA.
                </p>
                <p style="margin-top: 10px;"><strong>DESPACHADO POR:</strong> _____________________________________</p>
            </div>
            <div class="signature-box-wrapper" style="display: flex; flex-direction: column; justify-content: flex-end;">
                <div class="signature-box">
                    RECIBÍ CONFORME (CLIENTE)
                </div>
            </div>
        </div>

    </div>

    <script>
        window.addEventListener('load', () => {
            window.print();
        });
    </script>
</body>
</html>
