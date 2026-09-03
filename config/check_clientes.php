<?php
require 'config/db.php';
$cols = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='clientes' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
?>
