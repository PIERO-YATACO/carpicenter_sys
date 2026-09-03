<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

// Filtros recibidos
$local_id = $_GET['local_id'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$disponibilidad = trim($_GET['disponibilidad'] ?? 'all');

// Obtener catálogo de locales
$locales = $db->query("SELECT * FROM locales ORDER BY tipo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Identificar si se seleccionó un local específico
$selectedLocal = null;
if ($local_id !== 'all' && is_numeric($local_id)) {
    foreach ($locales as $l) {
        if ($l['id'] == $local_id) {
            $selectedLocal = $l;
            break;
        }
    }
}

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Construir columnas dinámicas para existencias
$localColumns = implode(",\n        ", array_map(function($l) {
    return "COALESCE(SUM(CASE WHEN il.local_id = {$l['id']} THEN il.stock_actual ELSE 0 END), 0) AS local_{$l['id']}_actual,\n" .
           "COALESCE(SUM(CASE WHEN il.local_id = {$l['id']} THEN COALESCE(il.stock_reservado, 0) ELSE 0 END), 0) AS local_{$l['id']}_reservado";
}, $locales));

$sql = "
    SELECT 
        p.id as producto_id,
        p.nombre as producto_nombre,
        p.codigo as producto_codigo,
        cat.nombre as categoria,
        c.id as color_id,
        c.nombre as color_nombre,
        c.codigo as color_codigo,
        pc.codigo as variante_codigo,
        $localColumns,
        COALESCE(SUM(il.stock_actual), 0) as stock_total_actual,
        COALESCE(SUM(il.stock_reservado), 0) as stock_total_reservado
    FROM producto_colores pc
    JOIN productos p ON pc.producto_id = p.id
    JOIN colores c ON pc.color_id = c.id
    LEFT JOIN categorias cat ON p.categoria_id = cat.id
    LEFT JOIN inventario_local il ON il.producto_id = p.id AND il.color_id = c.id
    GROUP BY p.id, p.nombre, p.codigo, cat.nombre, c.id, c.nombre, c.codigo, pc.codigo
    HAVING COALESCE(SUM(il.stock_actual), 0) > 0 OR COALESCE(SUM(il.stock_reservado), 0) > 0
    ORDER BY cat.nombre ASC NULLS LAST, p.nombre ASC, c.nombre ASC
";

$existenciasRaw = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Filtrar filas según los criterios seleccionados
$existencias = [];
foreach ($existenciasRaw as $row) {
    $prodNombre = $row['producto_nombre'] ?? '';
    $colNombre = $row['color_nombre'] ?? '';
    $catNombre = $row['categoria'] ?? '';

    // Filtro por búsqueda (texto)
    if (!empty($search)) {
        $searchLower = mb_strtolower($search);
        $fullText = mb_strtolower($prodNombre . ' ' . $colNombre . ' ' . $catNombre);
        if (mb_strpos($fullText, $searchLower) === false) {
            continue;
        }
    }

    // Filtro por categoría
    if (!empty($categoria)) {
        if (mb_strtolower($catNombre) !== mb_strtolower($categoria)) {
            continue;
        }
    }

    // Filtro por disponibilidad y sede
    if ($selectedLocal) {
        $act = intval($row["local_{$selectedLocal['id']}_actual"] ?? 0);
        $res = intval($row["local_{$selectedLocal['id']}_reservado"] ?? 0);
        $disp = max(0, $act - $res);

        if ($disponibilidad === 'disponible' && $disp <= 0) continue;
        if ($disponibilidad === 'agotado' && $disp > 0) continue;
        if ($disponibilidad === 'reservado' && $res <= 0) continue;

        // Si es para un local y no hay filtro específico de disponibilidad, mostrar artículos con movimiento/stock en ese local o coincidentes con búsqueda
        if ($disponibilidad === 'all' && empty($search) && $act === 0 && $res === 0) {
            continue;
        }
    } else {
        $act = intval($row['stock_total_actual'] ?? 0);
        $res = intval($row['stock_total_reservado'] ?? 0);
        $disp = max(0, $act - $res);

        if ($disponibilidad === 'disponible' && $disp <= 0) continue;
        if ($disponibilidad === 'agotado' && $disp > 0) continue;
        if ($disponibilidad === 'reservado' && $res <= 0) continue;
    }

    $existencias[] = $row;
}

// Calcular totales y nombres
if ($selectedLocal) {
    $grandTotalActual = array_sum(array_column($existencias, "local_{$selectedLocal['id']}_actual"));
    $grandTotalReservado = array_sum(array_column($existencias, "local_{$selectedLocal['id']}_reservado"));
    $grandTotalDisponible = max(0, $grandTotalActual - $grandTotalReservado);
    $reportTitle = "REPORTE DE INVENTARIO — " . mb_strtoupper($selectedLocal['nombre']) . " (" . mb_strtoupper($selectedLocal['tipo']) . ")";
    $sheetName = mb_substr($selectedLocal['nombre'], 0, 30);
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $selectedLocal['nombre']);
    $filename = "Inventario_{$safeName}_" . date('Y-m-d') . ".xls";
    $totalCols = 8;
} else {
    $localTotalsActual = [];
    $localTotalsReservado = [];
    foreach ($locales as $l) {
        $localTotalsActual[$l['id']] = array_sum(array_column($existencias, "local_{$l['id']}_actual"));
        $localTotalsReservado[$l['id']] = array_sum(array_column($existencias, "local_{$l['id']}_reservado"));
    }
    $grandTotalActual = array_sum(array_column($existencias, 'stock_total_actual'));
    $grandTotalReservado = array_sum(array_column($existencias, 'stock_total_reservado'));
    $grandTotalDisponible = max(0, $grandTotalActual - $grandTotalReservado);
    $reportTitle = "REPORTE CONSOLIDADO DE INVENTARIO MULTITIENDA";
    $sheetName = "Consolidado General";
    $filename = "Inventario_Consolidado_General_" . date('Y-m-d') . ".xls";
    $totalCols = 4 + (count($locales) * 2) + 3;
}

// Resumen de filtros
$filtrosText = [];
$filtrosText[] = "<b>Sede / Ámbito:</b> " . ($selectedLocal ? htmlspecialchars($selectedLocal['nombre']) . ' (' . htmlspecialchars($selectedLocal['tipo']) . ')' : 'Todas las Tiendas (Consolidado)');
if (!empty($categoria)) $filtrosText[] = "<b>Categoría:</b> " . htmlspecialchars($categoria);
if (!empty($search)) $filtrosText[] = "<b>Búsqueda:</b> \"" . htmlspecialchars($search) . "\"";
if ($disponibilidad === 'disponible') $filtrosText[] = "<b>Filtro:</b> Solo Disponibles (> 0)";
elseif ($disponibilidad === 'agotado') $filtrosText[] = "<b>Filtro:</b> Agotados (0 disp.)";
elseif ($disponibilidad === 'reservado') $filtrosText[] = "<b>Filtro:</b> Con Reservas";
$filtrosHtml = implode(" &nbsp;|&nbsp; ", $filtrosText);

// Encabezados HTTP para descarga Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

echo "<meta charset=\"utf-8\">\n";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name><?= htmlspecialchars($sheetName) ?></x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
     <x:Print>
      <x:ValidPrinterInfo/>
      <x:PaperSizeIndex>9</x:PaperSizeIndex>
      <x:HorizontalResolution>600</x:HorizontalResolution>
      <x:VerticalResolution>600</x:VerticalResolution>
     </x:Print>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    body {
        font-family: 'Segoe UI', Calibri, Arial, sans-serif;
        font-size: 10pt;
        color: #1E293B;
        background-color: #FFFFFF;
    }
    .banner-main {
        background-color: #881337;
        color: #FFFFFF;
        font-size: 13pt;
        font-weight: bold;
        letter-spacing: 0.5px;
        padding: 10px 14px;
        text-align: left;
        vertical-align: middle;
        border-bottom: 2px solid #4C0519;
    }
    .banner-meta {
        background-color: #F8FAFC;
        color: #475569;
        font-size: 8.5pt;
        padding: 6px 14px;
        border-bottom: 1px solid #E2E8F0;
        vertical-align: middle;
    }
    .spacer-row {
        height: 10px;
        font-size: 1pt;
        background-color: #FFFFFF;
    }

    /* KPI Cards */
    .kpi-hdr {
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: center;
        vertical-align: middle;
        padding: 5px 8px;
        border: 1px solid #CBD5E1;
    }
    .kpi-val {
        font-size: 12pt;
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        padding: 6px 8px;
        border: 1px solid #CBD5E1;
    }
    
    .kpi-hdr-blue { background-color: #E2E8F0; color: #1E293B; }
    .kpi-val-blue { background-color: #F8FAFC; color: #0F172A; }
    
    .kpi-hdr-rose { background-color: #FFE4E6; color: #9F1239; }
    .kpi-val-rose { background-color: #FFF1F2; color: #BE123C; }
    
    .kpi-hdr-emerald { background-color: #D1FAE5; color: #065F46; }
    .kpi-val-emerald { background-color: #ECFDF5; color: #047857; }
    
    .kpi-hdr-sky { background-color: #E0F2FE; color: #075985; }
    .kpi-val-sky { background-color: #F0F9FF; color: #0284C7; }

    /* Data Table */
    table.data-table {
        border-collapse: collapse;
        width: 100%;
    }
    .th-main {
        background-color: #1E293B;
        color: #FFFFFF;
        font-size: 9pt;
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        padding: 8px 6px;
        border: 1px solid #334155;
    }
    .th-disp {
        background-color: #047857;
        color: #FFFFFF;
        font-size: 9pt;
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        padding: 8px 6px;
        border: 1px solid #065F46;
    }
    .th-res {
        background-color: #475569;
        color: #FFFFFF;
        font-size: 9pt;
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        padding: 8px 6px;
        border: 1px solid #334155;
    }
    .th-sub-store {
        background-color: #334155;
        color: #F8FAFC;
        font-size: 8pt;
        font-weight: normal;
        text-align: center;
        border: 1px solid #1E293B;
    }

    .td-cell {
        font-size: 9pt;
        padding: 5px 8px;
        vertical-align: middle;
        border: 1px solid #E2E8F0;
    }
    .td-num {
        font-size: 9.5pt;
        text-align: right;
        padding: 5px 8px;
        vertical-align: middle;
        border: 1px solid #E2E8F0;
        mso-number-format: "\#\,\#\#0";
    }
    .td-num-bold {
        font-size: 9.5pt;
        font-weight: bold;
        text-align: right;
        padding: 5px 8px;
        vertical-align: middle;
        border: 1px solid #E2E8F0;
        mso-number-format: "\#\,\#\#0";
    }
    .row-even { background-color: #FFFFFF; }
    .row-odd { background-color: #F8FAFC; }

    /* Badges */
    .badge-instock { background-color: #DCFCE7; color: #15803D; font-weight: bold; text-align: center; border: 1px solid #86EFAC; }
    .badge-lowstock { background-color: #FEF3C7; color: #B45309; font-weight: bold; text-align: center; border: 1px solid #FCD34D; }
    .badge-nostock { background-color: #FEE2E2; color: #B91C1C; font-weight: bold; text-align: center; border: 1px solid #FCA5A5; }

    .tr-total td {
        background-color: #E2E8F0;
        font-size: 9.5pt;
        font-weight: bold;
        vertical-align: middle;
        padding: 7px 8px;
        border-top: 2px solid #1E293B;
        border-bottom: 3px double #1E293B;
        border-left: 1px solid #CBD5E1;
        border-right: 1px solid #CBD5E1;
        mso-number-format: "\#\,\#\#0";
    }
</style>
</head>
<body>

<table>
    <!-- Banner de Título Corporativo -->
    <tr>
        <td colspan="<?= $totalCols ?>" class="banner-main">
            INDUSTRIAS CARPICENTER® &nbsp;—&nbsp; <?= htmlspecialchars($reportTitle) ?>
        </td>
    </tr>
    <tr>
        <td colspan="<?= $totalCols ?>" class="banner-meta">
            <b>Generado el:</b> <?= date('d/m/Y H:i:s') ?> &nbsp;|&nbsp; <?= $filtrosHtml ?> &nbsp;|&nbsp; <b>Sistema:</b> Carpicenter ERP
        </td>
    </tr>
    <tr class="spacer-row"><td colspan="<?= $totalCols ?>">&nbsp;</td></tr>

    <!-- Tarjetas KPI Resumen Estructuradas -->
    <?php if ($selectedLocal): ?>
        <tr>
            <td colspan="2" class="kpi-hdr kpi-hdr-blue">TOTAL STOCK FÍSICO</td>
            <td colspan="2" class="kpi-hdr kpi-hdr-rose">RESERVADO CONTRATOS</td>
            <td colspan="2" class="kpi-hdr kpi-hdr-emerald">DISPONIBLE PARA VENTA</td>
            <td colspan="2" class="kpi-hdr kpi-hdr-sky">ARTÍCULOS VISIBLES</td>
        </tr>
        <tr>
            <td colspan="2" class="kpi-val kpi-val-blue"><?= number_format($grandTotalActual) ?> und.</td>
            <td colspan="2" class="kpi-val kpi-val-rose"><?= number_format($grandTotalReservado) ?> und.</td>
            <td colspan="2" class="kpi-val kpi-val-emerald"><?= number_format($grandTotalDisponible) ?> und.</td>
            <td colspan="2" class="kpi-val kpi-val-sky"><?= count($existencias) ?> variantes</td>
        </tr>
    <?php else: ?>
        <tr>
            <td colspan="<?= max(2, (int)($totalCols / 4)) ?>" class="kpi-hdr kpi-hdr-blue">TOTAL STOCK FÍSICO</td>
            <td colspan="<?= max(2, (int)($totalCols / 4)) ?>" class="kpi-hdr kpi-hdr-rose">RESERVADO CONTRATOS</td>
            <td colspan="<?= max(2, (int)($totalCols / 4)) ?>" class="kpi-hdr kpi-hdr-emerald">DISPONIBLE PARA VENTA</td>
            <td colspan="<?= $totalCols - (3 * max(2, (int)($totalCols / 4))) ?>" class="kpi-hdr kpi-hdr-sky">ARTÍCULOS VISIBLES</td>
        </tr>
        <tr>
            <td colspan="<?= max(2, (int)($totalCols / 4)) ?>" class="kpi-val kpi-val-blue"><?= number_format($grandTotalActual) ?> und.</td>
            <td colspan="<?= max(2, (int)($totalCols / 4)) ?>" class="kpi-val kpi-val-rose"><?= number_format($grandTotalReservado) ?> und.</td>
            <td colspan="<?= max(2, (int)($totalCols / 4)) ?>" class="kpi-val kpi-val-emerald"><?= number_format($grandTotalDisponible) ?> und.</td>
            <td colspan="<?= $totalCols - (3 * max(2, (int)($totalCols / 4))) ?>" class="kpi-val kpi-val-sky"><?= count($existencias) ?> variantes</td>
        </tr>
    <?php endif; ?>

    <tr class="spacer-row"><td colspan="<?= $totalCols ?>">&nbsp;</td></tr>
</table>

<?php if ($selectedLocal): ?>
    <!-- FORMATO ELEGANTE PARA TIENDA O ALMACÉN ESPECÍFICO -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="th-main" style="width:40px;">#</th>
                <th class="th-main" style="width:140px; text-align:left;">CATEGORÍA</th>
                <th class="th-main" style="width:230px; text-align:left;">PRODUCTO / MODELO</th>
                <th class="th-main" style="width:160px; text-align:left;">COLOR / VARIANTE</th>
                <th class="th-main" style="width:105px; text-align:right;">STOCK FÍSICO</th>
                <th class="th-res" style="width:105px; text-align:right;">RESERVADO</th>
                <th class="th-disp" style="width:120px; text-align:right;">DISPONIBLE</th>
                <th class="th-main" style="width:105px;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($existencias as $row): 
                $act = intval($row["local_{$selectedLocal['id']}_actual"] ?? 0);
                $res = intval($row["local_{$selectedLocal['id']}_reservado"] ?? 0);
                $disp = max(0, $act - $res);
                $rowClass = ($i % 2 === 0) ? 'row-odd' : 'row-even';
                $skuCode = (!empty($row['producto_codigo']) ? $row['producto_codigo'] : 'CA-PRD') . (!empty($row['color_codigo']) ? $row['color_codigo'] : '');
                
                if ($disp > 3) {
                    $badgeClass = 'badge-instock';
                    $estadoTxt = 'En Stock';
                } elseif ($disp > 0) {
                    $badgeClass = 'badge-lowstock';
                    $estadoTxt = 'Bajo Stock';
                } else {
                    $badgeClass = 'badge-nostock';
                    $estadoTxt = 'Agotado';
                }
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="td-cell" style="text-align:center; color:#64748B;"><?= $i++ ?></td>
                <td class="td-cell" style="color:#475569;"><?= htmlspecialchars(mb_strtoupper($row['categoria'] ?? 'SIN CATEGORÍA')) ?></td>
                <td class="td-cell" style="font-weight:bold; color:#0F172A;">[<?= htmlspecialchars($skuCode) ?>] <?= htmlspecialchars($row['producto_nombre']) ?></td>
                <td class="td-cell" style="color:#334155; font-weight:600;"><?= htmlspecialchars($row['color_nombre']) ?></td>
                <td class="td-num-bold" style="color:<?= $act > 0 ? '#0F172A' : '#94A3B8' ?>;"><?= $act ?></td>
                <td class="td-num-bold" style="color:<?= $res > 0 ? '#BE123C' : '#94A3B8' ?>;"><?= $res ?></td>
                <td class="td-num-bold" style="color:<?= $disp > 0 ? '#047857' : '#94A3B8' ?>; font-size:10pt;"><?= $disp ?></td>
                <td class="td-cell <?= $badgeClass ?>"><?= $estadoTxt ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="tr-total">
                <td colspan="4" style="text-align:center; text-transform:uppercase; letter-spacing:0.5px;">
                    TOTALES — <?= mb_strtoupper($selectedLocal['nombre']) ?>
                </td>
                <td style="text-align:right; color:#0F172A;"><?= $grandTotalActual ?></td>
                <td style="text-align:right; color:#BE123C;"><?= $grandTotalReservado ?></td>
                <td style="text-align:right; color:#047857; font-size:10.5pt;"><?= $grandTotalDisponible ?></td>
                <td style="text-align:center;">—</td>
            </tr>
        </tbody>
    </table>

<?php else: ?>
    <!-- FORMATO CONSOLIDADO MULTITIENDA CORPORATIVO -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-main" style="width:40px;">#</th>
                <th rowspan="2" class="th-main" style="width:140px; text-align:left;">CATEGORÍA</th>
                <th rowspan="2" class="th-main" style="width:220px; text-align:left;">PRODUCTO / MODELO</th>
                <th rowspan="2" class="th-main" style="width:150px; text-align:left;">COLOR / VARIANTE</th>
                <?php foreach ($locales as $l): ?>
                    <th colspan="2" class="th-main" style="background-color:#334155; border-color:#1E293B;">
                        <?= strtoupper(htmlspecialchars($l['nombre'])) ?>
                    </th>
                <?php endforeach; ?>
                <th colspan="3" class="th-disp">CONSOLIDADO GENERAL</th>
            </tr>
            <tr>
                <?php foreach ($locales as $l): ?>
                    <th class="th-sub-store" style="width:65px;">Físico</th>
                    <th class="th-sub-store" style="width:65px; background-color:#475569;">Reserv.</th>
                <?php endforeach; ?>
                <th class="th-disp" style="width:85px; font-size:8.5pt;">Stock Físico</th>
                <th class="th-disp" style="width:85px; font-size:8.5pt; background-color:#065F46;">Reservado</th>
                <th class="th-disp" style="width:90px; font-size:8.5pt; background-color:#022C22;">Disponible</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($existencias as $row): 
                $totActual = intval($row['stock_total_actual']);
                $totRes = intval($row['stock_total_reservado']);
                $disp = max(0, $totActual - $totRes);
                $rowClass = ($i % 2 === 0) ? 'row-odd' : 'row-even';
                $skuCode = (!empty($row['producto_codigo']) ? $row['producto_codigo'] : 'CA-PRD') . (!empty($row['color_codigo']) ? $row['color_codigo'] : '');
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="td-cell" style="text-align:center; color:#64748B;"><?= $i++ ?></td>
                <td class="td-cell" style="color:#475569;"><?= htmlspecialchars(mb_strtoupper($row['categoria'] ?? 'SIN CATEGORÍA')) ?></td>
                <td class="td-cell" style="font-weight:bold; color:#0F172A;">[<?= htmlspecialchars($skuCode) ?>] <?= htmlspecialchars($row['producto_nombre']) ?></td>
                <td class="td-cell" style="color:#334155; font-weight:600;"><?= htmlspecialchars($row['color_nombre']) ?></td>

                <?php foreach ($locales as $l): 
                    $act = intval($row["local_{$l['id']}_actual"] ?? 0);
                    $res = intval($row["local_{$l['id']}_reservado"] ?? 0);
                ?>
                    <td class="td-num" style="color:<?= $act > 0 ? '#0F172A' : '#94A3B8' ?>; font-weight:<?= $act > 0 ? 'bold' : 'normal' ?>;">
                        <?= $act ?>
                    </td>
                    <td class="td-num" style="color:<?= $res > 0 ? '#BE123C' : '#94A3B8' ?>; font-weight:<?= $res > 0 ? 'bold' : 'normal' ?>;">
                        <?= $res ?>
                    </td>
                <?php endforeach; ?>

                <td class="td-num-bold" style="color:#0F172A;"><?= $totActual ?></td>
                <td class="td-num-bold" style="color:#BE123C;"><?= $totRes ?></td>
                <td class="td-num-bold" style="color:#047857; font-size:10pt;"><?= $disp ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- Fila de Totales -->
            <tr class="tr-total">
                <td colspan="4" style="text-align:center; text-transform:uppercase; letter-spacing:0.5px;">
                    TOTALES GENERALES
                </td>
                <?php foreach ($locales as $l): ?>
                    <td style="text-align:right; color:#0F172A;"><?= $localTotalsActual[$l['id']] ?? 0 ?></td>
                    <td style="text-align:right; color:#BE123C;"><?= $localTotalsReservado[$l['id']] ?? 0 ?></td>
                <?php endforeach; ?>
                <td style="text-align:right; color:#0F172A;"><?= $grandTotalActual ?></td>
                <td style="text-align:right; color:#BE123C;"><?= $grandTotalReservado ?></td>
                <td style="text-align:right; color:#047857; font-size:10.5pt;"><?= $grandTotalDisponible ?></td>
            </tr>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
