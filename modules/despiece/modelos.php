<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

// ── Eliminar modelo ──────────────────────────────────────────
if (isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    try {
        $db->beginTransaction();
        $db->prepare("DELETE FROM insumos_modelo  WHERE producto_id = ?")->execute([$did]);
        $db->prepare("DELETE FROM piezas_modelo   WHERE producto_id = ?")->execute([$did]);
        $db->prepare("DELETE FROM productos_maestros WHERE id = ?")->execute([$did]);
        $db->commit();
        header("Location: modelos.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
    }
}

// ── Listado ─────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$where  = $search ? "WHERE nombre_modelo ILIKE :s" : "";
$params = $search ? [':s' => "%$search%"] : [];

$stmt = $db->prepare("
    SELECT pm.*, 
           COUNT(DISTINCT p.id) AS total_piezas,
           COUNT(DISTINCT i.id) AS total_insumos
    FROM productos_maestros pm
    LEFT JOIN piezas_modelo  p ON p.producto_id = pm.id
    LEFT JOIN insumos_modelo i ON i.producto_id = pm.id
    $where
    GROUP BY pm.id
    ORDER BY pm.nombre_modelo ASC
");
$stmt->execute($params);
$modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title    = 'Despiece — Modelos';
$page_subtitle = 'Gestiona los modelos de muebles y sus hojas de despiece';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Modelos de Muebles - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../views/partials/header.php'; ?>
        <div class="page-content">

            <?php if (isset($_GET['msg'])): ?>
            <div style="background:rgba(46,125,50,0.15);border:1px solid #2E7D32;border-radius:8px;padding:0.7rem 1rem;margin-bottom:1rem;color:#66BB6A;font-size:0.85rem;">
                <i class="fas fa-check-circle"></i>
                <?= $_GET['msg'] === 'deleted' ? 'Modelo eliminado correctamente.' : 'Modelo guardado correctamente.' ?>
            </div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2><i class="fas fa-drafting-compass" style="color:var(--primary);margin-right:0.5rem;"></i>Modelos de Muebles</h2>
                    <p>Hoja de despiece y lista de materiales (BOM)</p>
                </div>
                <a href="modelo_form.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Modelo
                </a>
            </div>

            <!-- Buscador -->
            <div class="filter-bar">
                <form method="GET" style="display:flex;align-items:center;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;padding:0 0.8rem;gap:0.5rem;min-width:280px;">
                    <i class="fas fa-search" style="color:var(--text-muted);font-size:0.85rem;"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Buscar modelo..." style="background:none;border:none;outline:none;color:var(--text-primary);padding:0.55rem 0;font-size:0.85rem;width:100%;">
                </form>
                <span style="color:var(--text-muted);font-size:0.85rem;">
                    Total: <strong style="color:var(--text-primary);"><?= count($modelos) ?></strong> modelos
                </span>
            </div>

            <!-- Tabla -->
            <div class="card-panel">
                <div class="card-body-custom" style="padding:0;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Modelo</th>
                                <th>Categoría</th>
                                <th style="text-align:center;">Piezas</th>
                                <th style="text-align:center;">Insumos</th>
                                <th style="text-align:center;">Tiempo Fab.</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modelos as $m): ?>
                            <tr>
                                <td><span class="badge badge-info"><?= htmlspecialchars($m['codigo']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($m['nombre_modelo']) ?></strong>
                                    <?php if ($m['descripcion']): ?>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($m['descripcion']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['categoria'] ?? '—') ?></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-warning"><?= $m['total_piezas'] ?> piezas</span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge badge-success"><?= $m['total_insumos'] ?> insumos</span>
                                </td>
                                <td style="text-align:center;">
                                    <?= $m['tiempo_fab_horas'] ? $m['tiempo_fab_horas'] . ' h' : '—' ?>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:flex;gap:0.4rem;justify-content:center;">
                                        <a href="bom.php?id=<?= $m['id'] ?>" class="btn-icon" title="Generar BOM" style="color:#42A5F5;">
                                            <i class="fas fa-calculator"></i>
                                        </a>
                                        <a href="modelo_form.php?id=<?= $m['id'] ?>" class="btn-icon" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar modelo y todas sus piezas?');">
                                            <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn-icon" style="color:#ef4444;"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($modelos)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted);">
                                    <i class="fas fa-drafting-compass" style="font-size:2rem;margin-bottom:0.5rem;display:block;opacity:0.4;"></i>
                                    No hay modelos registrados aún.<br>
                                    <a href="modelo_form.php" style="color:var(--primary-light);">Crea el primero</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
</body>
</html>
