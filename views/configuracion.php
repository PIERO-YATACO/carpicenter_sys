<?php
require_once __DIR__ . '/../auth/check_auth.php';
$page_title = 'Configuración'; $page_subtitle = 'Ajustes generales del sistema'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <script src="/carpicenter_sys/assets/js/theme.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header">
                <div><h2>Configuración</h2><p>Ajustes generales del sistema</p></div>
            </div>
            <div class="grid-2">
                <div class="card-panel" style="margin-bottom:1.2rem;">
                    <div class="card-header"><h3><i class="fas fa-building" style="color:var(--primary);margin-right:0.5rem;"></i>Datos de la empresa</h3></div>
                    <div class="card-body-custom">
                        <div class="form-group"><label>Nombre de la empresa</label><input type="text" class="form-control" value="Industrias Carpicenter S.A.C."></div>
                        <div class="form-group"><label>RUC</label><input type="text" class="form-control" value="20612345678"></div>
                        <div class="form-group"><label>Dirección</label><input type="text" class="form-control" value="Av. Industrial 1234, Lima"></div>
                        <div class="form-row">
                            <div class="form-group"><label>Teléfono</label><input type="text" class="form-control" value="(01) 234-5678"></div>
                            <div class="form-group"><label>Email</label><input type="email" class="form-control" value="info@carpicenter.com"></div>
                        </div>
                    </div>
                </div>
                <div class="card-panel" style="margin-bottom:1.2rem;">
                    <div class="card-header"><h3><i class="fas fa-palette" style="color:var(--primary);margin-right:0.5rem;"></i>Apariencia</h3></div>
                    <div class="card-body-custom">
                        <div class="form-group"><label>Tema</label><select class="form-control" id="theme-select"><option>Oscuro (predeterminado)</option><option>Claro</option></select></div>
                        <div class="form-group"><label>Color principal</label><div style="display:flex;gap:0.5rem;margin-top:0.3rem;">
                            <div class="color-option" data-primary="#C62828" data-light="#E53935" data-dark="#8B1A1A" style="width:32px;height:32px;border-radius:6px;background:#C62828;cursor:pointer;"></div>
                            <div class="color-option" data-primary="#1565C0" data-light="#42A5F5" data-dark="#0D47A1" style="width:32px;height:32px;border-radius:6px;background:#1565C0;cursor:pointer;"></div>
                            <div class="color-option" data-primary="#2E7D32" data-light="#66BB6A" data-dark="#1B5E20" style="width:32px;height:32px;border-radius:6px;background:#2E7D32;cursor:pointer;"></div>
                            <div class="color-option" data-primary="#6A1B9A" data-light="#AB47BC" data-dark="#4A148C" style="width:32px;height:32px;border-radius:6px;background:#6A1B9A;cursor:pointer;"></div>
                            <div class="color-option" data-primary="#E65100" data-light="#FB8C00" data-dark="#BF360C" style="width:32px;height:32px;border-radius:6px;background:#E65100;cursor:pointer;"></div>
                        </div></div>
                        <div class="form-group"><label>Logo de la empresa</label><div class="img-upload" style="height:100px;"><i class="fas fa-cloud-upload-alt"></i><small>Subir logo (PNG, JPG)</small></div></div>
                        <div class="form-group"><label>Idioma</label><select class="form-control"><option>Español</option><option>English</option></select></div>
                    </div>
                </div>
                <div class="card-panel">
                    <div class="card-header"><h3><i class="fas fa-cog" style="color:var(--primary);margin-right:0.5rem;"></i>Sistema</h3></div>
                    <div class="card-body-custom">
                        <div class="form-group"><label>Moneda predeterminada</label><select class="form-control"><option>Soles (S/)</option><option>Dólares ($)</option></select></div>
                        <div class="form-group"><label>IGV (%)</label><input type="number" class="form-control" value="18"></div>
                        <div class="form-group"><label>Stock mínimo por defecto</label><input type="number" class="form-control" value="5"></div>
                        <div class="form-group"><label>Zona horaria</label><select class="form-control"><option>América/Lima (UTC-5)</option></select></div>
                    </div>
                </div>
                <div class="card-panel">
                    <div class="card-header"><h3><i class="fas fa-database" style="color:var(--primary);margin-right:0.5rem;"></i>Base de datos</h3></div>
                    <div class="card-body-custom">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;"><div style="width:10px;height:10px;border-radius:50%;background:#66BB6A;"></div><span style="font-size:0.85rem;">Conectado a PostgreSQL</span></div>
                        <div class="form-group"><label>Servidor</label><input type="text" class="form-control" value="localhost:5432" disabled></div>
                        <div class="form-group"><label>Base de datos</label><input type="text" class="form-control" value="carpicenter_db" disabled></div>
                        <div style="display:flex;gap:0.5rem;margin-top:1rem;">
                            <button class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Backup</button>
                            <button class="btn btn-outline btn-sm"><i class="fas fa-upload"></i> Restaurar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0.8rem;justify-content:flex-end;margin-top:1.5rem;">
                <button class="btn btn-outline">Restablecer</button>
                <button class="btn btn-primary" id="save-config-btn"><i class="fas fa-save"></i> Guardar configuración</button>
            </div>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>
</body>
</html>
