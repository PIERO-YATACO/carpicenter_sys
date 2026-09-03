<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die("ID de cuadre no proporcionado.");

// Obtener cabecera del cuadre
$stmt = $db->prepare("SELECT * FROM finanzas_cuadre_caja WHERE id = ?");
$stmt->execute([$id]);
$cuadre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cuadre) die("Cuadre de caja no encontrado.");

// Obtener detalles ordenados
$stmtDet = $db->prepare("SELECT * FROM finanzas_cuadre_detalle WHERE cuadre_id = ? ORDER BY id ASC");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$entradas = array_filter($detalles, fn($d) => $d['tipo'] === 'ENTRADA');
$salidas = array_filter($detalles, fn($d) => $d['tipo'] === 'SALIDA');

$filename = "Reporte_Cuadre_Caja_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cuadre['codigo'] ?? ('CC_'.$cuadre['id'])) . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM

$f_inicio_str = $cuadre['fecha_inicio'] ? date('d/m/Y', strtotime($cuadre['fecha_inicio'])) : '—';
$f_fin_str = $cuadre['fecha_fin'] ? date('d/m/Y', strtotime($cuadre['fecha_fin'])) : '';
$fecha_emision = date('d/m/Y H:i');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Cuadre de Caja</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; color: #0F172A; }
    table { border-collapse: collapse; }
    
    .th-col { background-color: #1E293B; color: #FFFFFF; font-weight: bold; border: 1px solid #1E293B; text-align: center; vertical-align: middle; padding: 6px 8px; font-size: 9.5pt; text-transform: uppercase; }
    .td-cell { border: 1px solid #CBD5E1; padding: 5px 8px; vertical-align: middle; }
    .td-zebra { background-color: #F8FAFC; border: 1px solid #CBD5E1; padding: 5px 8px; vertical-align: middle; }
    
    .num-green { text-align: right; font-weight: bold; color: #166534; mso-number-format:"\"S/\"\\ \#\,\#\#0\.00"; }
    .num-red { text-align: right; font-weight: bold; color: #991B1B; mso-number-format:"\"S/\"\\ \#\,\#\#0\.00"; }
    
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .txt-date { mso-number-format:"\@"; text-align: center; }
</style>
</head>
<body>

<table border="0" style="border-collapse:collapse;">

    <!-- 1. ENCABEZADO CORPORATIVO LIMPIO (SIN IMAGEN) -->
    <tr>
        <td colspan="5" style="font-size:14pt; font-weight:bold; color:#0F172A; text-align:center; height:32px; vertical-align:middle;">
            INDUSTRIAS CARPICENTER S.A.C.
        </td>
    </tr>
    <tr>
        <td colspan="5" style="font-size:11pt; font-weight:bold; color:#C62828; text-align:center; height:24px; vertical-align:middle;">
            INFORME GERENCIAL DE CUADRE Y LIQUIDACIÓN DE CAJA
        </td>
    </tr>
    <tr>
        <td colspan="5" style="color:#64748B; font-size:9.5pt; text-align:center; height:20px; vertical-align:middle; border-bottom:2px solid #E2E8F0; padding-bottom:6px;">
            Código: <b><?= htmlspecialchars($cuadre['codigo'] ?? ('CC-'.$cuadre['id'])) ?></b> &nbsp;|&nbsp; Período: <b><?= $f_inicio_str ?> <?= (!empty($cuadre['fecha_fin']) && $cuadre['fecha_fin'] !== $cuadre['fecha_inicio']) ? 'al '.$f_fin_str : '' ?></b> &nbsp;|&nbsp; Emisión: <?= $fecha_emision ?>
        </td>
    </tr>

    <!-- ESPACIO -->
    <tr><td colspan="5" style="height:10px;"></td></tr>

    <!-- 2. METADATA DE CONTROL -->
    <tr>
        <td style="width:110px; background-color:#F1F5F9; font-weight:bold; color:#475569; border:1px solid #CBD5E1; padding:5px 8px;">Área:</td>
        <td colspan="2" style="width:440px; background-color:#FFFFFF; font-weight:bold; color:#0F172A; border:1px solid #CBD5E1; padding:5px 8px;"><?= htmlspecialchars($cuadre['area'] ?? 'ADMINISTRATIVO') ?></td>
        <td style="width:150px; background-color:#F1F5F9; font-weight:bold; color:#475569; border:1px solid #CBD5E1; padding:5px 8px;">Responsable:</td>
        <td style="width:145px; background-color:#FFFFFF; font-weight:bold; color:#0F172A; border:1px solid #CBD5E1; padding:5px 8px;"><?= htmlspecialchars($cuadre['encargado'] ?? 'NAOMI') ?></td>
    </tr>
    <tr>
        <td style="background-color:#F1F5F9; font-weight:bold; color:#475569; border:1px solid #CBD5E1; padding:5px 8px;">Alcance:</td>
        <td colspan="2" style="background-color:#FFFFFF; font-weight:bold; color:#0F172A; border:1px solid #CBD5E1; padding:5px 8px;"><?= htmlspecialchars($cuadre['tienda'] ?? 'TODAS LAS TIENDAS') ?></td>
        <td style="background-color:#F1F5F9; font-weight:bold; color:#475569; border:1px solid #CBD5E1; padding:5px 8px;">Fecha Cierre:</td>
        <td style="background-color:#FFFFFF; font-weight:bold; color:#0F172A; border:1px solid #CBD5E1; padding:5px 8px; mso-number-format:'\@';"><?= $f_inicio_str ?></td>
    </tr>

    <!-- ESPACIO -->
    <tr><td colspan="5" style="height:12px;"></td></tr>

    <!-- 3. RESUMEN DE BALANCE FINANCIERO PERFECTAMENTE ALINEADO -->
    <!-- Fila de Títulos de los KPIs -->
    <tr>
        <td colspan="2" style="background-color:#F0FDF4; border-top:1px solid #BBF7D0; border-left:1px solid #BBF7D0; border-right:1px solid #BBF7D0; text-align:center; vertical-align:middle; height:20px; font-size:8.5pt; font-weight:bold; text-transform:uppercase; color:#15803D;">
            TOTAL RECAUDACIÓN TIENDAS
        </td>
        <td colspan="2" style="background-color:#FEF2F2; border-top:1px solid #FECACA; border-left:1px solid #FECACA; border-right:1px solid #FECACA; text-align:center; vertical-align:middle; height:20px; font-size:8.5pt; font-weight:bold; text-transform:uppercase; color:#B91C1C;">
            TOTAL EGRESOS / GASTOS
        </td>
        <td style="background-color:#FFFFFF; border-top:2px solid #C62828; border-left:2px solid #C62828; border-right:2px solid #C62828; text-align:center; vertical-align:middle; height:20px; font-size:8.5pt; font-weight:bold; text-transform:uppercase; color:#C62828;">
            SALDO NETO EN CAJA
        </td>
    </tr>
    <!-- Fila de Montos de los KPIs -->
    <tr>
        <td colspan="2" style="background-color:#F0FDF4; border-bottom:1px solid #BBF7D0; border-left:1px solid #BBF7D0; border-right:1px solid #BBF7D0; text-align:center; vertical-align:middle; height:34px; font-size:14pt; font-weight:bold; color:#15803D; mso-number-format:'&quot;S/&quot;\\ #\,##0\.00';">
            <?= floatval($cuadre['total_ingreso']) ?>
        </td>
        <td colspan="2" style="background-color:#FEF2F2; border-bottom:1px solid #FECACA; border-left:1px solid #FECACA; border-right:1px solid #FECACA; text-align:center; vertical-align:middle; height:34px; font-size:14pt; font-weight:bold; color:#B91C1C; mso-number-format:'&quot;S/&quot;\\ #\,##0\.00';">
            <?= floatval($cuadre['total_egreso']) ?>
        </td>
        <td style="background-color:#FFFFFF; border-bottom:2px solid #C62828; border-left:2px solid #C62828; border-right:2px solid #C62828; text-align:center; vertical-align:middle; height:34px; font-size:14pt; font-weight:bold; color:#C62828; mso-number-format:'&quot;S/&quot;\\ #\,##0\.00';">
            <?= floatval($cuadre['saldo_final']) ?>
        </td>
    </tr>

    <!-- ESPACIO -->
    <tr><td colspan="5" style="height:14px;"></td></tr>

    <!-- 4. SECCIÓN 1: RECAUDACIÓN EN TIENDAS (ENTRADAS) -->
    <tr>
        <td colspan="5" style="font-size:10.5pt; font-weight:bold; color:#0F172A; padding:5px 0;">
            1. RECAUDACIÓN Y COBROS EN TIENDAS (ENTRADAS)
        </td>
    </tr>
    <tr>
        <th class="th-col" style="width:110px;">Fecha</th>
        <th class="th-col" style="width:160px;">Tienda / Origen</th>
        <th colspan="2" class="th-col" style="width:430px;">N° Tickets / Justificante</th>
        <th class="th-col" style="width:145px; text-align:right;">Monto Recaudado</th>
    </tr>

    <?php 
    $hasSaldoAnterior = false;
    $i = 0;
    foreach($entradas as $ent): 
        $i++;
        $cls = ($i % 2 == 0) ? 'td-zebra' : 'td-cell';
        $f_item = $ent['fecha'] ? date('d/m/Y', strtotime($ent['fecha'])) : '';
        $descUpper = strtoupper($ent['descripcion'] ?? $ent['detalle'] ?? '');
        $isSaldoAnt = (strpos($descUpper, 'SALDO ANTERIOR') !== false || $ent['categoria'] === 'SALDO_ANTERIOR');
        if ($isSaldoAnt) $hasSaldoAnterior = true;
        $montoEnt = floatval($ent['monto']);
    ?>
    <tr>
        <td class="<?= $cls ?> txt-date"><?= $isSaldoAnt ? '—' : $f_item ?></td>
        <td class="<?= $cls ?> bold"><?= htmlspecialchars(strtoupper($ent['descripcion'] ?? $ent['detalle'])) ?></td>
        <td colspan="2" class="<?= $cls ?>"><?= $isSaldoAnt ? '—' : htmlspecialchars($ent['nro_justificante'] ?? '—') ?></td>
        <td class="<?= $cls ?> num-green"><?= $montoEnt ?></td>
    </tr>
    <?php endforeach; ?>

    <?php if(!$hasSaldoAnterior && floatval($cuadre['saldo_anterior']) > 0): ?>
    <tr>
        <td class="td-cell txt-date">—</td>
        <td class="td-cell bold">SALDO ANTERIOR</td>
        <td colspan="2" class="td-cell">—</td>
        <td class="td-cell num-green"><?= floatval($cuadre['saldo_anterior']) ?></td>
    </tr>
    <?php endif; ?>

    <!-- TOTAL ENTRADAS -->
    <tr>
        <td colspan="4" style="background-color:#F0FDF4; text-align:right; color:#15803D; padding:7px 10px; border:1px solid #BBF7D0; font-weight:bold;">TOTAL RECAUDACIÓN (ENTRADAS):</td>
        <td class="num-green" style="background-color:#F0FDF4; font-size:11pt; padding:7px 10px; border:1px solid #BBF7D0; font-weight:bold;"><?= floatval($cuadre['total_ingreso']) ?></td>
    </tr>

    <!-- ESPACIO -->
    <tr><td colspan="5" style="height:14px;"></td></tr>

    <!-- 5. SECCIÓN 2: EGRESOS OPERATIVOS -->
    <tr>
        <td colspan="5" style="font-size:10.5pt; font-weight:bold; color:#0F172A; padding:5px 0;">
            2. DETALLE DE EGRESOS Y SALIDAS OPERATIVAS DEDUCIDAS
        </td>
    </tr>
    <tr>
        <th class="th-col" style="width:110px;">Fecha</th>
        <th class="th-col" style="width:160px;">Categoría</th>
        <th class="th-col" style="width:280px;">Descripción del Gasto / Salida</th>
        <th class="th-col" style="width:150px;">N° Justificante / OP</th>
        <th class="th-col" style="width:145px; text-align:right;">Monto Deducido</th>
    </tr>

    <?php 
    $j = 0;
    if (empty($salidas)): 
    ?>
    <tr>
        <td colspan="5" class="td-cell center" style="color:#94A3B8; padding:10px;">Sin movimientos de egreso registrados en este período.</td>
    </tr>
    <?php else: foreach($salidas as $sal): 
        $j++;
        $cls = ($j % 2 == 0) ? 'td-zebra' : 'td-cell';
        $f_sal = $sal['fecha'] ? date('d/m/Y', strtotime($sal['fecha'])) : '';
        $cat = strtoupper($sal['categoria'] ?? 'OTROS');
        $monto = floatval($sal['monto']);
    ?>
    <tr>
        <td class="<?= $cls ?> txt-date"><?= $f_sal ?></td>
        <td class="<?= $cls ?> bold" style="color:#475569;"><?= htmlspecialchars($cat) ?></td>
        <td class="<?= $cls ?> bold"><?= htmlspecialchars(strtoupper($sal['descripcion'] ?? $sal['detalle'])) ?></td>
        <td class="<?= $cls ?> center"><?= htmlspecialchars($sal['nro_justificante'] ?? '—') ?></td>
        <td class="<?= $cls ?> num-red"><?= $monto ?></td>
    </tr>
    <?php endforeach; endif; ?>

    <!-- TOTAL EGRESOS -->
    <tr>
        <td colspan="4" style="background-color:#FEF2F2; text-align:right; color:#B91C1C; padding:7px 10px; border:1px solid #FECACA; font-weight:bold;">TOTAL EGRESOS (GASTOS):</td>
        <td class="num-red" style="background-color:#FEF2F2; font-size:11pt; padding:7px 10px; border:1px solid #FECACA; font-weight:bold;"><?= floatval($cuadre['total_egreso']) ?></td>
    </tr>

    <!-- ESPACIO -->
    <tr><td colspan="5" style="height:12px;"></td></tr>

    <?php if(!empty($cuadre['observacion'])): ?>
    <tr>
        <td colspan="5" style="background-color:#F8FAFC; border:1px solid #CBD5E1; padding:6px 8px; font-size:9pt; color:#475569;">
            <b>Observaciones:</b> <?= htmlspecialchars($cuadre['observacion']) ?>
        </td>
    </tr>
    <tr><td colspan="5" style="height:10px;"></td></tr>
    <?php endif; ?>

    <!-- FOOTER -->
    <tr>
        <td colspan="5" style="border-top:1px solid #CBD5E1; font-size:8.5pt; color:#94A3B8; padding-top:6px;">
            INDUSTRIAS CARPICENTER S.A.C. &bull; RUC: 20608569421 &bull; Sistema de Gestión Financiera &bull; Emitido por: <?= htmlspecialchars($cuadre['encargado'] ?? 'NAOMI') ?>
        </td>
    </tr>

</table>

</body>
</html>
