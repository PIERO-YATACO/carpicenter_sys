<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$modelo  = $piezas = $insumos = [];
$error   = '';

$tableros = $db->query("SELECT id, nombre, espesor_mm FROM despiece_tableros WHERE activo=TRUE ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($is_edit) {
    $s = $db->prepare("SELECT * FROM productos_maestros WHERE id=?"); $s->execute([$id]);
    $modelo = $s->fetch(PDO::FETCH_ASSOC);
    if (!$modelo) { header("Location: modelos.php"); exit; }
    $sp = $db->prepare("SELECT * FROM piezas_modelo WHERE producto_id=? ORDER BY nro_pieza"); $sp->execute([$id]);
    $piezas = $sp->fetchAll(PDO::FETCH_ASSOC);
    $si = $db->prepare("SELECT * FROM insumos_modelo WHERE producto_id=? ORDER BY nombre_insumo"); $si->execute([$id]);
    $insumos = $si->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $nombre = trim($_POST['nombre_modelo'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');
    $cat    = trim($_POST['categoria'] ?? '');
    $tiempo = !empty($_POST['tiempo_fab_horas']) ? (float)$_POST['tiempo_fab_horas'] : null;
    try {
        $db->beginTransaction();
        if ($is_edit) {
            $db->prepare("UPDATE productos_maestros SET codigo=?,nombre_modelo=?,descripcion=?,categoria=?,tiempo_fab_horas=? WHERE id=?")
               ->execute([$codigo,$nombre,$desc,$cat,$tiempo,$id]);
        } else {
            $s=$db->prepare("INSERT INTO productos_maestros(codigo,nombre_modelo,descripcion,categoria,tiempo_fab_horas) VALUES(?,?,?,?,?) RETURNING id");
            $s->execute([$codigo,$nombre,$desc,$cat,$tiempo]);
            $id=$s->fetchColumn();
        }
        $db->prepare("DELETE FROM piezas_modelo WHERE producto_id=?")->execute([$id]);
        foreach (($_POST['p_nombre'] ?? []) as $i => $np) {
            if (trim($np)==='') continue;
            $db->prepare("INSERT INTO piezas_modelo(producto_id,nro_pieza,nombre_pieza,tablero_id,largo_final_mm,ancho_final_mm,espesor_mm,cant_por_mueble,tiene_veta,l1_canto_mm,l2_canto_mm,a1_canto_mm,a2_canto_mm,ranura_lado,perf_cant,notas) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([
                   $id, $i+1, trim($np),
                   !empty($_POST['p_tablero'][$i]) ? (int)$_POST['p_tablero'][$i] : null,
                   (float)($_POST['p_largo'][$i]??0),
                   (float)($_POST['p_ancho'][$i]??0),
                   !empty($_POST['p_esp'][$i]) ? (float)$_POST['p_esp'][$i] : null,
                   (float)($_POST['p_cant'][$i]??1),
                   isset($_POST['p_veta'][$i]) ? 'true' : 'false',
                   (float)($_POST['p_l1'][$i]??0),
                   (float)($_POST['p_l2'][$i]??0),
                   (float)($_POST['p_a1'][$i]??0),
                   (float)($_POST['p_a2'][$i]??0),
                   trim($_POST['p_ranura'][$i]??'')?:null,
                   (int)($_POST['p_perf'][$i]??0),
                   trim($_POST['p_notas'][$i]??'')?:null,
               ]);
        }
        $db->prepare("DELETE FROM insumos_modelo WHERE producto_id=?")->execute([$id]);
        foreach (($_POST['ins_nombre'] ?? []) as $i => $ni) {
            if (trim($ni)==='') continue;
            $db->prepare("INSERT INTO insumos_modelo(producto_id,nombre_insumo,cantidad_unitaria,unidad_medida,notas) VALUES(?,?,?,?,?)")
               ->execute([$id,trim($ni),(float)($_POST['ins_cant'][$i]??1),trim($_POST['ins_um'][$i]??'unidad'),trim($_POST['ins_notas'][$i]??'')]);
        }
        $db->commit();
        header("Location: modelos.php?msg=saved"); exit;
    } catch(Exception $e) {
        if($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

$page_title = $is_edit ? 'Editar Modelo' : 'Nuevo Modelo';
$page_subtitle = 'Hoja de despiece';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?> – Carpicenter</title>
<link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.section-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:1.4rem;margin-bottom:1.4rem;}
.section-title{font-size:.9rem;font-weight:700;color:var(--primary-light);display:flex;align-items:center;gap:.5rem;padding-bottom:.7rem;border-bottom:1px solid var(--border-color);margin-bottom:1rem;}
/* Tabla de piezas */
.pieces-table{width:100%;border-collapse:collapse;font-size:.8rem;}
.pieces-table th{background:var(--bg-primary);color:var(--text-muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;padding:.5rem .6rem;text-align:center;white-space:nowrap;border-bottom:2px solid var(--border-color);}
.pieces-table th.left{text-align:left;}
.pieces-table td{padding:.35rem .4rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
.pieces-table tr:hover td{background:rgba(255,255,255,.02);}
.fi{width:100%;background:var(--bg-primary);border:1px solid var(--border-color);border-radius:5px;color:var(--text-primary);font-size:.78rem;padding:.35rem .45rem;}
.fi:focus{outline:none;border-color:var(--primary);}
.fi-sm{width:60px;}
.fi-md{width:80px;}
.nro-cell{color:var(--text-muted);font-size:.75rem;text-align:center;width:28px;}
.canto-cell{width:52px;}
.canto-yes{background:rgba(198,40,40,.15);border-color:var(--primary);}
/* Leyenda de cantos */
.legend-box{background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px;padding:.8rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;}
.legend-diagram{position:relative;width:90px;height:60px;border:2px solid #42A5F5;border-radius:4px;flex-shrink:0;}
.leg-label{position:absolute;font-size:.58rem;font-weight:700;color:#fff;background:rgba(0,0,0,.7);padding:1px 4px;border-radius:3px;}
.leg-top{top:-10px;left:50%;transform:translateX(-50%);color:#F9A825;}
.leg-bot{bottom:-10px;left:50%;transform:translateX(-50%);color:#F9A825;}
.leg-left{left:-18px;top:50%;transform:translateY(-50%);color:#66BB6A;}
.leg-right{right:-18px;top:50%;transform:translateY(-50%);color:#66BB6A;}
.legend-text{font-size:.75rem;color:var(--text-muted);line-height:1.7;}
.legend-text span{font-weight:600;}
/* Insumos */
.ins-row{display:grid;grid-template-columns:2fr 90px 110px 2fr 32px;gap:.4rem;align-items:center;padding:.3rem 0;border-bottom:1px solid rgba(255,255,255,.04);}
.ins-header{font-size:.65rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);padding-bottom:.4rem;border-bottom:1px solid var(--border-color);}
.btn-del{background:none;border:none;color:#ef4444;cursor:pointer;font-size:.9rem;padding:.2rem;}
.badge-tip{display:inline-block;background:rgba(66,165,245,.12);color:#42A5F5;border-radius:4px;padding:.1rem .5rem;font-size:.68rem;font-weight:600;margin-left:.5rem;}
</style>
</head>
<body>
<div class="app-wrapper">
<?php include __DIR__.'/../../views/partials/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__.'/../../views/partials/header.php'; ?>
<div class="page-content">

<?php if ($error): ?>
<div style="background:rgba(198,40,40,.15);border:1px solid #C62828;border-radius:8px;padding:.7rem 1rem;margin-bottom:1rem;color:#ef4444;font-size:.83rem;">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2><i class="fas fa-drafting-compass" style="color:var(--primary);margin-right:.5rem;"></i><?= $page_title ?></h2>
        <p>Completa los datos del mueble, sus piezas y los accesorios necesarios</p>
    </div>
    <a href="modelos.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<form method="POST" id="frmModelo">

<!-- ① DATOS GENERALES -->
<div class="section-card">
    <div class="section-title"><i class="fas fa-info-circle"></i> ① Datos del Mueble</div>
    <div class="form-row">
        <div class="form-group">
            <label>Código interno <span style="color:#ef4444">*</span></label>
            <input class="form-control" name="codigo" required placeholder="ej. ESC-D2" value="<?= htmlspecialchars($modelo['codigo']??'') ?>">
            <small style="color:var(--text-muted);">Código único para identificar el modelo</small>
        </div>
        <div class="form-group">
            <label>Nombre del Mueble <span style="color:#ef4444">*</span></label>
            <input class="form-control" name="nombre_modelo" required placeholder="ej. Escritorio Ejecutivo D2" value="<?= htmlspecialchars($modelo['nombre_modelo']??'') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Categoría</label>
            <input class="form-control" name="categoria" placeholder="ej. Oficina, Dormitorio, Cocina" value="<?= htmlspecialchars($modelo['categoria']??'') ?>">
        </div>
        <div class="form-group">
            <label>Tiempo de fabricación (horas)</label>
            <input class="form-control" name="tiempo_fab_horas" type="number" step="0.5" min="0" placeholder="ej. 6" value="<?= htmlspecialchars($modelo['tiempo_fab_horas']??'') ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Descripción del mueble</label>
        <textarea class="form-control" name="descripcion" rows="2" placeholder="Descripción general, características especiales..."><?= htmlspecialchars($modelo['descripcion']??'') ?></textarea>
    </div>
</div>

<!-- ② PIEZAS A CORTAR -->
<div class="section-card">
    <div class="section-title">
        <i class="fas fa-cut"></i> ② Piezas a Cortar
        <span class="badge-tip">Medidas en milímetros (mm)</span>
    </div>

    <!-- Leyenda visual de cantos -->
    <div class="legend-box">
        <div style="padding-left:22px;padding-right:22px;">
            <div class="legend-diagram">
                <span class="leg-label leg-top">LARGO 1 (L1)</span>
                <span class="leg-label leg-bot">LARGO 2 (L2)</span>
                <span class="leg-label leg-left">A1</span>
                <span class="leg-label leg-right">A2</span>
            </div>
        </div>
        <div class="legend-text">
            <div>📐 <span>Largo</span> = dimensión más larga de la pieza</div>
            <div>📐 <span>Ancho</span> = dimensión más corta de la pieza</div>
            <div>🟡 <span>L1, L2, A1, A2</span> = lados que llevan tapacanto (enchape)</div>
            <div style="color:var(--text-muted);font-size:.7rem;margin-top:.3rem;">Ingresa el grosor del tapacanto en mm (ej. 0.5, 1, 3) o deja en 0 si no lleva</div>
        </div>
    </div>

    <div style="overflow-x:auto;">
    <table class="pieces-table" id="tblPiezas">
        <thead><tr>
            <th style="width:28px;">#</th>
            <th class="left" style="min-width:160px;">Nombre de la Pieza</th>
            <th style="min-width:150px;">Tablero / Material</th>
            <th>Largo (mm)</th>
            <th>Ancho (mm)</th>
            <th>Esp.</th>
            <th>Cant.</th>
            <th colspan="4" style="color:#F9A825;">── Tapacanto por lado (mm) ──</th>
            <th>Veta</th>
            <th>Ranura</th>
            <th>Notas</th>
            <th style="width:32px;"></th>
        </tr>
        <tr style="font-size:.62rem;color:#F9A825;background:var(--bg-primary);">
            <th></th><th class="left"></th><th></th>
            <th></th><th></th><th></th><th></th>
            <th>L1</th><th>L2</th><th>A1</th><th>A2</th>
            <th></th><th></th><th></th><th></th>
        </tr></thead>
        <tbody id="piezasBody">
        <?php
        $rows = !empty($piezas) ? $piezas : [array_fill_keys(['nombre_pieza','tablero_id','largo_final_mm','ancho_final_mm','espesor_mm','cant_por_mueble','l1_canto_mm','l2_canto_mm','a1_canto_mm','a2_canto_mm','tiene_veta','ranura_lado','notas'],null)];
        foreach ($rows as $ri => $p):
        ?>
        <tr class="pieza-row" id="row-<?=$ri?>">
            <td class="nro-cell"><?=$ri+1?></td>
            <td><input class="fi" type="text" name="p_nombre[]" placeholder="ej. Panel lateral izquierdo" value="<?=htmlspecialchars($p['nombre_pieza']??'')?>"></td>
            <td>
                <select class="fi" name="p_tablero[]">
                    <option value="">— Seleccionar —</option>
                    <?php foreach($tableros as $t): ?>
                    <option value="<?=$t['id']?>" <?=($p['tablero_id']??'')==$t['id']?'selected':''?>><?=htmlspecialchars($t['nombre'])?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input class="fi fi-md" type="number" step="0.01" name="p_largo[]" placeholder="0" value="<?=$p['largo_final_mm']??''?>"></td>
            <td><input class="fi fi-md" type="number" step="0.01" name="p_ancho[]" placeholder="0" value="<?=$p['ancho_final_mm']??''?>"></td>
            <td><input class="fi fi-sm" type="number" step="0.01" name="p_esp[]" placeholder="18" value="<?=$p['espesor_mm']??''?>"></td>
            <td><input class="fi fi-sm" type="number" step="0.01" name="p_cant[]" value="<?=$p['cant_por_mueble']??1?>" min="1"></td>
            <td class="canto-cell"><input class="fi <?=$p['l1_canto_mm']>0?'canto-yes':''?>" type="number" step="0.01" name="p_l1[]" value="<?=$p['l1_canto_mm']??0?>" min="0" oninput="markCanto(this)"></td>
            <td class="canto-cell"><input class="fi <?=$p['l2_canto_mm']>0?'canto-yes':''?>" type="number" step="0.01" name="p_l2[]" value="<?=$p['l2_canto_mm']??0?>" min="0" oninput="markCanto(this)"></td>
            <td class="canto-cell"><input class="fi <?=$p['a1_canto_mm']>0?'canto-yes':''?>" type="number" step="0.01" name="p_a1[]" value="<?=$p['a1_canto_mm']??0?>" min="0" oninput="markCanto(this)"></td>
            <td class="canto-cell"><input class="fi <?=$p['a2_canto_mm']>0?'canto-yes':''?>" type="number" step="0.01" name="p_a2[]" value="<?=$p['a2_canto_mm']??0?>" min="0" oninput="markCanto(this)"></td>
            <td style="text-align:center;"><input type="checkbox" name="p_veta[<?=$ri?>]" title="¿Tiene veta/dirección?" <?=!empty($p['tiene_veta'])&&$p['tiene_veta']!='false'?'checked':''?> style="accent-color:var(--primary);width:15px;height:15px;"></td>
            <td><input class="fi fi-sm" type="text" name="p_ranura[]" placeholder="L1" title="Lado donde va la ranura" value="<?=htmlspecialchars($p['ranura_lado']??'')?>"></td>
            <td><input class="fi" type="text" name="p_notas[]" placeholder="Observaciones..." value="<?=htmlspecialchars($p['notas']??'')?>"></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)" title="Eliminar pieza"><i class="fas fa-times"></i></button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <button type="button" onclick="addPieza()" class="btn btn-outline btn-sm" style="margin-top:.8rem;">
        <i class="fas fa-plus"></i> Agregar Pieza
    </button>
</div>

<!-- ③ ACCESORIOS E INSUMOS -->
<div class="section-card">
    <div class="section-title"><i class="fas fa-screwdriver"></i> ③ Accesorios e Insumos
        <span class="badge-tip">Bisagras, correderas, tornillos, etc.</span>
    </div>
    <div class="ins-row ins-header">
        <span>Nombre del Accesorio / Insumo</span>
        <span style="text-align:center;">Cantidad por mueble</span>
        <span>Unidad de medida</span>
        <span>Notas</span>
        <span></span>
    </div>
    <div id="insumosBody">
    <?php
    $def_ins = !empty($insumos) ? $insumos : [['nombre_insumo'=>'','cantidad_unitaria'=>1,'unidad_medida'=>'unidad','notas'=>'']];
    foreach($def_ins as $ii => $ins):
    ?>
    <div class="ins-row">
        <input class="fi" type="text" name="ins_nombre[]" placeholder="ej. Bisagra 35mm, Corredera 45cm..." value="<?=htmlspecialchars($ins['nombre_insumo']??'')?>">
        <input class="fi" type="number" step="0.01" name="ins_cant[]" value="<?=$ins['cantidad_unitaria']??1?>" style="text-align:center;">
        <select class="fi" name="ins_um[]">
            <?php foreach(['unidad','par','ml','m2','kg','litro','paquete'] as $um): ?>
            <option value="<?=$um?>" <?=($ins['unidad_medida']??'')===$um?'selected':''?>><?=$um?></option>
            <?php endforeach; ?>
        </select>
        <input class="fi" type="text" name="ins_notas[]" placeholder="Observaciones..." value="<?=htmlspecialchars($ins['notas']??'')?>">
        <button type="button" class="btn-del" onclick="this.closest('.ins-row').remove()"><i class="fas fa-times"></i></button>
    </div>
    <?php endforeach; ?>
    </div>
    <button type="button" onclick="addInsumo()" class="btn btn-outline btn-sm" style="margin-top:.8rem;">
        <i class="fas fa-plus"></i> Agregar Accesorio
    </button>
</div>

<!-- GUARDAR -->
<div style="display:flex;justify-content:flex-end;gap:1rem;margin-bottom:2rem;">
    <a href="modelos.php" class="btn btn-outline">Cancelar</a>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Modelo</button>
</div>

</form>
</div></div></div>
<?php include __DIR__.'/../../views/partials/footer.php'; ?>

<script>
const tableroOpts = `<option value="">— Seleccionar —</option><?php foreach($tableros as $t) echo '<option value="'.$t['id'].'">'.htmlspecialchars($t['nombre']).'</option>'; ?>`;
let pc = <?=count($rows)?>;

function markCanto(el) {
    el.classList.toggle('canto-yes', parseFloat(el.value) > 0);
}

function addPieza() {
    const i = pc++;
    const tr = document.createElement('tr');
    tr.className = 'pieza-row'; tr.id = 'row-'+i;
    tr.innerHTML = `
        <td class="nro-cell">${i+1}</td>
        <td><input class="fi" type="text" name="p_nombre[]" placeholder="ej. Panel lateral izquierdo"></td>
        <td><select class="fi" name="p_tablero[]">${tableroOpts}</select></td>
        <td><input class="fi fi-md" type="number" step="0.01" name="p_largo[]" placeholder="0"></td>
        <td><input class="fi fi-md" type="number" step="0.01" name="p_ancho[]" placeholder="0"></td>
        <td><input class="fi fi-sm" type="number" step="0.01" name="p_esp[]" placeholder="18"></td>
        <td><input class="fi fi-sm" type="number" step="0.01" name="p_cant[]" value="1" min="1"></td>
        <td class="canto-cell"><input class="fi" type="number" step="0.01" name="p_l1[]" value="0" min="0" oninput="markCanto(this)"></td>
        <td class="canto-cell"><input class="fi" type="number" step="0.01" name="p_l2[]" value="0" min="0" oninput="markCanto(this)"></td>
        <td class="canto-cell"><input class="fi" type="number" step="0.01" name="p_a1[]" value="0" min="0" oninput="markCanto(this)"></td>
        <td class="canto-cell"><input class="fi" type="number" step="0.01" name="p_a2[]" value="0" min="0" oninput="markCanto(this)"></td>
        <td style="text-align:center;"><input type="checkbox" name="p_veta[${i}]" style="accent-color:var(--primary);width:15px;height:15px;"></td>
        <td><input class="fi fi-sm" type="text" name="p_ranura[]" placeholder="L1"></td>
        <td><input class="fi" type="text" name="p_notas[]" placeholder="Observaciones..."></td>
        <td><button type="button" class="btn-del" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>`;
    document.getElementById('piezasBody').appendChild(tr);
}

function addInsumo() {
    const div = document.createElement('div');
    div.className = 'ins-row';
    div.innerHTML = `
        <input class="fi" type="text" name="ins_nombre[]" placeholder="ej. Bisagra 35mm...">
        <input class="fi" type="number" step="0.01" name="ins_cant[]" value="1" style="text-align:center;">
        <select class="fi" name="ins_um[]">
            <option>unidad</option><option>par</option><option>ml</option>
            <option>m2</option><option>kg</option><option>litro</option><option>paquete</option>
        </select>
        <input class="fi" type="text" name="ins_notas[]" placeholder="Observaciones...">
        <button type="button" class="btn-del" onclick="this.closest('.ins-row').remove()"><i class="fas fa-times"></i></button>`;
    document.getElementById('insumosBody').appendChild(div);
}

function removeRow(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('#piezasBody tr').length > 1) row.remove();
    // Renumerar
    document.querySelectorAll('#piezasBody tr').forEach((r,i) => {
        const nc = r.querySelector('.nro-cell');
        if(nc) nc.textContent = i+1;
    });
}
</script>
</body></html>
