<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// GET: single user data for edit modal
if ($action === 'get') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.nombre_completo, u.email, u.estado, u.local_id, ur.rol_id 
        FROM usuarios u 
        LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id 
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($user ?: []);
    exit;
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    // DELETE Action
    if ($action === 'delete') {
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            exit;
        }

        // Prevent self-deletion
        if ((int)$id === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta de usuario.']);
            exit;
        }

        try {
            $db->beginTransaction();

            // 1. Delete roles association
            $stmtDelRoles = $db->prepare("DELETE FROM usuario_roles WHERE usuario_id = :id");
            $stmtDelRoles->execute([':id' => $id]);

            // 2. Delete user
            $stmtDelUser = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmtDelUser->execute([':id' => $id]);

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario: ' . $e->getMessage()]);
        }
        exit;
    }

    // CREATE / UPDATE Actions
    $username = trim($_POST['username'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol_id = $_POST['rol_id'] ?? null;
    $local_id = $_POST['local_id'] ?? null;
    $estado = $_POST['estado'] ?? 'Activo';

    if (empty($username) || empty($nombre_completo) || empty($email) || !$rol_id) {
        echo json_encode(['success' => false, 'message' => 'Los campos de Nombre de Usuario, Nombre Completo, Email y Rol son obligatorios.']);
        exit;
    }

    if ($action === 'create' && empty($password)) {
        echo json_encode(['success' => false, 'message' => 'La contraseña es obligatoria para nuevos usuarios.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Check for duplicates
        if ($action === 'create') {
            $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE username = :u");
            $stmtCheck->execute([':u' => $username]);
        } else {
            $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE username = :u AND id != :id");
            $stmtCheck->execute([':u' => $username, ':id' => $id]);
        }

        if ($stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso.']);
            $db->rollBack();
            exit;
        }

        // 2. Database Writes
        if ($action === 'create') {
            // INSERT User
            $stmtInsert = $db->prepare("
                INSERT INTO usuarios (username, password, estado, local_id, nombre_completo, email) 
                VALUES (:username, :password, :estado, :local_id, :nombre_completo, :email)
            ");
            $stmtInsert->execute([
                ':username' => $username,
                ':password' => $password, // Direct password string matching current schema rules
                ':estado' => $estado,
                ':local_id' => !empty($local_id) ? $local_id : null,
                ':nombre_completo' => $nombre_completo,
                ':email' => $email
            ]);
            $new_user_id = $db->lastInsertId();

            // INSERT Rol
            $stmtRol = $db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (:uid, :rid)");
            $stmtRol->execute([
                ':uid' => $new_user_id,
                ':rid' => $rol_id
            ]);

            $db->commit();
            echo json_encode(['success' => true]);
        } else {
            // UPDATE User
            if (!empty($password)) {
                // If password is changed
                $stmtUpdate = $db->prepare("
                    UPDATE usuarios 
                    SET username = :username, password = :password, estado = :estado, 
                        local_id = :local_id, nombre_completo = :nombre_completo, email = :email 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':username' => $username,
                    ':password' => $password,
                    ':estado' => $estado,
                    ':local_id' => !empty($local_id) ? $local_id : null,
                    ':nombre_completo' => $nombre_completo,
                    ':email' => $email,
                    ':id' => $id
                ]);
            } else {
                // Keep password unchanged
                $stmtUpdate = $db->prepare("
                    UPDATE usuarios 
                    SET username = :username, estado = :estado, 
                        local_id = :local_id, nombre_completo = :nombre_completo, email = :email 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':username' => $username,
                    ':estado' => $estado,
                    ':local_id' => !empty($local_id) ? $local_id : null,
                    ':nombre_completo' => $nombre_completo,
                    ':email' => $email,
                    ':id' => $id
                ]);
            }

            // Check if user has active session and reload basic session details dynamically
            if ((int)$id === (int)$_SESSION['user_id']) {
                $_SESSION['username'] = $username;
                $_SESSION['nombre_completo'] = $nombre_completo;
                $_SESSION['email'] = $email;
                if (!empty($local_id)) {
                    $_SESSION['local_id'] = $local_id;
                    // fetch local name
                    $stmtLocName = $db->prepare("SELECT nombre FROM locales WHERE id = :lid");
                    $stmtLocName->execute([':lid' => $local_id]);
                    $_SESSION['local_nombre'] = $stmtLocName->fetchColumn() ?: 'Sin Local';
                }
                // fetch rol name
                $stmtRolName = $db->prepare("SELECT nombre FROM roles WHERE id = :rid");
                $stmtRolName->execute([':rid' => $rol_id]);
                $_SESSION['rol_nombre'] = $stmtRolName->fetchColumn() ?: 'Sin Rol';
            }

            // UPDATE Rol
            $stmtDelRol = $db->prepare("DELETE FROM usuario_roles WHERE usuario_id = :uid");
            $stmtDelRol->execute([':uid' => $id]);

            $stmtAddRol = $db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (:uid, :rid)");
            $stmtAddRol->execute([
                ':uid' => $id,
                ':rid' => $rol_id
            ]);

            $db->commit();
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al guardar cambios: ' . $e->getMessage()]);
    }
}
