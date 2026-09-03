<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '%cotiza%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
