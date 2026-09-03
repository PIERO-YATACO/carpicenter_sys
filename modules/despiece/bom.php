<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$producto_id = isset($_GET['id'])  ? (int)$_GET['id']       : 0;
$cantidad    = isset($_GET['qty']) ? max(1,(int)$_GET['qty']) : 1;

if (!$producto_id) { header("Location: modelos.php"); exit; }

// Datos del modelo
$sm = $db->prepare("SELECT * FROM productos_maestros WHERE id = ?");
$sm->execute([$producto_id]);
$modelo = $sm->fetch(PDO::FETCH_ASSOC);
if (!$modelo) { header("Location: modelos.php"); exit; }

// BOM de piezas (función PostgreSQL)
$sp = $db->prepare("SELECT * FROM fn_bom_piezas(:pid, :qty)");
$sp->execute([':pid' => $producto_id, ':qty' => $cantidad]);
$bom_piezas = $sp->fetchAll(PDO::FETCH_ASSOC);

// BOM de insumos
$si = $db->prepare("SELECT * FROM fn_bom_insumos(:pid, :qty)");
$si->execute([':pid' => $producto_id, ':qty' => $cantidad]);
$bom_insumos = $si->fetchAll(PDO::FETCH_ASSOC);

// Resumen de compras
$sr = $db->prepare("SELECT * FROM fn_bom_resumen_compras(:pid, :qty)");
$sr->execute([':pid' => $producto_id, ':qty' => $cantidad]);
$resumen = $sr->fetchAll(PDO::FETCH_ASSOC);

// Totales generales
$total_planchas  = array_sum(array_column($resumen, 'planchas_totales'));
$total_tapacanto = array_sum(array_column($resumen, 'ml_tapacanto_total'));
$total_piezas    = array_sum(array_column($bom_piezas, 'cant_total'));

