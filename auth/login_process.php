<?php
require_once '../config/db.php';

$usuario = trim($_POST['user'] ?? '');
$clave = trim($_POST['pass'] ?? '');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' 
       || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

if (empty($usuario) || empty($clave)) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Por favor, ingrese su usuario y contraseña.']);
        exit;
    }
    header("Location: ../index.php?error=empty_fields");
    exit;
}

// Buscamos al usuario en la base de datos de Carpicenter (por username o email)
$stmt = $db->prepare("
    SELECT u.id, u.username, u.nombre_completo, u.email, u.password, u.estado, u.local_id, u.foto_url, 
           l.nombre as local_nombre, r.nombre as rol_nombre 
    FROM usuarios u
    LEFT JOIN locales l ON u.local_id = l.id
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
    WHERE (LOWER(u.username) = LOWER(:u) OR LOWER(u.email) = LOWER(:u))
    LIMIT 1
");
$stmt->execute(['u' => $usuario]);
$user_found = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_found) {
    if ($user_found['estado'] !== 'Activo') {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Tu cuenta se encuentra inactiva. Contacta al administrador.']);
            exit;
        }
        header("Location: ../index.php?error=inactive_user&user=" . urlencode($usuario));
        exit;
    }

    if ($user_found['password'] === $clave) {
        session_start();
        $_SESSION['user_id'] = $user_found['id'];
        $_SESSION['username'] = $user_found['username'];
        $_SESSION['nombre_completo'] = $user_found['nombre_completo'] ?? $user_found['username'];
        $_SESSION['email'] = $user_found['email'] ?? ($user_found['username'] . '@carpicenter.com');
        $_SESSION['local_id'] = $user_found['local_id'];
        $_SESSION['local_nombre'] = $user_found['local_nombre'];
        $_SESSION['rol_nombre'] = $user_found['rol_nombre'] ?? 'Sin Rol';
        $_SESSION['foto_url'] = $user_found['foto_url'];

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'redirect' => 'views/dashboard.php']);
            exit;
        }

        header("Location: ../views/dashboard.php");
        exit();
    }
}

// Si no coincide la contraseña o no se encuentra el usuario
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos. Verifica tus datos.']);
    exit;
}

header("Location: ../index.php?error=invalid_credentials&user=" . urlencode($usuario));
exit;