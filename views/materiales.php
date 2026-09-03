<?php
require_once __DIR__ . '/../auth/check_auth.php';
$page_title = 'Materiales'; $page_subtitle = 'Gestión de materias primas'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiales - Carpicenter</title>
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
                <div><h2>Materiales</h2><p>Gestión de materias primas</p></div>
                <button class="btn btn-success"><i class="fas fa-plus"></i> Nuevo material</button>
            </div>
            <div class="filter-bar">
                <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Buscar material..."></div>
                <select class="form-control"><option>Todas las categorías</option><option>Madera</option><option>Acabados</option><option>Ferretería</option><option>Tapicería</option></select>
            </div>
            <div class="card-panel">
                <div class="card-body-custom" style="padding:0;">
                    <table class="table-custom">
                        <thead><tr><th>Código</th><th>Material</th><th>Categoría</th><th>Unidad</th><th>Stock</th><th>Precio</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <tr><td>MAT-001</td><td><strong>Madera Pino</strong></td><td>Madera</td><td>m²</td><td>45.5</td><td>S/ 24.80</td><td><span class="badge badge-success">Disponible</span></td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td>MAT-002</td><td><strong>Madera Roble</strong></td><td>Madera</td><td>m²</td><td>22.0</td><td>S/ 45.00</td><td><span class="badge badge-success">Disponible</span></td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td>MAT-003</td><td><strong>Tinte Nogal</strong></td><td>Acabados</td><td>L</td><td>3.5</td><td>S/ 48.00</td><td><span class="badge badge-danger">Bajo</span></td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td>MAT-004</td><td><strong>Tornillos 2"</strong></td><td>Ferretería</td><td>kg</td><td>8.2</td><td>S/ 12.00</td><td><span class="badge badge-success">Disponible</span></td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td>MAT-005</td><td><strong>Espuma HD 2"</strong></td><td>Tapicería</td><td>m²</td><td>15.0</td><td>S/ 35.00</td><td><span class="badge badge-success">Disponible</span></td><td><button class="btn-icon"><i class="fas fa-edit"></i></button> <button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;">
                <span style="font-size:0.8rem;color:var(--text-muted);">Mostrando 1 a 5 de 42 materiales</span>
                <div class="pagination"><a href="#">&laquo;</a><a href="#" class="active">1</a><a href="#">2</a><a href="#">3</a><a href="#">&raquo;</a></div>
            </div>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>
</body>
</html>
