<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle POST request to update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_name = $_FILES['avatar']['name'];
            $file_size = $_FILES['avatar']['size'];
            $file_type = $_FILES['avatar']['type'];
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_exts)) {
                $error_msg = "Solo se permiten imágenes JPG, JPEG, PNG y GIF.";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error_msg = "El tamaño de la imagen no debe superar los 2MB.";
            } else {
                // Asegurar existencia del directorio
                $upload_dir = __DIR__ . '/../assets/img/avatars';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Crear nombre único para evitar almacenamiento en caché
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . '/' . $new_filename;
                
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $web_path = '/carpicenter_sys/assets/img/avatars/' . $new_filename;
                    
                    try {
                        // Eliminar foto antigua si existe
                        $stmtOld = $db->prepare("SELECT foto_url FROM usuarios WHERE id = :id");
                        $stmtOld->execute(['id' => $user_id]);
                        $old_foto = $stmtOld->fetchColumn();
                        if ($old_foto) {
                            $old_file_path = __DIR__ . '/..' . str_replace('/carpicenter_sys', '', $old_foto);
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                        
                        // Actualizar base de datos
                        $stmtPhoto = $db->prepare("UPDATE usuarios SET foto_url = :url WHERE id = :id");
                        $stmtPhoto->execute(['url' => $web_path, 'id' => $user_id]);
                        
                        // Actualizar variable de sesión
                        $_SESSION['foto_url'] = $web_path;
                        $success_msg = "¡Foto de perfil actualizada con éxito!";
                    } catch (PDOException $e) {
                        $error_msg = "Error al actualizar foto en BD: " . $e->getMessage();
                    }
                } else {
                    $error_msg = "Error al guardar la imagen en el servidor.";
                }
            }
        } else {
            $error_msg = "Error al procesar el archivo. Intenta con otra imagen.";
        }
    } elseif ($action === 'update_profile') {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($nombre_completo) || empty($email) || empty($username)) {
            $error_msg = "Todos los campos de información personal son obligatorios.";
        } else {
            try {
                // Check if username is already taken by someone else
                $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE username = :u AND id != :id");
                $stmtCheck->execute(['u' => $username, 'id' => $user_id]);
                if ($stmtCheck->fetch()) {
                    $error_msg = "El nombre de usuario ya está en uso por otra persona.";
                } else {
                    // Update user basic profile
                    $stmtUpdate = $db->prepare("UPDATE usuarios SET nombre_completo = :nc, email = :e, username = :u WHERE id = :id");
                    $stmtUpdate->execute([
                        'nc' => $nombre_completo,
                        'e' => $email,
                        'u' => $username,
                        'id' => $user_id
                    ]);

                    // Update session variables dynamically
                    $_SESSION['username'] = $username;
                    $_SESSION['nombre_completo'] = $nombre_completo;
                    $_SESSION['email'] = $email;

                    $success_msg = "¡Perfil actualizado con éxito!";
                }
            } catch (PDOException $e) {
                $error_msg = "Error al actualizar perfil: " . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_pass = $_POST['current_pass'] ?? '';
        $new_pass = $_POST['new_pass'] ?? '';
        $confirm_pass = $_POST['confirm_pass'] ?? '';

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $error_msg = "Todos los campos de contraseña son obligatorios.";
        } elseif ($new_pass !== $confirm_pass) {
            $error_msg = "La nueva contraseña y la confirmación no coinciden.";
        } else {
            try {
                // Verify current password first
                $stmtVerify = $db->prepare("SELECT password FROM usuarios WHERE id = :id");
                $stmtVerify->execute(['id' => $user_id]);
                $user_db = $stmtVerify->fetch(PDO::FETCH_ASSOC);

                if ($user_db['password'] !== $current_pass) {
                    $error_msg = "La contraseña actual es incorrecta.";
                } else {
                    // Update password
                    $stmtPass = $db->prepare("UPDATE usuarios SET password = :p WHERE id = :id");
                    $stmtPass->execute(['p' => $new_pass, 'id' => $user_id]);
                    $success_msg = "¡Contraseña cambiada con éxito!";
                }
            } catch (PDOException $e) {
                $error_msg = "Error al cambiar contraseña: " . $e->getMessage();
            }
        }
    }
}

// Fetch the most updated user information from database
$stmtUser = $db->prepare("
    SELECT u.id, u.username, u.nombre_completo, u.email, u.estado, u.foto_url, l.nombre as local_nombre, r.nombre as rol_nombre
    FROM usuarios u
    LEFT JOIN locales l ON u.local_id = l.id
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
    WHERE u.id = :id
");
$stmtUser->execute(['id' => $user_id]);
$user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);

