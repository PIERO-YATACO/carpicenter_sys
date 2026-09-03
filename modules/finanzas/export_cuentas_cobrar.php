<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql_base = "FROM finanzas_cuentas_cobrar WHERE 1=1";
$params = [];

if ($filtro_estado) {
    $sql_base .= " AND estado = ?";
    $params[] = $filtro_estado;
}
if ($search) {
    $sql_base .= " AND (cliente ILIKE ? OR ft_lt ILIKE ? OR referencia ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql_data = "SELECT * " . $sql_base . " ORDER BY f_venc ASC NULLS LAST, id DESC";
$stmt = $db->prepare($sql_data);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Cuentas_Por_Cobrar_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<meta charset=\"utf-8\">";
echo "<table border='1'>";
echo "<tr>";
echo "<th style='background-color:#f2f2f2;'>COMPROBANTE / REF</th>";
echo "<th style='background-color:#f2f2f2;'>CLIENTE</th>";
echo "<th style='background-color:#f2f2f2;'>F. VENCIMIENTO</th>";
echo "<th style='background-color:#f2f2f2;'>MONTO TOTAL</th>";
echo "<th style='background-color:#f2f2f2;'>MONTO PAGADO</th>";
echo "<th style='background-color:#f2f2f2;'>SALDO</th>";
echo "<th style='background-color:#f2f2f2;'>BANCO</th>";
echo "<th style='background-color:#f2f2f2;'>F. PAGO</th>";
echo "<th style='background-color:#f2f2f2;'>ESTADO</th>";
echo "</tr>";

foreach ($items as $row) {
    $saldo = $row['monto_total'] - $row['monto_pagado'];
    $comprobante = $row['ft_lt'] ?? 'S/N';
    if ($row['referencia']) {
        $comprobante .= " / " . $row['referencia'];
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($comprobante) . "</td>";
    echo "<td>" . htmlspecialchars($row['cliente']) . "</td>";
    echo "<td>" . ($row['f_venc'] ? date('d/m/Y', strtotime($row['f_venc'])) : '') . "</td>";
    echo "<td>" . number_format($row['monto_total'], 2, '.', '') . "</td>";
    echo "<td>" . number_format($row['monto_pagado'], 2, '.', '') . "</td>";
    echo "<td>" . number_format($saldo, 2, '.', '') . "</td>";
    echo "<td>" . htmlspecialchars($row['banco'] ?? 'EFECTIVO') . "</td>";
    echo "<td>" . ($row['fecha_pago'] ? date('d/m/Y', strtotime($row['fecha_pago'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($row['estado']) . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
