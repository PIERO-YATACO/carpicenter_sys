<?php
require_once __DIR__ . '/../auth/check_auth.php';
$page_title = 'Carrito de Compra'; $page_subtitle = 'Revisa los productos antes de confirmar la compra'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - Carpicenter</title>
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
                <div><h2>Carrito de Compra</h2><p>Revisa los productos antes de confirmar la compra</p></div>
            </div>
            <div class="card-panel" style="margin-bottom:1.2rem;">
                <div class="card-header"><h3>Proveedor</h3></div>
                <div class="card-body-custom">
                    <div class="form-row">
                        <div class="form-group"><label>Proveedor</label><select class="form-control"><option>Maderas del Perú</option><option>Ferretería SAC</option><option>Tapicería Lima</option></select></div>
                        <div class="form-group"><label>Fecha de compra</label><input type="date" class="form-control" value="2024-03-15"></div>
                    </div>
                </div>
            </div>
            <div class="card-panel">
                <div class="card-header"><h3>Productos</h3><button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Agregar</button></div>
                <div class="card-body-custom" style="padding:0;">
                    <table class="table-custom">
                        <thead><tr><th>Producto</th><th>Material</th><th>Cantidad</th><th>Precio Unit.</th><th>Subtotal</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Madera Pino</strong></td><td>Madera</td><td>10 m²</td><td>S/ 248.00</td><td>S/ 2,480.00</td><td><button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td><strong>Tinte Nogal</strong></td><td>Acabados</td><td>5.0 L</td><td>S/ 48.00</td><td>S/ 240.00</td><td><button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td><strong>Clavo 1"</strong></td><td>Ferretería</td><td>2.05 kg</td><td>S/ 8.00</td><td>S/ 16.00</td><td><button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                            <tr><td><strong>Tapiz Gris</strong></td><td>Tapicería</td><td>3.60 m</td><td>S/ 30.00</td><td>S/ 108.00</td><td><button class="btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;">
                <button class="btn btn-outline"><i class="fas fa-trash"></i> Vaciar carrito</button>
                <div style="display:flex;align-items:center;gap:2rem;">
                    <span style="font-size:1.1rem;font-weight:600;">Total: <span style="color:var(--red-light);font-size:1.3rem;">S/ 2,844.00</span></span>
                    <button class="btn btn-success"><i class="fas fa-check"></i> Confirmar compra</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>
</body>
</html>