$page_title = 'Mi Perfil';
$page_subtitle = 'Gestiona tus datos personales y contraseña';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <script src="/carpicenter_sys/assets/js/theme.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        .profile-header-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            animation: fadeUp 0.5s ease;
        }
        .profile-large-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(198, 40, 40, 0.4);
        }
        .profile-meta h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .profile-meta p {
            margin: 0.2rem 0 0 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .profile-meta .badge {
            margin-top: 0.6rem;
        }
        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            animation: fadeUp 0.3s ease;
        }
        .alert-success {
            background: rgba(46, 125, 50, 0.15);
            border: 1px solid rgba(46, 125, 50, 0.3);
            color: #66BB6A;
        }
        .alert-danger {
            background: rgba(198, 40, 40, 0.15);
            border: 1px solid rgba(198, 40, 40, 0.3);
            color: var(--primary-light);
        }
        .form-readonly {
            background: var(--bg-primary);
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">
            
            <div class="profile-container">
                
                <!-- Notification Alert -->
                <?php if (!empty($success_msg)): ?>
                    <div class="alert-custom alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success_msg) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert-custom alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error_msg) ?></span>
                    </div>
                <?php endif; ?>

                <!-- User Profile Header Card -->
                <div class="profile-header-card" style="position: relative;">
                    <div style="position: relative; display: inline-block;">
                        <?php if(!empty($user_data['foto_url'])): ?>
                            <img src="<?= htmlspecialchars($user_data['foto_url']) ?>" alt="Avatar" class="profile-large-avatar" style="object-fit: cover; border: 2px solid var(--border-color); box-shadow: 0 4px 15px rgba(198, 40, 40, 0.25);">
                        <?php else: ?>
                            <div class="profile-large-avatar"><?= strtoupper(substr($user_data['nombre_completo'] ?? $user_data['username'] ?? 'A', 0, 1)) ?></div>
                        <?php endif; ?>
                        
                        <!-- Botón superpuesto para subir foto -->
                        <label for="avatar_file" style="position: absolute; bottom: 0; right: 0; background: var(--primary); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--bg-card); cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.3);" title="Subir Foto de Perfil">
                            <i class="fas fa-camera" style="font-size: 0.85rem; color: #fff;"></i>
                        </label>
                    </div>
                    
                    <!-- Formulario Oculto de Carga Directa -->
                    <form id="avatarForm" action="perfil.php" method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="hidden" name="action" value="update_avatar">
                        <input type="file" id="avatar_file" name="avatar" accept="image/*" onchange="document.getElementById('avatarForm').submit();">
                    </form>

                    <div class="profile-meta">
                        <h2><?= htmlspecialchars($user_data['nombre_completo'] ?? 'Sin Nombre') ?></h2>
                        <p><i class="far fa-envelope" style="margin-right: 0.4rem; color: var(--primary-light);"></i> <?= htmlspecialchars($user_data['email'] ?? 'sin_correo@carpicenter.com') ?></p>
                        <div>
                            <span class="badge badge-danger"><?= htmlspecialchars($user_data['rol_nombre'] ?? 'Sin Rol') ?></span>
                            <span class="badge badge-info" style="margin-left: 0.3rem;"><i class="fas fa-store" style="margin-right: 0.3rem;"></i><?= htmlspecialchars($user_data['local_nombre'] ?? 'Sin Local') ?></span>
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    
                    <!-- General Personal Information Card -->
                    <div class="card-panel">
                        <div class="card-header">
                            <h3><i class="fas fa-user-edit" style="color:var(--primary);margin-right:0.5rem;"></i>Información Personal</h3>
                        </div>
                        <div class="card-body-custom">
                            <form action="perfil.php" method="POST" autocomplete="off">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-group">
                                    <label for="nombre_completo">Nombre Completo <span style="color:red">*</span></label>
                                    <input type="text" id="nombre_completo" name="nombre_completo" class="form-control" 
                                           value="<?= htmlspecialchars($user_data['nombre_completo'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="email">Correo Electrónico <span style="color:red">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="username">Nombre de Usuario <span style="color:red">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control" 
                                           value="<?= htmlspecialchars($user_data['username'] ?? '') ?>" required>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Rol Asignado (Solo Lectura)</label>
                                        <input type="text" class="form-control form-readonly" value="<?= htmlspecialchars($user_data['rol_nombre'] ?? 'Sin Rol') ?>" readonly disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>Local (Solo Lectura)</label>
                                        <input type="text" class="form-control form-readonly" value="<?= htmlspecialchars($user_data['local_nombre'] ?? 'Sin Local') ?>" readonly disabled>
                                    </div>
                                </div>

                                <div style="text-align: right; margin-top: 1rem;">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security & Password Change Card -->
                    <div class="card-panel">
                        <div class="card-header">
                            <h3><i class="fas fa-key" style="color:var(--primary);margin-right:0.5rem;"></i>Seguridad y Contraseña</h3>
                        </div>
                        <div class="card-body-custom">
                            <form action="perfil.php" method="POST" autocomplete="off">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group">
                                    <label for="current_pass">Contraseña Actual <span style="color:red">*</span></label>
                                    <input type="password" id="current_pass" name="current_pass" class="form-control" 
                                           placeholder="Ingresa tu contraseña actual" required>
                                </div>

                                <div class="form-group">
                                    <label for="new_pass">Nueva Contraseña <span style="color:red">*</span></label>
                                    <input type="password" id="new_pass" name="new_pass" class="form-control" 
                                           placeholder="Mínimo 6 caracteres" minlength="4" required>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_pass">Confirmar Nueva Contraseña <span style="color:red">*</span></label>
                                    <input type="password" id="confirm_pass" name="confirm_pass" class="form-control" 
                                           placeholder="Repite la nueva contraseña" minlength="4" required>
                                </div>

                                <div style="text-align: right; margin-top: 2rem;">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-shield-alt"></i> Actualizar Contraseña</button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>
</body>
</html>
