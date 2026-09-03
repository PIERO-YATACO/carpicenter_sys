<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$stmt = $db->query("UPDATE cotizaciones SET estado = 'Aceptada', firma_digital = 'data:image/png;base64,fakefirma' WHERE cliente_nombre ILIKE '%EUROMERICA%'");
echo "Simulated signature for EUROMERICA\n";
