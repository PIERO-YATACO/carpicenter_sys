<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';
$s = $db->query("SELECT * FROM producto_colores WHERE producto_id=9");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
?>
