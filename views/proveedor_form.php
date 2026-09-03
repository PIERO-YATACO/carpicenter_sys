<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once '../config/db.php';

$mensaje = '';
$tipo_mensaje = '';

$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$proveedor_edit = null;

if($edit_id) {
    $stmt = $db->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->execute([$edit_id]);
    $proveedor_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $ruc = $_POST['ruc'] ?? '';
    $rubro = $_POST['rubro'] ?? '';
    $contacto = $_POST['contacto'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $ciudad = $_POST['ciudad'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $estado = $_POST['estado'] ?? 'Activo';
    $observaciones = $_POST['observaciones'] ?? '';
    
    $banco = $_POST['banco'] ?? '';
    $numero_cuenta = $_POST['numero_cuenta'] ?? '';
    $cci = $_POST['cci'] ?? '';
    $tipo_cuenta = $_POST['tipo_cuenta'] ?? '';
    
    if(!empty($nombre)) {
        try {
            if(!empty($id_post)) {
                $sql = "UPDATE proveedores SET 
                    nombre=?, ruc=?, rubro=?, contacto=?, telefono=?, email=?, ciudad=?, direccion=?, estado=?, observaciones=?, 
                    banco=?, numero_cuenta=?, cci=?, tipo_cuenta=? WHERE id=?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$nombre, $ruc, $rubro, $contacto, $telefono, $email, $ciudad, $direccion, $estado, $observaciones, $banco, $numero_cuenta, $cci, $tipo_cuenta, $id_post]);
            } else {
                $sql = "INSERT INTO proveedores 
                    (nombre, ruc, rubro, contacto, telefono, email, ciudad, direccion, estado, observaciones, banco, numero_cuenta, cci, tipo_cuenta) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$nombre, $ruc, $rubro, $contacto, $telefono, $email, $ciudad, $direccion, $estado, $observaciones, $banco, $numero_cuenta, $cci, $tipo_cuenta]);
            }
            header("Location: proveedores.php?success=1");
            exit;
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "La Razón Social (Nombre) es obligatoria.";
        $tipo_mensaje = "warning";
    }
}

$page_title = $proveedor_edit ? 'Editar Proveedor' : 'Nuevo Proveedor'; 
$page_subtitle = $proveedor_edit ? 'Modifica los datos del proveedor' : 'Registra un nuevo proveedor en el sistema'; 

$val_nombre = $proveedor_edit['nombre'] ?? '';
$val_ruc = $proveedor_edit['ruc'] ?? '';
$val_rubro = $proveedor_edit['rubro'] ?? '';
$val_contacto = $proveedor_edit['contacto'] ?? '';
$val_telefono = $proveedor_edit['telefono'] ?? '';
$val_email = $proveedor_edit['email'] ?? '';
$val_ciudad = $proveedor_edit['ciudad'] ?? '';
$val_direccion = $proveedor_edit['direccion'] ?? '';
$val_estado = $proveedor_edit['estado'] ?? 'Activo';
$val_observaciones = $proveedor_edit['observaciones'] ?? '';

$val_banco = $proveedor_edit['banco'] ?? '';
$val_ncuenta = $proveedor_edit['numero_cuenta'] ?? '';
$val_cci = $proveedor_edit['cci'] ?? '';
$val_tcuenta = $proveedor_edit['tipo_cuenta'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .alert-warning { background-color: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header">
                <div><h2><?php echo $page_title; ?></h2><p><?php echo $page_subtitle; ?></p></div>
                <a href="proveedores.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
            
            <?php if(!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <form method="POST" action="proveedor_form.php">
                <?php if($proveedor_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $proveedor_edit['id']; ?>">
                <?php endif; ?>
                
                <div class="grid-2">
                    <div class="card-panel">
                        <div class="card-header"><h3>Información del proveedor</h3></div>
                        <div class="card-body-custom">
                            <div class="form-group">
                                <label>Razón Social / Nombre *</label>
                                <input type="text" name="nombre" class="form-control" placeholder="Razón social" value="<?php echo htmlspecialchars($val_nombre); ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>RUC</label>
                                    <input type="text" name="ruc" class="form-control" placeholder="20xxxxxxxxx" value="<?php echo htmlspecialchars($val_ruc); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Categoría / Rubro</label>
                                    <input type="text" name="rubro" class="form-control" placeholder="Ej: Melamina, Herrajes" value="<?php echo htmlspecialchars($val_rubro); ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Contacto principal</label>
                                    <input type="text" name="contacto" class="form-control" placeholder="Nombre del contacto" value="<?php echo htmlspecialchars($val_contacto); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono / Celular</label>
                                    <input type="text" name="telefono" class="form-control" placeholder="999 999 999" value="<?php echo htmlspecialchars($val_telefono); ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="correo@empresa.com" value="<?php echo htmlspecialchars($val_email); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Estado</label>
                                    <select name="estado" class="form-control">
                                        <option value="Activo" <?php echo ($val_estado == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                        <option value="Inactivo" <?php echo ($val_estado == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Ciudad</label>
                                    <input type="text" name="ciudad" class="form-control" placeholder="Ej: Lima" value="<?php echo htmlspecialchars($val_ciudad); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" name="direccion" class="form-control" placeholder="Dirección completa" value="<?php echo htmlspecialchars($val_direccion); ?>">
                            </div>
                            <div class="form-group">
                                <label>Observaciones</label>
                                <textarea name="observaciones" class="form-control" placeholder="Notas adicionales..."><?php echo htmlspecialchars($val_observaciones); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="card-panel">
                            <div class="card-header"><h3>Datos bancarios</h3></div>
                            <div class="card-body-custom">
                                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:15px;">Completa estos datos para agilizar los pagos y transferencias a este proveedor.</p>
                                <div class="form-group">
                                    <label>Banco</label>
                                    <input type="text" name="banco" class="form-control" placeholder="Ej: BCP, BBVA, Interbank" value="<?php echo htmlspecialchars($val_banco); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Tipo de cuenta</label>
                                    <select name="tipo_cuenta" class="form-control">
                                        <option value="Corriente" <?php echo ($val_tcuenta == 'Corriente') ? 'selected' : ''; ?>>Corriente</option>
                                        <option value="Ahorros" <?php echo ($val_tcuenta == 'Ahorros') ? 'selected' : ''; ?>>Ahorros</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Número de cuenta</label>
                                    <input type="text" name="numero_cuenta" class="form-control" placeholder="Nro de cuenta" value="<?php echo htmlspecialchars($val_ncuenta); ?>">
                                </div>
                                <div class="form-group">
                                    <label>CCI (Código de Cuenta Interbancario)</label>
                                    <input type="text" name="cci" class="form-control" placeholder="000-000-000000000000-00" value="<?php echo htmlspecialchars($val_cci); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:0.8rem;justify-content:flex-end;margin-top:1.5rem;">
                    <a href="proveedores.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar proveedor</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>
</body>
</html>
