<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$action = $_POST['action'] ?? '';

if ($action === 'verify') {
    $identifier = trim($_POST['identifier'] ?? '');
    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, ingrese su usuario o correo electrónico.']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT u.id, u.username, u.nombre_completo, u.email, u.estado, r.nombre as rol_nombre
        FROM usuarios u
        LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
        LEFT JOIN roles r ON ur.rol_id = r.id
        WHERE (LOWER(u.username) = LOWER(:id) OR LOWER(u.email) = LOWER(:id))
        LIMIT 1
    ");
    $stmt->execute(['id' => $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No encontramos ninguna cuenta asociada a este usuario o correo.']);
        exit;
    }

    if ($user['estado'] !== 'Activo') {
        echo json_encode(['success' => false, 'message' => 'Esta cuenta se encuentra inactiva. Contacte al administrador.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user_id' => $user['id'],
        'username' => $user['username'],
        'nombre' => $user['nombre_completo'] ?: $user['username'],
        'rol' => $user['rol_nombre'] ?? 'Personal',
        'email_masked' => maskEmail($user['email'] ?? ($user['username'] . '@carpicenter.com'))
    ]);
    exit;
}

if ($action === 'reset_password') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');
    $master_pin = trim($_POST['master_pin'] ?? '');

    if ($user_id <= 0 || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos para restablecer la contraseña.']);
        exit;
    }

    if (strlen($new_password) < 4) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 4 caracteres.']);
        exit;
    }

    // Verificar PIN maestro del sistema o autorización
    $validPins = ['CARPICENTER2026', '2026', 'ADMIN2026', 'CARPI2026'];
    if (!in_array(strtoupper($master_pin), $validPins) && !empty($master_pin)) {
        echo json_encode(['success' => false, 'message' => 'El código de autorización / PIN de seguridad es incorrecto.']);
        exit;
    }

    // Actualizar contraseña
    $stmtUpdate = $db->prepare("UPDATE usuarios SET password = :p WHERE id = :id");
    $res = $stmtUpdate->execute([
        'p' => $new_password,
        'id' => $user_id
    ]);

    if ($res) {
        echo json_encode([
            'success' => true,
            'message' => '¡Tu contraseña ha sido actualizada con éxito! Ya puedes iniciar sesión.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ocurrió un error al actualizar la contraseña en la base de datos.']);
    }
    exit;
}

function maskEmail($email) {
    if (empty($email) || strpos($email, '@') === false) return 'c***@carpicenter.com';
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    $maskedName = substr($name, 0, 2) . str_repeat('*', max(3, strlen($name) - 2));
    return $maskedName . '@' . $domain;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
