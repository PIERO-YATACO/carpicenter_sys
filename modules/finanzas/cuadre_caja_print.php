<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    die("ID de cuadre de caja no especificado.");
}

// Obtener cabecera
$stmt = $db->prepare("SELECT * FROM finanzas_cuadre_caja WHERE id = ?");
$stmt->execute([$id]);
$cuadre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cuadre) {
    die("Registro de cuadre de caja no encontrado.");
}

// Obtener detalles ordenados
$stmtDet = $db->prepare("SELECT * FROM finanzas_cuadre_detalle WHERE cuadre_id = ? ORDER BY id ASC");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$entradas = array_filter($detalles, fn($d) => $d['tipo'] === 'ENTRADA');
$salidas = array_filter($detalles, fn($d) => $d['tipo'] === 'SALIDA');

// Fechas formateadas
$f_inicio_str = $cuadre['fecha_inicio'] ? date('d/m/Y', strtotime($cuadre['fecha_inicio'])) : '—';
$f_fin_str = $cuadre['fecha_fin'] ? date('d/m/Y', strtotime($cuadre['fecha_fin'])) : '';
$fecha_emision = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero de Caja - <?= htmlspecialchars($cuadre['codigo'] ?? ('CC-'.$cuadre['id'])) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        * { 
            box-sizing: border-box; 
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #0F172A;
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.45;
            display: flex;
            justify-content: center;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            background-color: #ffffff;
            position: relative;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            margin: 6mm auto;
            padding: 14mm 16mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        @media print {
            body { background-color: #fff; }
            .page {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .no-print { display: none !important; }
        }

        .action-bar {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        .btn-act {
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: inherit;
        }
        .btn-print { background: #C62828; color: #fff; }
        .btn-print:hover { background: #9E1B1B; }
        .btn-back { background: #1E293B; color: #fff; }

        /* HEADER DEL INFORME */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 16px;
        }
        .brand-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand-section img {
            height: 64px;
            width: auto;
            object-fit: contain;
        }
        .brand-titles h1 {
            font-size: 1.25rem;
            font-weight: 900;
            color: #0F172A;
            margin: 0;
            letter-spacing: -0.3px;
        }
        .brand-titles p {
            font-size: 0.78rem;
            color: #64748B;
            font-weight: 600;
            margin: 2px 0 0 0;
        }

        .report-badge-box {
            text-align: right;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 8px 14px;
        }
        .report-badge-box .tag {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #C62828;
            margin-bottom: 2px;
        }
        .report-badge-box .code {
            font-size: 1.15rem;
            font-weight: 900;
            color: #0F172A;
            line-height: 1.2;
        }
        .report-badge-box .period {
            font-size: 0.72rem;
            color: #64748B;
            margin-top: 2px;
        }

        /* METADATA BAR */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .meta-label {
            font-size: 0.66rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748B;
        }
        .meta-val {
            font-size: 0.85rem;
            font-weight: 800;
            color: #0F172A;
        }

        /* RESUMEN FINANCIERO PERFECTAMENTE ALINEADO */
        .financial-summary-strip {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            background: #FFFFFF;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 22px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
        }
        .summary-metric {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 4px;
        }
        .summary-metric:not(:last-child) {
            border-right: 1.5px solid #E2E8F0;
        }
        .summary-metric .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 800;
            color: #64748B;
            white-space: nowrap;
            line-height: 1;
        }
        .summary-metric .value {
            font-size: 1.55rem;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }
        .summary-metric.green .value { color: #059669; }
        .summary-metric.red .value { color: #DC2626; }
        .summary-metric.highlight .value { color: #0F172A; }
        .summary-metric.highlight .label { color: #C62828; }

        /* SECCIONES Y TABLAS */
        .section-block {
            margin-bottom: 18px;
        }
        .section-title {
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0F172A;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #F1F5F9;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748B;
            padding: 8px 10px;
            border-bottom: 1.5px solid #E2E8F0;
            text-align: left;
            background: #F8FAFC;
        }
        .data-table td {
            font-size: 0.82rem;
            padding: 8px 10px;
            border-bottom: 1px solid #F1F5F9;
            color: #1E293B;
            vertical-align: middle;
        }
        .data-table tbody tr:hover td {
            background-color: #F8FAFC;
        }
        .data-table tfoot td {
            font-size: 0.86rem;
            font-weight: 800;
            padding: 10px;
            border-top: 1.5px solid #CBD5E1;
            border-bottom: none;
            background: #FFFFFF;
        }

        .store-name {
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
        }
        
        .pill-ticket {
            font-size: 0.72rem;
            color: #475569;
            font-weight: 600;
            background: #F1F5F9;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .category-badge {
            font-size: 0.68rem;
            font-weight: 800;
            color: #475569;
            background: #F1F5F9;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* OBSERVACIONES */
        .notes-card {
            background: #F8FAFC;
            border-radius: 8px;
            padding: 8px 12px;
            border-left: 3px solid #CBD5E1;
            font-size: 0.75rem;
            color: #475569;
            margin-top: 10px;
        }

        /* FOOTER */
        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid #E2E8F0;
            font-size: 0.72rem;
            color: #94A3B8;
        }
    </style>
</head>
<body>

    <div class="action-bar no-print">
        <a href="cuadre_caja.php" class="btn-act btn-back">Volver a Cuadres</a>
        <button onclick="window.print()" class="btn-act btn-print">Imprimir Reporte</button>
    </div>

    <div class="page">

        <div>
            <!-- 1. ENCABEZADO CON LOGO GRANDE -->
            <div class="report-header">
                <div class="brand-section">
                    <img src="/carpicenter_sys/assets/img/logo_carpicenter_official.png" alt="INDUSTRIAS CARPICENTER" onerror="this.onerror=null; this.src='/carpicenter_sys/assets/img/logo.png';">
                    <div class="brand-titles">
                        <h1>INDUSTRIAS CARPICENTER S.A.C.</h1>
                        <p>Informe Gerencial de Cuadre y Liquidación de Caja</p>
                    </div>
                </div>
                <div class="report-badge-box">
                    <div class="tag">Liquidación Financiera</div>
                    <div class="code"><?= htmlspecialchars($cuadre['codigo'] ?? ('CC-'.$cuadre['id'])) ?></div>
                    <div class="period">Período: <?= $f_inicio_str ?> <?= (!empty($cuadre['fecha_fin']) && $cuadre['fecha_fin'] !== $cuadre['fecha_inicio']) ? 'al '.$f_fin_str : '' ?></div>
                </div>
            </div>

            <!-- 2. DATOS DE CONTROL -->
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Área</span>
                    <span class="meta-val"><?= htmlspecialchars($cuadre['area'] ?? 'ADMINISTRATIVO') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Responsable</span>
                    <span class="meta-val"><?= htmlspecialchars($cuadre['encargado'] ?? 'NAOMI') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Alcance</span>
                    <span class="meta-val"><?= htmlspecialchars($cuadre['tienda'] ?? 'TODAS LAS TIENDAS') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Fecha de Cierre</span>
                    <span class="meta-val"><?= $f_inicio_str ?></span>
                </div>
            </div>

            <!-- 3. RESUMEN FINANCIERO PERFECTAMENTE ALINEADO -->
            <div class="financial-summary-strip">
                <div class="summary-metric green">
                    <span class="label">Total Recaudación</span>
                    <span class="value">S/ <?= number_format($cuadre['total_ingreso'], 2, '.', ',') ?></span>
                </div>
                <div class="summary-metric red">
                    <span class="label">Total Egresos</span>
                    <span class="value">S/ <?= number_format($cuadre['total_egreso'], 2, '.', ',') ?></span>
                </div>
                <div class="summary-metric highlight">
                    <span class="label">Saldo Neto en Caja</span>
                    <span class="value">S/ <?= number_format($cuadre['saldo_final'], 2, '.', ',') ?></span>
                </div>
            </div>

            <!-- 4. SECCIÓN 1: RECAUDACIÓN POR TIENDAS (ENTRADAS) -->
            <div class="section-block">
                <div class="section-title">
                    1. Recaudación y Cobros en Tiendas
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Fecha</th>
                            <th>Origen / Tienda</th>
                            <th style="width: 220px;">N° Tickets / Justificante</th>
                            <th style="width: 150px;" class="text-right">Monto Recaudado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasSaldoAnterior = false;
                        foreach($entradas as $ent): 
                            $f_item = $ent['fecha'] ? date('d/m/Y', strtotime($ent['fecha'])) : '';
                            $descUpper = strtoupper($ent['descripcion'] ?? $ent['detalle'] ?? '');
                            $isSaldoAnt = (strpos($descUpper, 'SALDO ANTERIOR') !== false || $ent['categoria'] === 'SALDO_ANTERIOR');
                            if ($isSaldoAnt) $hasSaldoAnterior = true;
                            $montoEnt = floatval($ent['monto']);
                        ?>
                            <tr>
                                <td style="color:#64748B;"><?= $isSaldoAnt ? '—' : $f_item ?></td>
                                <td class="store-name">
                                    <?= htmlspecialchars($ent['descripcion'] ?? $ent['detalle']) ?>
                                </td>
                                <td>
                                    <?php if(!empty($ent['nro_justificante']) && $ent['nro_justificante'] !== '(N/A)'): ?>
                                        <span class="pill-ticket"><?= htmlspecialchars($ent['nro_justificante']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right" style="font-weight:800; color:#166534;">
                                    S/ <?= number_format($montoEnt, 2, '.', ',') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if(!$hasSaldoAnterior && floatval($cuadre['saldo_anterior']) > 0): ?>
                            <tr>
                                <td style="color:#64748B;">—</td>
                                <td class="store-name">SALDO ANTERIOR</td>
                                <td style="color:#94A3B8;">—</td>
                                <td class="text-right" style="font-weight:800; color:#166534;">
                                    S/ <?= number_format(floatval($cuadre['saldo_anterior']), 2, '.', ',') ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right" style="color:#166534;">TOTAL ENTRADAS:</td>
                            <td class="text-right" style="color:#166534;">
                                S/ <?= number_format($cuadre['total_ingreso'], 2, '.', ',') ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 5. SECCIÓN 2: EGRESOS Y SALIDAS DEDUCIDAS -->
            <div class="section-block">
                <div class="section-title">
                    2. Detalle de Egresos y Salidas Operativas
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Fecha</th>
                            <th style="width: 110px;">Categoría</th>
                            <th>Descripción del Gasto / Salida</th>
                            <th style="width: 150px;">N° Justificante / OP</th>
                            <th style="width: 150px;" class="text-right">Monto Deducido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($salidas)): ?>
                            <tr>
                                <td colspan="5" class="text-center" style="color:#94A3B8; padding:12px;">
                                    Sin movimientos de egreso registrados en este período.
                                </td>
                            </tr>
                        <?php else: foreach($salidas as $sal): 
                            $f_sal = $sal['fecha'] ? date('d/m/Y', strtotime($sal['fecha'])) : '';
                            $cat = strtoupper($sal['categoria'] ?? 'OTROS');
                            $monto = floatval($sal['monto']);
                        ?>
                            <tr>
                                <td style="color:#64748B;"><?= $f_sal ?></td>
                                <td>
                                    <span class="category-badge"><?= htmlspecialchars($cat) ?></span>
                                </td>
                                <td style="font-weight:600; text-transform:uppercase; color:#0F172A;">
                                    <?= htmlspecialchars($sal['descripcion'] ?? $sal['detalle']) ?>
                                </td>
                                <td>
                                    <?php if(!empty($sal['nro_justificante'])): ?>
                                        <span class="pill-ticket"><?= htmlspecialchars($sal['nro_justificante']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right" style="font-weight:800; color:#991B1B;">
                                    S/ <?= number_format($monto, 2, '.', ',') ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right" style="color:#991B1B;">TOTAL EGRESOS:</td>
                            <td class="text-right" style="color:#991B1B;">
                                S/ <?= number_format($cuadre['total_egreso'], 2, '.', ',') ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- OBSERVACIONES -->
            <?php if(!empty($cuadre['observacion'])): ?>
                <div class="notes-card">
                    <strong>Observaciones:</strong> <?= htmlspecialchars($cuadre['observacion']) ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- FOOTER INFORMATIVO -->
        <div class="report-footer">
            <div>INDUSTRIAS CARPICENTER S.A.C. &bull; RUC: 20608569421 &bull; Sistema de Gestión Financiera</div>
            <div>Emitido por: <strong><?= htmlspecialchars($cuadre['encargado'] ?? 'NAOMI') ?></strong> el <?= $fecha_emision ?></div>
        </div>

    </div>

</body>
</html>
