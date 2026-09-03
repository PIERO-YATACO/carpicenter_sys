<?php
require_once __DIR__ . '/../auth/check_auth.php';
$page_title = 'Roles y Permisos'; $page_subtitle = 'Gestión de roles y permisos del sistema'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles y Permisos - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header">
                <div><h2>Roles y Permisos</h2><p>Configura los niveles de acceso</p></div>
                <button class="btn btn-success"><i class="fas fa-plus"></i> Nuevo rol</button>
            </div>
            <div class="grid-2">
                <div class="card-panel">
                    <div class="card-header"><h3>Roles del sistema</h3></div>
                    <div class="card-body-custom" style="padding:0;">
                        <table class="table-custom">
                            <thead><tr><th>Rol</th><th>Usuarios</th><th>Descripción</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <tr><td><span class="badge badge-danger">Super Admin</span></td><td>1</td><td>Acceso total al sistema</td><td><button class="btn-icon"><i class="fas fa-eye"></i></button></td></tr>
                                <tr><td><span class="badge badge-info">Vendedor</span></td><td>2</td><td>Gestión de ventas y clientes</td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                                <tr><td><span class="badge badge-warning">Almacén</span></td><td>1</td><td>Gestión de inventario y kardex</td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                                <tr><td><span class="badge badge-success">Producción</span></td><td>3</td><td>Control de producción</td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-panel">
                    <div class="card-header"><h3>Permisos del rol: <span style="color:var(--red-light);">Vendedor</span></h3></div>
                    <div class="card-body-custom">
                        <?php
                        $modules = ['Dashboard','Productos','Materiales','Producción','Ventas','Compras','Kardex','Proveedores','Clientes','Reportes','Usuarios','Configuración'];
                        $perms = [1,1,0,0,1,0,0,0,1,1,0,0]; // 1=active
                        foreach($modules as $i => $m): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid var(--border-color);">
                            <span style="font-size:0.88rem;"><?= $m ?></span>
                            <div style="display:flex;gap:0.8rem;">
                                <label style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:0.3rem;"><input type="checkbox" <?= $perms[$i]?'checked':'' ?> style="accent-color:var(--red);"> Ver</label>
                                <label style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:0.3rem;"><input type="checkbox" <?= $perms[$i]&&$i<9?'checked':'' ?> style="accent-color:var(--red);"> Editar</label>
                                <label style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:0.3rem;"><input type="checkbox" style="accent-color:var(--red);"> Eliminar</label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div style="margin-top:1rem;text-align:right;">
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar permisos</button>
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
