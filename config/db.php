<?php
date_default_timezone_set('America/Lima');

$host = "localhost";
$port = "5432";
$dbname = "carpicenter_db";
$user = "postgres";
$password = "041003"; // Tu clave confirmada

try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

if (!function_exists('formatearMonto')) {
    /**
     * Formatea montos de dinero de forma inteligente:
     * Si no tiene céntimos (ej: 6612.00) -> muestra S/ 6,612 (limpio y sin ceros de más)
     * Si tiene céntimos (ej: 45.50) -> muestra S/ 45.50
     */
    function formatearMonto($monto, $incluirSimbolo = true) {
        $num = floatval($monto);
        $prefijo = $incluirSimbolo ? 'S/ ' : '';
        if (abs($num - round($num)) < 0.0001) {
            return $prefijo . number_format($num, 0, '.', ',');
        } else {
            return $prefijo . number_format($num, 2, '.', ',');
        }
    }
}
?>