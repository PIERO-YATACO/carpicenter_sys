<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$stmt = $db->query("SELECT estado, firma_digital FROM cotizaciones WHERE cliente_nombre ILIKE '%EUROMERICA%'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Estado actual: " . $row['estado'] . "\n";
echo "Tiene firma: " . (!empty($row['firma_digital']) ? "SI" : "NO") . "\n";
