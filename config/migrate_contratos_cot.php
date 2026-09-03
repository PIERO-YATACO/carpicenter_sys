<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$db->exec("ALTER TABLE contratos ADD COLUMN IF NOT EXISTS cotizacion_id INTEGER");
echo 'Success';
