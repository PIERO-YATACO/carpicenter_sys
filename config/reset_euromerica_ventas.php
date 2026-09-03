<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$stmt = $db->query("SELECT id FROM cotizaciones WHERE cliente_nombre ILIKE '%EUROMERICA%'");
$cotizaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($cotizaciones as $id) {
    // Check if venta exists
    $stmtV = $db->prepare("SELECT id FROM ventas WHERE cotizacion_id = ?");
    $stmtV->execute([$id]);
    $ventas = $stmtV->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($ventas as $vid) {
        $db->exec("DELETE FROM ventas WHERE id = $vid");
        echo "Deleted venta $vid associated to cotizacion $id\n";
    }
}
echo "Done.";