$page_title    = 'BOM — ' . $modelo['nombre_modelo'];
$page_subtitle = 'Explosión de Materiales para ' . $cantidad . ' unidad(es)';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BOM - <?= htmlspecialchars($modelo['nombre_modelo']) ?> - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Exportación PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <!-- Exportación Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .bom-table { width:100%; border-collapse:collapse; font-size:0.78rem; }
        .bom-table th { background:var(--bg-primary); padding:0.6rem 0.7rem; text-align:left; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:600; white-space:nowrap; }
        .bom-table td { padding:0.55rem 0.7rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
        .bom-table tbody tr:hover { background:rgba(198,40,40,0.04); }
        .bom-table .center { text-align:center; }
        .bom-table .right  { text-align:right; }
        .pill { display:inline-block; padding:0.15rem 0.5rem; border-radius:20px; font-size:0.68rem; font-weight:600; }
        .pill-red   { background:rgba(198,40,40,0.15); color:var(--primary-light); }
        .pill-green { background:rgba(46,125,50,0.15);  color:#66BB6A; }
        .pill-blue  { background:rgba(21,101,192,0.15); color:#42A5F5; }
        .pill-yes   { background:rgba(249,168,37,0.15); color:#F9A825; }
        .qty-form   { display:flex; align-items:center; gap:0.5rem; }
        .qty-form input[type=number] { width:80px; padding:0.45rem 0.7rem; background:var(--bg-card); border:1px solid var(--border-color); border-radius:8px; color:var(--text-primary); font-size:0.9rem; text-align:center; }
        .kpi-grid   { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .kpi-box    { background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; padding:1.2rem 1.4rem; display:flex; align-items:center; gap:1rem; }
        .kpi-icon   { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .kpi-val    { font-size:1.6rem; font-weight:700; line-height:1; }
        .kpi-lbl    { font-size:0.72rem; color:var(--text-muted); margin-top:0.2rem; }
        .print-only { display:none; }
        /* Botones de exportación */
        .export-group { display:flex; gap:0.4rem; align-items:center; }
        .btn-export {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.45rem 0.9rem; border:none; border-radius:8px;
            font-size:0.8rem; font-weight:600; cursor:pointer;
            transition:all 0.2s; letter-spacing:0.3px;
        }
        .btn-pdf   { background:#DC2626; color:#fff; }
        .btn-pdf:hover   { background:#B91C1C; transform:translateY(-1px); box-shadow:0 4px 12px rgba(220,38,38,0.35); }
        .btn-excel { background:#16A34A; color:#fff; }
        .btn-excel:hover { background:#15803D; transform:translateY(-1px); box-shadow:0 4px 12px rgba(22,163,74,0.35); }
        .btn-print { background:var(--bg-card); color:var(--text-primary); border:1px solid var(--border-color); }
        .btn-print:hover { border-color:var(--text-primary); transform:translateY(-1px); }
        @media print {
            .sidebar, .topbar, .no-print { display:none !important; }
            .main-content { margin-left:0 !important; }
            .print-only { display:block; }
            .bom-table th, .bom-table td { font-size:0.65rem; padding:0.3rem 0.5rem; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Cabecera y selector de cantidad -->
            <div class="page-header no-print" style="flex-wrap:wrap;gap:1rem;">
                <div>
                    <h2><i class="fas fa-calculator" style="color:var(--primary);margin-right:0.5rem;"></i>
                        <?= htmlspecialchars($modelo['nombre_modelo']) ?>
                    </h2>
                    <p>Explosión de Materiales (BOM) · Código: <strong><?= htmlspecialchars($modelo['codigo']) ?></strong></p>
                </div>
                <div style="display:flex;gap:0.8rem;align-items:center;flex-wrap:wrap;">
                    <!-- Selector de cantidad -->
                    <form method="GET" class="qty-form">
                        <input type="hidden" name="id" value="<?= $producto_id ?>">
                        <label style="font-size:0.82rem;color:var(--text-muted);white-space:nowrap;">Cantidad a fabricar:</label>
                        <input type="number" name="qty" min="1" max="999" value="<?= $cantidad ?>">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Calcular</button>
                    </form>
                    <a href="modelo_form.php?id=<?= $producto_id ?>" class="btn btn-outline btn-sm no-print">
                        <i class="fas fa-edit"></i> Editar Modelo
                    </a>
                    <!-- Grupo exportar -->
                    <div class="export-group no-print">
                        <button onclick="exportarPDF()" class="btn btn-export btn-pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button onclick="exportarExcel()" class="btn btn-export btn-excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button onclick="window.print()" class="btn btn-export btn-print">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                    <a href="modelos.php" class="btn btn-outline btn-sm no-print">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- ── KPIs Resumen ── -->
            <div class="kpi-grid">
                <div class="kpi-box">
                    <div class="kpi-icon" style="background:rgba(198,40,40,0.15);color:var(--primary-light);"><i class="fas fa-boxes"></i></div>
                    <div>
                        <div class="kpi-val"><?= $cantidad ?></div>
                        <div class="kpi-lbl">Muebles a Fabricar</div>
                    </div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-icon" style="background:rgba(21,101,192,0.15);color:#42A5F5;"><i class="fas fa-puzzle-piece"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($total_piezas, 0) ?></div>
                        <div class="kpi-lbl">Piezas Totales a Cortar</div>
                    </div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-icon" style="background:rgba(46,125,50,0.15);color:#66BB6A;"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="kpi-val"><?= $total_planchas ?></div>
                        <div class="kpi-lbl">Planchas de Tablero</div>
                    </div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-icon" style="background:rgba(249,168,37,0.15);color:#F9A825;"><i class="fas fa-ruler-horizontal"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($total_tapacanto, 1) ?> ml</div>
                        <div class="kpi-lbl">Tapacanto (+10% desp.)</div>
                    </div>
                </div>
            </div>

            <!-- ── Resumen por Material (para Compras) ── -->
            <?php if (!empty($resumen)): ?>
            <div class="card-panel" style="margin-bottom:1.5rem;">
                <div class="card-header">
                    <h3><i class="fas fa-shopping-cart" style="color:#F9A825;margin-right:0.5rem;"></i>Resumen para Compras</h3>
                </div>
                <div class="card-body-custom" style="padding:0;">
                    <table class="bom-table">
                        <thead><tr>
                            <th>Material</th>
                            <th class="right">Área Total (m²)</th>
                            <th class="center">Planchas Necesarias</th>
                            <th class="right">Tapacanto con desperdicio (ml)</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($resumen as $r): ?>
                            <tr>
                                <td><i class="fas fa-square" style="color:var(--primary);margin-right:0.5rem;font-size:0.7rem;"></i><?= htmlspecialchars($r['material']) ?></td>
                                <td class="right"><strong><?= number_format($r['area_total_m2'],3) ?></strong></td>
                                <td class="center">
                                    <span class="pill pill-red"><?= $r['planchas_totales'] ?> planchas</span>
                                </td>
                                <td class="right"><strong><?= number_format($r['ml_tapacanto_total'],2) ?></strong> ml</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── BOM Detalle de Piezas ── -->
            <div class="card-panel" style="margin-bottom:1.5rem;">
                <div class="card-header">
                    <h3><i class="fas fa-cut" style="color:#42A5F5;margin-right:0.5rem;"></i>Lista de Piezas para Producción</h3>
                    <small style="color:var(--text-muted);">Medidas de corte ya incluyen descuento de canto grueso</small>
                </div>
                <div class="card-body-custom" style="padding:0;overflow-x:auto;">
                    <table class="bom-table">
                        <thead><tr>
                            <th>Nº</th>
                            <th>Pieza</th>
                            <th>Material</th>
                            <th class="right">Largo Corte (mm)</th>
                            <th class="right">Ancho Corte (mm)</th>
                            <th class="center">Esp.</th>
                            <th class="center">x Mueble</th>
                            <th class="center">TOTAL</th>
                            <th class="center">L1</th>
                            <th class="center">L2</th>
                            <th class="center">A1</th>
                            <th class="center">A2</th>
                            <th class="right">ml Canto</th>
                            <th class="center">Ranura</th>
                            <th class="center">Perf.</th>
                            <th>Notas</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($bom_piezas as $p): ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $p['nro'] ?></td>
                                <td><strong><?= htmlspecialchars($p['nombre_pieza']) ?></strong></td>
                                <td style="color:var(--text-muted);font-size:0.75rem;"><?= htmlspecialchars($p['material']) ?></td>
                                <td class="right"><strong><?= number_format($p['largo_corte_mm'],0) ?></strong></td>
                                <td class="right"><strong><?= number_format($p['ancho_corte_mm'],0) ?></strong></td>
                                <td class="center"><?= $p['espesor_mm'] ?></td>
                                <td class="center"><?= $p['cant_por_mueble'] ?></td>
                                <td class="center"><span class="pill pill-blue"><?= $p['cant_total'] ?></span></td>
                                <td class="center"><?= $p['l1_canto_mm']>0 ? '<span class="pill pill-red">'.($p['l1_canto_mm']).'</span>' : '—' ?></td>
                                <td class="center"><?= $p['l2_canto_mm']>0 ? '<span class="pill pill-red">'.($p['l2_canto_mm']).'</span>' : '—' ?></td>
                                <td class="center"><?= $p['a1_canto_mm']>0 ? '<span class="pill pill-red">'.($p['a1_canto_mm']).'</span>' : '—' ?></td>
                                <td class="center"><?= $p['a2_canto_mm']>0 ? '<span class="pill pill-red">'.($p['a2_canto_mm']).'</span>' : '—' ?></td>
                                <td class="right"><?= $p['ml_tapacanto_con_desp']>0 ? number_format($p['ml_tapacanto_con_desp'],3).' ml' : '—' ?></td>
                                <td class="center"><?= $p['tiene_ranura'] ? '<span class="pill pill-yes">Sí</span>' : '—' ?></td>
                                <td class="center"><?= $p['tiene_perforacion'] ? '<span class="pill pill-yes">Sí</span>' : '—' ?></td>
                                <td style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($p['notas']??'') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bom_piezas)): ?>
                            <tr><td colspan="16" style="text-align:center;padding:2rem;color:var(--text-muted);">Este modelo no tiene piezas registradas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Insumos y Accesorios ── -->
            <?php if (!empty($bom_insumos)): ?>
            <div class="card-panel">
                <div class="card-header">
                    <h3><i class="fas fa-screwdriver" style="color:#66BB6A;margin-right:0.5rem;"></i>Accesorios e Insumos</h3>
                </div>
                <div class="card-body-custom" style="padding:0;">
                    <table class="bom-table">
                        <thead><tr>
                            <th>Insumo</th>
                            <th class="center">x Mueble</th>
                            <th class="center">Total</th>
                            <th>Unidad</th>
                            <th>Notas</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($bom_insumos as $ins): ?>
                            <tr>
                                <td><?= htmlspecialchars($ins['nombre_insumo']) ?></td>
                                <td class="center"><?= $ins['cant_por_mueble'] ?></td>
                                <td class="center"><span class="pill pill-green"><?= $ins['cant_total'] ?></span></td>
                                <td><?= htmlspecialchars($ins['unidad_medida']) ?></td>
                                <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($ins['notas']??'') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>

<script>
// ================================================================
//  DATOS PHP → JS (JSON embebido para exportación client-side)
// ================================================================
const BOM_DATA = {
    modelo:   <?= json_encode([
        'codigo'        => $modelo['codigo'],
        'nombre_modelo' => $modelo['nombre_modelo'],
        'categoria'     => $modelo['categoria'] ?? '',
        'descripcion'   => $modelo['descripcion'] ?? '',
    ]) ?>,
    cantidad:  <?= (int)$cantidad ?>,
    kpis: {
        muebles:   <?= (int)$cantidad ?>,
        piezas:    <?= (int)$total_piezas ?>,
        planchas:  <?= (int)$total_planchas ?>,
        tapacanto: <?= number_format($total_tapacanto, 2, '.', '') ?>
    },
    resumen: <?= json_encode(array_map(fn($r) => [
        'material'          => $r['material'],
        'area_total_m2'     => number_format($r['area_total_m2'], 3, '.', ''),
        'planchas_totales'  => $r['planchas_totales'],
        'ml_tapacanto_total'=> number_format($r['ml_tapacanto_total'], 2, '.', ''),
    ], $resumen)) ?>,
    piezas: <?= json_encode(array_map(fn($p) => [
        'nro'                  => $p['nro'],
        'nombre_pieza'         => $p['nombre_pieza'],
        'material'             => $p['material'],
        'largo_corte_mm'       => number_format($p['largo_corte_mm'], 0, '.', ''),
        'ancho_corte_mm'       => number_format($p['ancho_corte_mm'], 0, '.', ''),
        'espesor_mm'           => $p['espesor_mm'],
        'cant_por_mueble'      => $p['cant_por_mueble'],
        'cant_total'           => $p['cant_total'],
        'l1'                   => $p['l1_canto_mm'],
        'l2'                   => $p['l2_canto_mm'],
        'a1'                   => $p['a1_canto_mm'],
        'a2'                   => $p['a2_canto_mm'],
        'ml_canto'             => number_format($p['ml_tapacanto_con_desp'], 3, '.', ''),
        'ranura'               => $p['tiene_ranura'] ? 'Sí' : '—',
        'perforacion'          => $p['tiene_perforacion'] ? 'Sí' : '—',
        'notas'                => $p['notas'] ?? '',
    ], $bom_piezas)) ?>,
    insumos: <?= json_encode(array_map(fn($i) => [
        'nombre_insumo'    => $i['nombre_insumo'],
        'cant_por_mueble'  => $i['cant_por_mueble'],
        'cant_total'       => $i['cant_total'],
        'unidad_medida'    => $i['unidad_medida'],
        'notas'            => $i['notas'] ?? '',
    ], $bom_insumos)) ?>
};

// ================================================================
//  EXPORTAR PDF
// ================================================================
function exportarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const ROJO  = [198, 40, 40];
    const NEGRO = [18, 18, 18];
    const GRIS  = [100, 100, 110];
    const pageW = doc.internal.pageSize.getWidth();
    let y = 15;

    // ── Encabezado ──────────────────────────────────────────────
    doc.setFillColor(...ROJO);
    doc.rect(0, 0, pageW, 22, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(14); doc.setFont('helvetica', 'bold');
    doc.text('INDUSTRIAS CARPICENTER', 14, 9);
    doc.setFontSize(8); doc.setFont('helvetica', 'normal');
    doc.text('HOJA DE DESPIECE — EXPLOSIÓN DE MATERIALES (BOM)', 14, 15);
    // Info modelo (derecha)
    doc.setFontSize(8);
    doc.text(`Modelo : ${BOM_DATA.modelo.nombre_modelo}`, pageW - 14, 7, { align: 'right' });
    doc.text(`Código : ${BOM_DATA.modelo.codigo}`,        pageW - 14, 12, { align: 'right' });
    doc.text(`Cantidad: ${BOM_DATA.cantidad} mueble(s)`,  pageW - 14, 17, { align: 'right' });
    y = 28;

    // ── KPIs ────────────────────────────────────────────────────
    doc.setTextColor(...NEGRO); doc.setFont('helvetica', 'bold'); doc.setFontSize(9);
    const kpiLabels = [
        ['Muebles a Fabricar', BOM_DATA.kpis.muebles],
        ['Piezas Totales',     BOM_DATA.kpis.piezas],
        ['Planchas Tablero',   BOM_DATA.kpis.planchas],
        ['Tapacanto (+10%)',   BOM_DATA.kpis.tapacanto + ' ml'],
    ];
    const kpiW = (pageW - 28) / 4;
    kpiLabels.forEach(([lbl, val], i) => {
        const x = 14 + i * kpiW;
        doc.setFillColor(245, 245, 248);
        doc.roundedRect(x, y, kpiW - 3, 14, 2, 2, 'F');
        doc.setFontSize(13); doc.setFont('helvetica', 'bold'); doc.setTextColor(...ROJO);
        doc.text(String(val), x + (kpiW - 3) / 2, y + 7, { align: 'center' });
        doc.setFontSize(6.5); doc.setFont('helvetica', 'normal'); doc.setTextColor(...GRIS);
        doc.text(lbl, x + (kpiW - 3) / 2, y + 12, { align: 'center' });
    });
    y += 20;

    // ── Resumen Compras ─────────────────────────────────────────
    if (BOM_DATA.resumen.length > 0) {
        doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(...ROJO);
        doc.text('RESUMEN PARA COMPRAS', 14, y); y += 3;
        doc.autoTable({
            startY: y,
            head: [['Material', 'Área Total (m²)', 'Planchas Necesarias', 'Tapacanto con desp. (ml)']],
            body: BOM_DATA.resumen.map(r => [r.material, r.area_total_m2, r.planchas_totales + ' planchas', r.ml_tapacanto_total + ' ml']),
            theme: 'grid',
            headStyles: { fillColor: ROJO, textColor: [255,255,255], fontSize: 7, fontStyle: 'bold' },
            bodyStyles: { fontSize: 7.5 },
            alternateRowStyles: { fillColor: [250, 250, 252] },
            margin: { left: 14, right: 14 },
        });
        y = doc.lastAutoTable.finalY + 8;
    }

    // ── Lista de Piezas ─────────────────────────────────────────
    doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(...ROJO);
    doc.text('LISTA DE PIEZAS PARA PRODUCCIÓN', 14, y); y += 3;
    doc.autoTable({
        startY: y,
        head: [['Nº','Pieza','Material','Largo\nCorte','Ancho\nCorte','Esp.','x Mueble','Total','L1','L2','A1','A2','ml Canto','Ranura','Perf.','Notas']],
        body: BOM_DATA.piezas.map(p => [
            p.nro, p.nombre_pieza, p.material,
            p.largo_corte_mm, p.ancho_corte_mm, p.espesor_mm,
            p.cant_por_mueble, p.cant_total,
            p.l1 > 0 ? p.l1 : '—', p.l2 > 0 ? p.l2 : '—',
            p.a1 > 0 ? p.a1 : '—', p.a2 > 0 ? p.a2 : '—',
            p.ml_canto > 0 ? p.ml_canto : '—',
            p.ranura, p.perforacion, p.notas
        ]),
        theme: 'striped',
        headStyles: { fillColor: [30, 30, 40], textColor: [255,255,255], fontSize: 6, fontStyle: 'bold', halign: 'center' },
        bodyStyles: { fontSize: 6.5 },
        columnStyles: {
            0:  { halign: 'center', cellWidth: 7 },
            3:  { halign: 'right',  cellWidth: 15 },
            4:  { halign: 'right',  cellWidth: 15 },
            5:  { halign: 'center', cellWidth: 10 },
            6:  { halign: 'center', cellWidth: 14 },
            7:  { halign: 'center', cellWidth: 14 },
            8:  { halign: 'center', cellWidth: 9 },
            9:  { halign: 'center', cellWidth: 9 },
            10: { halign: 'center', cellWidth: 9 },
            11: { halign: 'center', cellWidth: 9 },
            12: { halign: 'right',  cellWidth: 15 },
            13: { halign: 'center', cellWidth: 12 },
            14: { halign: 'center', cellWidth: 10 },
        },
        alternateRowStyles: { fillColor: [245, 245, 250] },
        margin: { left: 14, right: 14 },
        didParseCell: function(data) {
            if (data.column.index === 7 && data.section === 'body') {
                data.cell.styles.textColor = [21, 101, 192];
                data.cell.styles.fontStyle = 'bold';
            }
        }
    });

    // ── Insumos ─────────────────────────────────────────────────
    if (BOM_DATA.insumos.length > 0) {
        y = doc.lastAutoTable.finalY + 8;
        doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(...ROJO);
        doc.text('ACCESORIOS E INSUMOS', 14, y); y += 3;
        doc.autoTable({
            startY: y,
            head: [['Insumo', 'x Mueble', 'Total', 'Unidad', 'Notas']],
            body: BOM_DATA.insumos.map(i => [i.nombre_insumo, i.cant_por_mueble, i.cant_total, i.unidad_medida, i.notas]),
            theme: 'grid',
            headStyles: { fillColor: [22, 163, 74], textColor: [255,255,255], fontSize: 7, fontStyle: 'bold' },
            bodyStyles: { fontSize: 7.5 },
            alternateRowStyles: { fillColor: [240, 253, 244] },
            margin: { left: 14, right: 14 },
        });
    }

    // ── Pie de página ────────────────────────────────────────────
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(6.5); doc.setFont('helvetica', 'normal'); doc.setTextColor(...GRIS);
        const fecha = new Date().toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
        doc.text(`Generado el ${fecha} | Sistema Carpicenter ERP`, 14, doc.internal.pageSize.getHeight() - 5);
        doc.text(`Página ${i} de ${pageCount}`, pageW - 14, doc.internal.pageSize.getHeight() - 5, { align: 'right' });
    }

    doc.save(`BOM_${BOM_DATA.modelo.codigo}_x${BOM_DATA.cantidad}.pdf`);
}

// ================================================================
//  EXPORTAR EXCEL
// ================================================================
function exportarExcel() {
    const wb = XLSX.utils.book_new();
    const fecha = new Date().toLocaleDateString('es-PE');

    // ── Hoja 1: Piezas ───────────────────────────────────────────
    const hdrPiezas = ['Nº','Pieza','Material','Largo Corte (mm)','Ancho Corte (mm)','Espesor (mm)',
        'Cant/Mueble','Cant Total','L1 (mm)','L2 (mm)','A1 (mm)','A2 (mm)',
        'ml Tapacanto (+10%)','Ranura','Perforación','Notas'];
    const rowsPiezas = BOM_DATA.piezas.map(p => [
        p.nro, p.nombre_pieza, p.material,
        Number(p.largo_corte_mm), Number(p.ancho_corte_mm), Number(p.espesor_mm),
        Number(p.cant_por_mueble), Number(p.cant_total),
        p.l1 > 0 ? Number(p.l1) : '', p.l2 > 0 ? Number(p.l2) : '',
        p.a1 > 0 ? Number(p.a1) : '', p.a2 > 0 ? Number(p.a2) : '',
        Number(p.ml_canto), p.ranura, p.perforacion, p.notas
    ]);
    const wsPiezas = XLSX.utils.aoa_to_sheet([
        [`INDUSTRIAS CARPICENTER — HOJA DE DESPIECE`],
        [`Modelo: ${BOM_DATA.modelo.nombre_modelo} | Código: ${BOM_DATA.modelo.codigo} | Cantidad: ${BOM_DATA.cantidad} mueble(s)`],
        [`Generado: ${fecha}`],
        [],
        [`RESUMEN`],
        ['Muebles a Fabricar', BOM_DATA.kpis.muebles],
        ['Piezas Totales a Cortar', BOM_DATA.kpis.piezas],
        ['Planchas Necesarias', BOM_DATA.kpis.planchas],
        ['Tapacanto Total (+10% desp.)', BOM_DATA.kpis.tapacanto + ' ml'],
        [],
        hdrPiezas,
        ...rowsPiezas
    ]);
    // Anchos de columna
    wsPiezas['!cols'] = [6,28,22,14,14,10,12,12,9,9,9,9,18,10,12,25].map(w => ({ wch: w }));
    XLSX.utils.book_append_sheet(wb, wsPiezas, 'Piezas BOM');

    // ── Hoja 2: Resumen Compras ───────────────────────────────────
    const wsCompras = XLSX.utils.aoa_to_sheet([
        ['RESUMEN PARA COMPRAS'],
        [`${BOM_DATA.modelo.nombre_modelo} — ${BOM_DATA.cantidad} mueble(s)`],
        [],
        ['Material', 'Área Total (m²)', 'Planchas Necesarias', 'Tapacanto con Desp. (ml)'],
        ...BOM_DATA.resumen.map(r => [r.material, Number(r.area_total_m2), Number(r.planchas_totales), Number(r.ml_tapacanto_total)])
    ]);
    wsCompras['!cols'] = [{ wch: 30 }, { wch: 16 }, { wch: 20 }, { wch: 24 }];
    XLSX.utils.book_append_sheet(wb, wsCompras, 'Resumen Compras');

    // ── Hoja 3: Insumos ──────────────────────────────────────────
    if (BOM_DATA.insumos.length > 0) {
        const wsIns = XLSX.utils.aoa_to_sheet([
            ['ACCESORIOS E INSUMOS'],
            [`${BOM_DATA.modelo.nombre_modelo} — ${BOM_DATA.cantidad} mueble(s)`],
            [],
            ['Insumo', 'Cant/Mueble', 'Total', 'Unidad', 'Notas'],
            ...BOM_DATA.insumos.map(i => [i.nombre_insumo, Number(i.cant_por_mueble), Number(i.cant_total), i.unidad_medida, i.notas])
        ]);
        wsIns['!cols'] = [{ wch: 35 }, { wch: 14 }, { wch: 12 }, { wch: 12 }, { wch: 30 }];
        XLSX.utils.book_append_sheet(wb, wsIns, 'Insumos');
    }

    XLSX.writeFile(wb, `BOM_${BOM_DATA.modelo.codigo}_x${BOM_DATA.cantidad}.xlsx`);
}
</script>
</body>
</html>
