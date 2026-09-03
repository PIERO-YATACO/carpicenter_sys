<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de Orden de Egreso no proporcionado.");

// Obtener cabecera de la orden
$stmt = $db->prepare("
    SELECT oe.*, l.direccion as local_direccion
    FROM ordenes_egreso oe
    LEFT JOIN locales l ON oe.local_origen_id = l.id
    WHERE oe.id = :id
");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) die("Orden de egreso no encontrada.");

// Obtener detalles de la orden
$stmtDet = $db->prepare("
    SELECT * 
    FROM orden_egreso_detalles 
    WHERE orden_egreso_id = :id
    ORDER BY id ASC
");
$stmtDet->execute([':id' => $id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Egreso N° <?= htmlspecialchars($orden['numero']) ?> - Carpicenter</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-size: 11px;
            display: flex;
            justify-content: center;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            background-color: #ffffff;
            position: relative;
            box-shadow: 0 0 12px rgba(0,0,0,0.3);
            margin: 8mm auto;
            padding: 10mm 12mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* HEADER BLOCK */
        .top-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .top-left {
            vertical-align: top;
        }
        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
        }
        .brand-header img {
            width: 65px;
            height: auto;
        }
        .company-name {
            font-size: 14px;
            font-weight: 900;
            color: #000;
            margin-bottom: 2px;
        }
        .company-info {
            font-size: 9.5px;
            color: #333;
            line-height: 1.35;
        }

        /* RIGHT RUC BOX (Light Blue/Grey) */
        .ruc-box {
            width: 240px;
            border: 1px solid #d0d7de;
            border-radius: 4px;
            text-align: center;
            padding: 12px;
            background: #f6f8fa;
        }
        .ruc-title {
            font-size: 13px;
            font-weight: 900;
            color: #000;
            letter-spacing: 0.5px;
        }
        .ruc-num {
            font-size: 15px;
            font-weight: 900;
            color: #000;
            margin: 4px 0;
        }
        .ruc-val {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }

        /* DETAILS BOX */
        .details-box {
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 15px;
            background: #ffffff;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
            font-size: 10.5px;
        }
        .details-item strong {
            color: #000;
        }

        /* SECTION TITLE */
        .section-title {
            font-size: 11px;
            font-weight: 900;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        /* ITEMS TABLE */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            border-top: 1px solid #d0d7de;
            border-bottom: 1px solid #d0d7de;
            padding: 8px;
            font-size: 10px;
            font-weight: 900;
            text-align: left;
            background: #f6f8fa;
            color: #000;
        }
        .items-table th.num { width: 45px; text-align: center; }
        .items-table th.unit { width: 120px; text-align: center; }
        .items-table th.qty { width: 100px; text-align: right; }

        .items-table td {
            border-bottom: 1px solid #f0f0f0;
            padding: 8px;
            font-size: 10.5px;
        }
        .items-table td.num { text-align: center; font-weight: bold; color: #555; }
        .items-table td.unit { text-align: center; color: #333; }
        .items-table td.qty { text-align: right; font-weight: bold; }

        /* RECEPTION & SIGNATURES BLOCK */
        .signatures-section {
            margin-top: 40px;
            border-top: 1px dashed #ccc;
            padding-top: 25px;
        }
        .sig-grid {
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }
        .sig-box {
            flex: 1;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 12px;
            background: #fafafa;
        }
        .sig-title {
            font-size: 10.5px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            color: #000;
        }
        .sig-line {
            border-top: 1px dotted #000;
            margin-top: 45px;
            padding-top: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }

        /* PRINT BUTTON */
        .no-print-bar {
            position: fixed;
            top: 15px;
            right: 20px;
            z-index: 9999;
        }
        .btn-print-main {
            background: #1565C0;
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
            .page {
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
    <button class="btn-print-main" onclick="window.print()">🖨️ Imprimir Orden de Egreso</button>
</div>

<div class="page">

    <div>
        <!-- TOP HEADER -->
        <table class="top-table">
            <tr>
                <td class="top-left">
                    <div class="brand-header">
                        <img src="/carpicenter_sys/assets/img/logo_bird_clean.png" alt="Carpicenter Logo">
                        <div>
                            <div class="company-name">INDUSTRIAS CARPICENTER SAC</div>
                            <div class="company-info">
                                <?= htmlspecialchars($orden['local_direccion'] ?? 'AVENIDA SOLIDARIDAD MZ J LT 21 PARQUE INDUSTRIAL J 21 Lima Lima Villa El Salvador') ?><br>
                                Correo electrónico: almacenprincipal@carpicenter.com.pe<br>
                                <strong>Fecha de emisión:</strong> <?= htmlspecialchars($orden['fecha_emision']) ?><br>
                                <strong>Hora de emisión:</strong> <?= htmlspecialchars($orden['hora_emision']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td style="width: 250px; text-align: right; vertical-align: top;">
                    <div class="ruc-box">
                        <div class="ruc-title">ORDEN DE EGRESO</div>
                        <div class="ruc-num"><?= htmlspecialchars($orden['numero']) ?></div>
                        <div class="ruc-val">RUC 20555889616</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- DETAILS GRID -->
        <div class="details-box">
            <div class="details-grid">
                <div class="details-item">
                    <strong>Local origen:</strong> <?= htmlspecialchars(strtoupper($orden['local_origen_nombre'] ?? 'ALMACEN PRINCIPAL')) ?>
                </div>
                <div class="details-item">
                    <strong>Fecha aprox. de llegada:</strong> <?= htmlspecialchars($orden['fecha_aprox_llegada'] ?: $orden['fecha_emision']) ?>
                </div>
                <div class="details-item">
                    <strong>Almacén origen:</strong> <?= htmlspecialchars($orden['local_origen_nombre'] ?? 'Almacén Principal') ?>
                </div>
                <div class="details-item">
                    <strong>Local destino:</strong> <?= htmlspecialchars(strtoupper($orden['local_destino_nombre'] ?: 'TIENDA')) ?>
                </div>
                <div class="details-item" style="grid-column: span 2;">
                    <strong>Motivo de egreso:</strong> <?= htmlspecialchars(strtoupper($orden['motivo_egreso'] ?? 'TRANSFERENCIA ENTRE ALMACENES')) ?>
                </div>
            </div>
        </div>

        <!-- PRODUCTOS TABLE -->
        <div class="section-title">PRODUCTOS DE LA ORDEN</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th class="num">N°</th>
                    <th>Descripción</th>
                    <th class="unit">Unidad medida</th>
                    <th class="qty">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ($detalles as $det): 
                ?>
                <tr>
                    <td class="num"><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($det['descripcion']) ?></strong></td>
                    <td class="unit"><?= htmlspecialchars($det['unidad_medida'] ?: 'un') ?></td>
                    <td class="qty"><?= number_format($det['cantidad'], 1) ?> un</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- SIGNATURES BLOCK WITH RECEPCIONADO POR NOMBRES DNI Y FIRMA -->
    <div class="signatures-section">
        <div class="sig-grid">
            <div class="sig-box">
                <div class="sig-title">ENTREGADO POR (ALMACÉN / TIENDA)</div>
                <p style="margin:4px 0;"><strong>Despachador:</strong> Industrias Carpicenter</p>
                <div class="sig-line">
                    FIRMA AUTORIZADA ENTREGADO
                </div>
            </div>

            <div class="sig-box">
                <div class="sig-title">RECEPCIONADO POR (RECEPTOR FINAL)</div>
                <p style="margin:3px 0;"><strong>Nombres y Apellidos:</strong> <?= htmlspecialchars($orden['recepcionado_nombre'] ?: '—') ?></p>
                <p style="margin:3px 0;"><strong>DNI / Doc.:</strong> <?= htmlspecialchars($orden['recepcionado_dni'] ?: '—') ?></p>
                <div class="sig-line">
                    FIRMA DE CONFORMIDAD RECEPCIONADO
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
