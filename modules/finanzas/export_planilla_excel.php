<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die("ID de planilla no proporcionado.");

// Obtener cabecera de la planilla
$stmt = $db->prepare("SELECT * FROM planilla_semanal WHERE id = ?");
$stmt->execute([$id]);
$planilla = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$planilla) die("Planilla no encontrada.");

// Obtener detalles agrupados
$stmtDet = $db->prepare("
    SELECT * FROM planilla_semanal_detalle 
    WHERE planilla_id = ? 
    ORDER BY orden ASC, id ASC
");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$detalles_admin = [];
$detalles_tiendas = [];
$detalles_prod = [];

foreach ($detalles as $d) {
    if ($d['categoria'] === 'ADMINISTRATIVO') $detalles_admin[] = $d;
    elseif ($d['categoria'] === 'TIENDAS') $detalles_tiendas[] = $d;
    else $detalles_prod[] = $d;
}

$filename = "Planilla_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $planilla['semana_codigo']) . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
    table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 10pt; }
    th { background-color: #1F4E78; color: #FFFFFF; font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle; padding: 6px 4px; }
    td { border: 1px solid #000000; padding: 4px 6px; vertical-align: middle; }
    .num { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
</style>
</head>
<body>
<table border="1" style="border-collapse:collapse;">
    <!-- TÍTULO DE LA PLANILLA (Col A hasta P) -->
    <tr>
        <td colspan="16" style="background-color:#FFFFFF; font-size:13pt; font-weight:bold; text-align:center; height:35px; border:1px solid #000000;">
            <?= htmlspecialchars(strtoupper($planilla['semana_codigo'])) ?> (<?= date('d/m/Y', strtotime($planilla['fecha_inicio'])) ?> AL <?= date('d/m/Y', strtotime($planilla['fecha_fin'])) ?>)
        </td>
    </tr>

    <!-- CABECERAS -->
    <tr>
        <th style="width:35px;">N°</th>
        <th style="width:210px;">NOMBRE Y APELLIDO</th>
        <th style="width:115px;">ÁREA</th>
        <th style="width:160px;">N° DE CUENTA BCP</th>
        <th style="width:85px;">MENSUAL</th>
        <th style="width:85px;">BASE X DIA</th>
        <th style="width:95px;">BASE SEMANAL</th>
        <th style="width:105px;">BONO / COMISIÓN</th>
        <th style="width:95px;">H. EXTRA / DOMINGO</th>
        <th style="width:85px;">PAGO X HORA</th>
        <th style="width:85px;">FALTA X HORA</th>
        <th style="width:95px;">DSTO. PRESTAMO</th>
        <th style="width:110px;">DESCUENTO X PLANILLA</th>
        <th style="width:95px;">TOTAL DSCTOS</th>
        <th style="width:115px;">TOTAL A PAGAR</th>
        <th style="width:100px;">MÉTODO</th>
    </tr>

    <!-- ================= 1. ADMINISTRATIVO (Amarillo Completo) ================= -->
    <?php 
    $num = 1;
    $tot_base_admin = 0; $tot_bono_admin = 0; $tot_dscto_admin = 0; $tot_pagar_admin = 0;
    foreach ($detalles_admin as $d): 
        if (!$d['incluido']) continue;
        $tot_base_admin += $d['base_semanal'];
        $tot_bono_admin += $d['bono_comision'];
        $tot_dscto_admin += $d['total_descuentos'];
        $tot_pagar_admin += $d['total_pagar'];
    ?>
    <tr>
        <td class="center"><?= $num++ ?></td>
        <td class="bold"><?= htmlspecialchars($d['nombre_personal']) ?></td>
        <td class="center"><?= htmlspecialchars($d['area']) ?></td>
        <td class="center"><?= htmlspecialchars($d['cuenta_bancaria']) ?></td>
        <td class="num"><?= number_format($d['sueldo_mensual'], 2) ?></td>
        <td class="num"><?= number_format($d['base_dia'], 2) ?></td>
        <td class="num bold"><?= number_format($d['base_semanal'], 2) ?></td>
        <td class="num"><?= number_format($d['bono_comision'], 2) ?></td>
        <td class="center"><?= $d['horas_extra'] > 0 ? $d['horas_extra'] : '' ?></td>
        <td class="num"><?= number_format($d['pago_hora'], 2) ?></td>
        <td class="num" style="color:red;"><?= $d['descuento_falta'] > 0 ? number_format($d['descuento_falta'], 2) : '' ?></td>
        <td class="num"><?= number_format($d['descuento_prestamo'], 2) ?></td>
        <td class="num"><?= number_format($d['descuento_planilla'], 2) ?></td>
        <td class="num bold" style="color:red;"><?= number_format($d['total_descuentos'], 2) ?></td>
        <td class="num bold" style="background-color:#F2F2F2;">S/ <?= number_format($d['total_pagar'], 2) ?></td>
        <td class="center"><?= htmlspecialchars($d['metodo_pago']) ?></td>
    </tr>
    <?php endforeach; ?>

    <!-- Subtotal Administrativo (100% Amarillo de Columna A a P) -->
    <tr>
        <td colspan="4" style="background-color:#FFFF00; font-weight:bold; text-align:center; border:1px solid #000000;">TOTAL PLANILLA ADMINISTRATIVO</td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td class="num bold" style="background-color:#FFFF00; border:1px solid #000000;"><?= number_format($tot_base_admin, 2) ?></td>
        <td class="num bold" style="background-color:#FFFF00; border:1px solid #000000;"><?= number_format($tot_bono_admin, 2) ?></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
        <td class="num bold" style="background-color:#FFFF00; border:1px solid #000000;"><?= number_format($tot_dscto_admin, 2) ?></td>
        <td class="num bold" style="background-color:#FFFF00; border:1px solid #000000;">S/ <?= number_format($tot_pagar_admin, 2) ?></td>
        <td style="background-color:#FFFF00; border:1px solid #000000;"></td>
    </tr>

    <!-- ================= 2. TIENDAS / VENTAS (Azul Completo) ================= -->
    <?php 
    $tot_base_tiendas = 0; $tot_bono_tiendas = 0; $tot_dscto_tiendas = 0; $tot_pagar_tiendas = 0;
    foreach ($detalles_tiendas as $d): 
        if (!$d['incluido']) continue;
        $tot_base_tiendas += $d['base_semanal'];
        $tot_bono_tiendas += $d['bono_comision'];
        $tot_dscto_tiendas += $d['total_descuentos'];
        $tot_pagar_tiendas += $d['total_pagar'];
    ?>
    <tr>
        <td class="center"><?= $num++ ?></td>
        <td class="bold"><?= htmlspecialchars($d['nombre_personal']) ?></td>
        <td class="center"><?= htmlspecialchars($d['area']) ?></td>
        <td class="center"><?= htmlspecialchars($d['cuenta_bancaria']) ?></td>
        <td class="num"><?= number_format($d['sueldo_mensual'], 2) ?></td>
        <td class="num"><?= number_format($d['base_dia'], 2) ?></td>
        <td class="num bold"><?= number_format($d['base_semanal'], 2) ?></td>
        <td class="num"><?= number_format($d['bono_comision'], 2) ?></td>
        <td class="center"><?= $d['horas_extra'] > 0 ? $d['horas_extra'] : '' ?></td>
        <td class="num"><?= number_format($d['pago_hora'], 2) ?></td>
        <td class="num" style="color:red;"><?= $d['descuento_falta'] > 0 ? number_format($d['descuento_falta'], 2) : '' ?></td>
        <td class="num"><?= number_format($d['descuento_prestamo'], 2) ?></td>
        <td class="num"><?= number_format($d['descuento_planilla'], 2) ?></td>
        <td class="num bold" style="color:red;"><?= number_format($d['total_descuentos'], 2) ?></td>
        <td class="num bold" style="background-color:#F2F2F2;">S/ <?= number_format($d['total_pagar'], 2) ?></td>
        <td class="center"><?= htmlspecialchars($d['metodo_pago']) ?></td>
    </tr>
    <?php endforeach; ?>

    <!-- Subtotal Tiendas (100% Azul Celeste de Columna A a P) -->
    <tr>
        <td colspan="4" style="background-color:#00B0F0; font-weight:bold; text-align:center; border:1px solid #000000;">TOTAL PLANILLA TIENDAS</td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td class="num bold" style="background-color:#00B0F0; border:1px solid #000000;"><?= number_format($tot_base_tiendas, 2) ?></td>
        <td class="num bold" style="background-color:#00B0F0; border:1px solid #000000;"><?= number_format($tot_bono_tiendas, 2) ?></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
        <td class="num bold" style="background-color:#00B0F0; border:1px solid #000000;"><?= number_format($tot_dscto_tiendas, 2) ?></td>
        <td class="num bold" style="background-color:#00B0F0; border:1px solid #000000;">S/ <?= number_format($tot_pagar_tiendas, 2) ?></td>
        <td style="background-color:#00B0F0; border:1px solid #000000;"></td>
    </tr>

    <!-- ================= 3. PRODUCCION & EXTERNOS (Naranja Completo) ================= -->
    <?php 
    $tot_base_prod = 0; $tot_bono_prod = 0; $tot_dscto_prod = 0; $tot_pagar_prod = 0;
    foreach ($detalles_prod as $d): 
        if (!$d['incluido']) continue;
        $tot_base_prod += $d['base_semanal'];
        $tot_bono_prod += $d['bono_comision'];
        $tot_dscto_prod += $d['total_descuentos'];
        $tot_pagar_prod += $d['total_pagar'];
    ?>
    <tr>
        <td class="center"><?= $num++ ?></td>
        <td class="bold"><?= htmlspecialchars($d['nombre_personal']) ?></td>
        <td class="center"><?= htmlspecialchars($d['area']) ?></td>
        <td class="center"><?= htmlspecialchars($d['cuenta_bancaria']) ?></td>
        <td class="num"><?= number_format($d['sueldo_mensual'], 2) ?></td>
        <td class="num"><?= number_format($d['base_dia'], 2) ?></td>
        <td class="num bold"><?= number_format($d['base_semanal'], 2) ?></td>
        <td class="num"><?= number_format($d['bono_comision'], 2) ?></td>
        <td class="center"><?= $d['horas_extra'] > 0 ? $d['horas_extra'] : '' ?></td>
        <td class="num"><?= number_format($d['pago_hora'], 2) ?></td>
        <td class="num" style="color:red;"><?= $d['descuento_falta'] > 0 ? number_format($d['descuento_falta'], 2) : '' ?></td>
        <td class="num"><?= number_format($d['descuento_prestamo'], 2) ?></td>
        <td class="num"><?= number_format($d['descuento_planilla'], 2) ?></td>
        <td class="num bold" style="color:red;"><?= number_format($d['total_descuentos'], 2) ?></td>
        <td class="num bold" style="background-color:#F2F2F2;">S/ <?= number_format($d['total_pagar'], 2) ?></td>
        <td class="center"><?= htmlspecialchars($d['metodo_pago']) ?></td>
    </tr>
    <?php endforeach; ?>

    <!-- Subtotal Producción (100% Naranja de Columna A a P) -->
    <tr>
        <td colspan="4" style="background-color:#F4B084; font-weight:bold; text-align:center; border:1px solid #000000;">TOTAL PLANILLA PRODUCCION</td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td class="num bold" style="background-color:#F4B084; border:1px solid #000000;"><?= number_format($tot_base_prod, 2) ?></td>
        <td class="num bold" style="background-color:#F4B084; border:1px solid #000000;"><?= number_format($tot_bono_prod, 2) ?></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
        <td class="num bold" style="background-color:#F4B084; border:1px solid #000000;"><?= number_format($tot_dscto_prod, 2) ?></td>
        <td class="num bold" style="background-color:#F4B084; border:1px solid #000000;">S/ <?= number_format($tot_pagar_prod, 2) ?></td>
        <td style="background-color:#F4B084; border:1px solid #000000;"></td>
    </tr>

    <!-- ================= GRAN TOTAL (Verde Completo) ================= -->
    <?php 
    $gran_base = $tot_base_admin + $tot_base_tiendas + $tot_base_prod;
    $gran_bono = $tot_bono_admin + $tot_bono_tiendas + $tot_bono_prod;
    $gran_dscto = $tot_dscto_admin + $tot_dscto_tiendas + $tot_dscto_prod;
    $gran_pagar = $tot_pagar_admin + $tot_pagar_tiendas + $tot_pagar_prod;
    ?>
    <tr>
        <td colspan="4" style="background-color:#C6EFCE; font-weight:bold; text-align:center; border:2px solid #000000;">TOTAL GENERAL A PAGAR (DESEMBOLSO)</td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td class="num bold" style="background-color:#C6EFCE; border:2px solid #000000;"><?= number_format($gran_base, 2) ?></td>
        <td class="num bold" style="background-color:#C6EFCE; border:2px solid #000000;"><?= number_format($gran_bono, 2) ?></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
        <td class="num bold" style="background-color:#C6EFCE; border:2px solid #000000;"><?= number_format($gran_dscto, 2) ?></td>
        <td class="num bold" style="background-color:#A7F3D0; font-size:11pt; border:2px solid #000000;">S/ <?= number_format($gran_pagar, 2) ?></td>
        <td style="background-color:#C6EFCE; border:2px solid #000000;"></td>
    </tr>

</table>
</body>
</html>
