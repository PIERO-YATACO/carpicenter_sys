<?php
// Proxy seguro para consultar RENIEC (DNI) y SUNAT (RUC)
// Usa la API gratuita de apis.net.pe
header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? '';
$numero = preg_replace('/\D/', '', $_GET['numero'] ?? '');

if (!$numero) {
    echo json_encode(['success' => false, 'message' => 'Número requerido']);
    exit;
}

// Token gratuito de apis.net.pe (demo - funciona para pruebas)
$token = 'apis-token-demo'; // Reemplazar con token real de https://apis.net.pe

if ($tipo === 'DNI' && strlen($numero) === 8) {
    $url = "https://api.apis.net.pe/v2/reniec/dni?numero=$numero";
} elseif ($tipo === 'RUC' && strlen($numero) === 11) {
    $url = "https://api.apis.net.pe/v2/sunat/ruc?numero=$numero";
} else {
    echo json_encode(['success' => false, 'message' => 'Formato de documento inválido']);
    exit;
}

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $token\r\nAccept: application/json\r\n",
        'timeout' => 8
    ],
    'ssl' => ['verify_peer' => false]
]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar con el servicio de consulta']);
    exit;
}

$data = json_decode($response, true);

if ($tipo === 'DNI') {
    if (isset($data['nombres'])) {
        $nombre = trim($data['apellidoPaterno'] . ' ' . $data['apellidoMaterno'] . ' ' . $data['nombres']);
        echo json_encode(['success' => true, 'nombre' => $nombre, 'tipo' => 'DNI']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DNI no encontrado en RENIEC']);
    }
} else {
    if (isset($data['razonSocial'])) {
        echo json_encode([
            'success' => true,
            'nombre' => $data['razonSocial'],
            'razon_social' => $data['razonSocial'],
            'direccion' => $data['direccion'] ?? '',
            'tipo' => 'RUC'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'RUC no encontrado en SUNAT']);
    }
}
