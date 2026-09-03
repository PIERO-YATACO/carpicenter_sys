<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$stmt = $db->prepare("UPDATE cotizaciones SET estado = 'Pendiente', firma_token = NULL, firma_digital = NULL WHERE cliente_nombre ILIKE '%EUROMERICA%'");
$stmt->execute();
echo 'Updated ' . $stmt->rowCount() . ' rows.';
