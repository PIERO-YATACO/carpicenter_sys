<?php
require_once __DIR__ . '/db.php';
try {
    $stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'producto_colores'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
