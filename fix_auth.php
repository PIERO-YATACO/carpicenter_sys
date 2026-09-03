<?php
$viewsDir = __DIR__ . '/views/';
$files = glob($viewsDir . '*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'check_auth.php') === false) {
        $content = preg_replace('/<\?php\s*/', "<?php\nrequire_once __DIR__ . '/../auth/check_auth.php';\n", $content, 1);
        file_put_contents($file, $content);
    }
}

$cotizacionesDir = __DIR__ . '/modules/cotizaciones/';
$cotFiles = glob($cotizacionesDir . '*.php');
foreach ($cotFiles as $file) {
    if (strpos($file, 'model') !== false || strpos($file, 'controller') !== false) continue;
    $content = file_get_contents($file);
    if (strpos($content, 'check_auth.php') === false) {
        $content = preg_replace('/<\?php\s*/', "<?php\nrequire_once __DIR__ . '/../../auth/check_auth.php';\n", $content, 1);
        file_put_contents($file, $content);
    }
}

echo "Auth fix applied.\n";
