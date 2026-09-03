<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once '../config/db.php';

// Restricción: Vendedores solo tienen acceso de lectura al catálogo, no pueden editar ni crear productos
if (in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora'])) {
    header("Location: productos.php?error=" . urlencode("Acceso denegado: Los vendedores tienen permiso únicamente de consulta en el catálogo de productos."));
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Si es edición, obtener datos existentes
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$producto_edit = null;
$colores_edit = [];

if($edit_id) {
    $stmt_p = $db->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt_p->execute([$edit_id]);
    $producto_edit = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if($producto_edit) {
        $stmt_c = $db->prepare("
            SELECT pc.color_id, pc.stock, pc.imagen_url, pc.codigo as codigo_sku, COALESCE(il.stock_actual, pc.stock, 0) as stock_real
            FROM producto_colores pc
            LEFT JOIN inventario_local il ON il.producto_id = pc.producto_id AND il.color_id = pc.color_id AND il.local_id = 1
            WHERE pc.producto_id = ?
        ");
        $stmt_c->execute([$edit_id]);
        while($row = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
            $colores_edit[$row['color_id']] = [
                'stock' => intval($row['stock_real']),
                'imagen_url' => $row['imagen_url'],
                'codigo_sku' => $row['codigo_sku']
            ];
        }

        $stmt_inv_extra = $db->prepare("
            SELECT il.color_id, il.stock_actual
            FROM inventario_local il
            WHERE il.producto_id = ? AND il.local_id = 1
        ");
        $stmt_inv_extra->execute([$edit_id]);
        while($row = $stmt_inv_extra->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($colores_edit[$row['color_id']])) {
                $colores_edit[$row['color_id']] = [
                    'stock' => intval($row['stock_actual']),
                    'imagen_url' => null,
                    'codigo_sku' => null
                ];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $categoria_id = (isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '') ? intval($_POST['categoria_id']) : null;
    $precio_compra = (isset($_POST['precio_compra']) && $_POST['precio_compra'] !== '') ? floatval($_POST['precio_compra']) : 0.0;
    $precio_venta = (isset($_POST['precio_venta']) && $_POST['precio_venta'] !== '') ? floatval($_POST['precio_venta']) : 0.0;
    $stock_minimo = (isset($_POST['stock_minimo']) && $_POST['stock_minimo'] !== '') ? intval($_POST['stock_minimo']) : 0;
    $colores_post = $_POST['colores'] ?? [];
    $codigos_variante_post = $_POST['codigo_variante'] ?? [];
    
    // Directorio base para imágenes
    $upload_dir = '../assets/uploads/productos/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $valid_extensions = ['jpg', 'jpeg', 'png', 'webp'];

    // Manejo de imagen principal explícita
    $imagen_url = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, $valid_extensions)) {
            $new_filename = uniqid('prod_main_') . '.' . $file_extension;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $new_filename)) {
                $imagen_url = '/carpicenter_sys/assets/uploads/productos/' . $new_filename;
            }
        } else {
            $mensaje = "Formato de imagen principal no válido.";
            $tipo_mensaje = "error";
        }
    }
    
    $en_catalogo = isset($_POST['en_catalogo']) ? true : false;
    $destacado_catalogo = isset($_POST['destacado_catalogo']) ? true : false;
    $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
    $tiempo_fabricacion = trim($_POST['tiempo_fabricacion'] ?? '3 a 5 días');

    if(!empty($nombre) && !empty($categoria_id) && empty($mensaje)) {
        try {
            $db->beginTransaction();

            if(!empty($id_post)) {
                // UPDATE
                $final_img = $imagen_url ?: ($producto_edit['imagen_url'] ?? null);
                $final_codigo = !empty($codigo) ? $codigo : ($producto_edit['codigo'] ?? ('PRD-' . str_pad($id_post, 3, '0', STR_PAD_LEFT)));
                $stmt = $db->prepare("UPDATE productos SET nombre=?, categoria_id=?, precio_compra=?, precio_venta=?, stock_minimo=?, imagen_url=?, en_catalogo=?, destacado_catalogo=?, descripcion_corta=?, tiempo_fabricacion=?, codigo=? WHERE id=?");
                $stmt->execute([$nombre, $categoria_id, $precio_compra, $precio_venta, $stock_minimo, $final_img, $en_catalogo ? 'true' : 'false', $destacado_catalogo ? 'true' : 'false', $descripcion_corta, $tiempo_fabricacion, $final_codigo, $id_post]);
                $producto_id = $id_post;
                
                // Borrar variantes anteriores para reinsertar las actualizadas
                $db->prepare("DELETE FROM producto_colores WHERE producto_id=?")->execute([$producto_id]);
            } else {
                // INSERT
                $stmt = $db->prepare("INSERT INTO productos (nombre, categoria_id, precio_compra, precio_venta, stock_minimo, imagen_url, es_fabricado, fecha_creacion, en_catalogo, destacado_catalogo, descripcion_corta, tiempo_fabricacion, codigo) VALUES (?, ?, ?, ?, ?, ?, true, CURRENT_DATE, ?, ?, ?, ?, ?) RETURNING id");
                $stmt->execute([$nombre, $categoria_id, $precio_compra, $precio_venta, $stock_minimo, $imagen_url, $en_catalogo ? 'true' : 'false', $destacado_catalogo ? 'true' : 'false', $descripcion_corta, $tiempo_fabricacion, $codigo]);
                $producto_id = $stmt->fetchColumn();

                // Si no se proveyó código en el insert, asignar PRD-xxx por defecto
                if (empty($codigo) && $producto_id) {
                    $auto_code = 'PRD-' . str_pad($producto_id, 3, '0', STR_PAD_LEFT);
                    $db->prepare("UPDATE productos SET codigo=? WHERE id=?")->execute([$auto_code, $producto_id]);
                    $final_codigo = $auto_code;
                } else {
                    $final_codigo = $codigo;
                }
            }

            if($producto_id) {
                // Obtener diccionario de codigos de color
                $col_stmt = $db->query("SELECT id, codigo FROM colores");
                $col_codes = $col_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                // Statements para guardar variantes e inventario
                $stmt_color = $db->prepare("INSERT INTO producto_colores (producto_id, color_id, stock, imagen_url, codigo) VALUES (?, ?, ?, ?, ?)");
                
                $stmt_inv = $db->prepare("
                    INSERT INTO inventario_local (producto_id, local_id, color_id, stock_actual, stock_reservado)
                    VALUES (:p, 1, :c, :stock, 0)
                    ON CONFLICT (producto_id, local_id, color_id) 
                    DO UPDATE SET stock_actual = EXCLUDED.stock_actual
                ");

                $imagenes_color_existentes = $_POST['imagen_color_actual'] ?? [];
                $first_color_image = null;

                foreach($colores_post as $c_id => $stock_input) {
                    $stock_val = (string)$stock_input !== '' ? intval($stock_input) : 0;
                    $col_img_url = $imagenes_color_existentes[$c_id] ?? null;

                    // Código / SKU de la variante
                    $var_code = strtoupper(trim($codigos_variante_post[$c_id] ?? ''));
                    if (empty($var_code)) {
                        $base_c_code = $col_codes[$c_id] ?? ('COL' . $c_id);
                        $var_code = ($final_codigo ?: 'PRD' . $producto_id) . '-' . $base_c_code;
                    }

                    // Si subió una nueva imagen para este color
                    if (isset($_FILES['imagen_color']) && isset($_FILES['imagen_color']['error'][$c_id]) && $_FILES['imagen_color']['error'][$c_id] === UPLOAD_ERR_OK) {
                        $file_ext = strtolower(pathinfo($_FILES['imagen_color']['name'][$c_id], PATHINFO_EXTENSION));
                        if (in_array($file_ext, $valid_extensions)) {
                            $new_fname = uniqid('prod_col_') . '.' . $file_ext;
                            if (move_uploaded_file($_FILES['imagen_color']['tmp_name'][$c_id], $upload_dir . $new_fname)) {
                                $col_img_url = '/carpicenter_sys/assets/uploads/productos/' . $new_fname;
                            }
                        }
                    }

                    if (!empty($col_img_url) && empty($first_color_image)) {
                        $first_color_image = $col_img_url;
                    }

                    // Se guarda si stock > 0, si tiene imagen o si se ingresó stock
                    if ($stock_val > 0 || !empty($col_img_url) || (string)$stock_input !== '') {
                        $stmt_color->execute([$producto_id, $c_id, $stock_val, $col_img_url, $var_code]);
                        $stmt_inv->execute([':p' => $producto_id, ':c' => $c_id, ':stock' => $stock_val]);
                    }
                }

                // SI NO HABÍA IMAGEN PRINCIPAL, usar automáticamente la primera imagen de color subida
                if (empty($imagen_url) && !empty($first_color_image)) {
                    $db->prepare("UPDATE productos SET imagen_url = ? WHERE id = ?")->execute([$first_color_image, $producto_id]);
                }
                
                $db->commit();
                header("Location: productos.php?success=1");
                exit;
            } else {
                $db->rollBack();
                $mensaje = "Error al guardar el producto en la base de datos.";
                $tipo_mensaje = "error";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else if (empty($mensaje)) {
        $mensaje = "Por favor, complete los campos obligatorios (Nombre y Categoría).";
        $tipo_mensaje = "warning";
    }
}

// Fetch categories
$stmt_cat = $db->query("SELECT * FROM categorias ORDER BY nombre ASC");
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Fetch colors
$stmt_col = $db->query("SELECT * FROM colores ORDER BY nombre ASC");
$colores_lista = $stmt_col->fetchAll(PDO::FETCH_ASSOC);

$page_title = $producto_edit ? 'Editar Producto' : 'Nuevo Producto'; 
$page_subtitle = $producto_edit ? 'Modifica la información del producto' : 'Registra un nuevo producto con variantes de color'; 

$val_nombre = $producto_edit['nombre'] ?? '';
$val_codigo = $producto_edit['codigo'] ?? '';
$val_cat = $producto_edit['categoria_id'] ?? '';
$val_pc = $producto_edit['precio_compra'] ?? '';
$val_pv = $producto_edit['precio_venta'] ?? '';
$val_smin = $producto_edit['stock_minimo'] ?? '0';
$val_img = $producto_edit['imagen_url'] ?? '';

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
        .img-preview { max-width: 100%; max-height: 150px; border-radius: 6px; margin-top: 10px; object-fit: contain; }
        
        /* Desactivar flechas / triangulitos en inputs numéricos */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] { 
            -moz-appearance: textfield; 
            appearance: textfield;
        }
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
                <a href="productos.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
            
            <?php if(!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <form method="POST" action="producto_form.php" enctype="multipart/form-data">
                <?php if($producto_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $producto_edit['id']; ?>">
                <?php endif; ?>
                
                <div class="grid-2">
                    <div class="card-panel">
                        <div class="card-header"><h3>Información del producto</h3></div>
                        <div class="card-body-custom">
                            <div class="form-group">
                                <label>Nombre del producto *</label>
                                <input type="text" name="nombre" id="inputProdNombre" class="form-control" placeholder="Ej: SILLA CAPRI o BANCO TOP" value="<?php echo htmlspecialchars($val_nombre); ?>" onkeyup="handleNombreChange(this.value)" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Código Oficial Carpicenter (Ej: CA-SI-CAPRI) *</label>
                                    <input type="text" name="codigo" id="inputProdCodigo" class="form-control" style="font-weight:700; text-transform:uppercase; color:#B91C1C; letter-spacing:0.5px;" placeholder="Ej: CA-SI-CAPRI" value="<?php echo htmlspecialchars($val_codigo); ?>" oninput="this.dataset.userEdited='true'" required>
                                </div>
                                <div class="form-group">
                                    <label>Categoría *</label>
                                    <select name="categoria_id" class="form-control" required>
                                        <option value="">Seleccionar</option>
                                        <?php foreach($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($val_cat == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(mb_strtoupper($cat['nombre'], 'UTF-8')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea name="descripcion" class="form-control" placeholder="Descripción detallada del producto..."></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Precio de costo (S/)</label>
                                    <input type="number" step="0.01" name="precio_compra" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($val_pc); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Precio de venta (S/)</label>
                                    <input type="number" step="0.01" name="precio_venta" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($val_pv); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 1rem;">
                                <label>Stock mínimo global</label>
                                <input type="number" name="stock_minimo" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($val_smin); ?>" style="max-width: 200px;">
                            </div>

                            <hr style="border-color:var(--border-color); margin:1.5rem 0;">
                            
                            <h4 style="color:var(--text-primary); font-size:0.95rem; margin-bottom:1rem;">
                                <i class="fas fa-book-open" style="color:var(--primary); margin-right:6px;"></i> Opciones de Catálogo Digital
                            </h4>

                            <div style="display:flex; gap:1.5rem; margin-bottom:1rem; flex-wrap:wrap;">
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem; color:var(--text-primary);">
                                    <input type="checkbox" name="en_catalogo" value="1" <?php echo (!isset($producto_edit['en_catalogo']) || $producto_edit['en_catalogo']) ? 'checked' : ''; ?>>
                                    Mostrar en Catálogo Digital
                                </label>

                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem; color:#f59e0b; font-weight:600;">
                                    <input type="checkbox" name="destacado_catalogo" value="1" <?php echo (!empty($producto_edit['destacado_catalogo'])) ? 'checked' : ''; ?>>
                                    ⭐ Producto Destacado en Catálogo
                                </label>
                            </div>

                            <div class="form-group">
                                <label>Descripción comercial corta (para tarjetas de catálogo)</label>
                                <input type="text" name="descripcion_corta" class="form-control" placeholder="Ej: Cocina moderna con acabados en melamina RH de alta resistencia" value="<?php echo htmlspecialchars($producto_edit['descripcion_corta'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Tiempo estimado de fabricación</label>
                                <input type="text" name="tiempo_fabricacion" class="form-control" placeholder="Ej: 3 a 5 días" value="<?php echo htmlspecialchars($producto_edit['tiempo_fabricacion'] ?? '3 a 5 días'); ?>">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="card-panel" style="margin-bottom:1.2rem;">
                            <div class="card-header"><h3>Imagen Principal (Defecto)</h3></div>
                            <div class="card-body-custom">
                                <div class="form-group">
                                    <input type="file" name="imagen" class="form-control" accept="image/jpeg, image/png, image/webp" onchange="previewImage(event)" style="padding: 10px;">
                                    <?php if($val_img): ?>
                                        <img id="img-preview" src="<?php echo htmlspecialchars($val_img); ?>" class="img-preview" style="background:#fff;">
                                    <?php else: ?>
                                        <img id="img-preview" src="" class="img-preview" style="display:none; background:#fff;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <script>
                                function previewImage(event) {
                                    var reader = new FileReader();
                                    reader.onload = function(){
                                        var output = document.getElementById('img-preview');
                                        output.src = reader.result;
                                        output.style.display = 'block';
                                    };
                                    if(event.target.files[0]) {
                                        reader.readAsDataURL(event.target.files[0]);
                                    }
                                }
                            </script>
                        </div>
                        <div class="card-panel">
                            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <h3 style="margin:0;">Variantes de Color y Stock</h3>
                                <button type="button" class="btn btn-sm btn-success" onclick="openModalNuevoColor()" style="font-size:0.8rem; padding:5px 12px; border-radius:6px; cursor:pointer;">
                                    <i class="fas fa-plus-circle"></i> + Crear Nuevo Color
                                </button>
                            </div>
                            <div class="card-body-custom">
                                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:10px;">Ingresa el stock, código de color/SKU y la foto específica por cada acabado.</p>
                                <div id="coloresContainer" style="display:flex; flex-direction:column; gap:8px; max-height:420px; overflow-y:auto; padding-right:5px; margin-bottom:1rem;">
                                    <?php foreach($colores_lista as $c): 
                                        $stock_val = $colores_edit[$c['id']]['stock'] ?? 0;
                                        $col_img_val = $colores_edit[$c['id']]['imagen_url'] ?? null;
                                        $col_sku_val = $colores_edit[$c['id']]['codigo_sku'] ?? '';
                                        $c_code = $c['codigo'] ?? ('COL' . $c['id']);
                                    ?>
                                    <div id="color-row-<?php echo $c['id']; ?>" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-primary); padding:10px; border-radius:8px; border:1px solid var(--border-color); flex-direction:column; gap:8px;">
                                        <div style="display:flex; justify-content:space-between; width:100%; align-items:center; flex-wrap:wrap; gap:8px;">
                                            <div style="display:flex; align-items:center; gap:6px;">
                                                <label style="font-size:0.85rem; margin:0; font-weight:bold; color:var(--text-primary);"><?php echo htmlspecialchars($c['nombre']); ?></label>
                                                <span class="doc-badge" style="font-size:0.68rem; padding:1px 5px;"><?php echo htmlspecialchars($c_code); ?></span>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                <div style="display:flex; align-items:center; gap:4px;">
                                                    <span style="font-size:0.75rem; color:var(--text-muted);">Cód. SKU:</span>
                                                    <input type="text" name="codigo_variante[<?php echo $c['id']; ?>]" value="<?php echo htmlspecialchars($col_sku_val); ?>" placeholder="<?php echo htmlspecialchars(($val_codigo ?: 'PRD') . '-' . $c_code); ?>" class="form-control" style="width:115px; padding:3px 6px; height:auto; font-size:0.8rem; font-weight:700; text-transform:uppercase;">
                                                </div>
                                                <div style="display:flex; align-items:center; gap:4px;">
                                                    <span style="font-size:0.75rem; color:var(--text-muted);">Stock:</span>
                                                    <input type="number" name="colores[<?php echo $c['id']; ?>]" min="0" value="<?php echo $stock_val; ?>" class="form-control" style="width:65px; padding:3px 6px; height:auto; font-size:0.85rem;">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="display:flex; width:100%; align-items:center; gap:10px; background:var(--bg-secondary); padding:6px; border-radius:4px;">
                                            <?php if($col_img_val): ?>
                                                <img src="<?php echo htmlspecialchars($col_img_val); ?>" style="width:35px; height:35px; object-fit:contain; background:#fff; border-radius:4px; border:1px solid var(--border-color);" title="Imagen actual del color">
                                                <input type="hidden" name="imagen_color_actual[<?php echo $c['id']; ?>]" value="<?php echo htmlspecialchars($col_img_val); ?>">
                                            <?php else: ?>
                                                <div style="width:35px; height:35px; background:var(--bg-primary); border-radius:4px; display:flex; align-items:center; justify-content:center; border:1px solid var(--border-color);"><i class="fas fa-image" style="color:var(--text-muted); font-size:0.8rem;"></i></div>
                                            <?php endif; ?>
                                            <input type="file" name="imagen_color[<?php echo $c['id']; ?>]" accept="image/jpeg, image/png, image/webp" style="font-size:0.75rem; width:100%; color:var(--text-muted);" title="Subir nueva foto para este color">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:0.8rem;justify-content:flex-end;margin-top:1.5rem;">
                    <a href="productos.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar producto</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>

<!-- Modal para crear nuevo color dinámico -->
<div id="modalNuevoColor" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center; padding:1rem; backdrop-filter:blur(4px);">
    <div style="background:var(--bg-card); border-radius:12px; border:1px solid var(--border-color); width:100%; max-width:420px; padding:1.5rem; box-shadow:0 15px 35px rgba(0,0,0,0.6); animation:fadeIn 0.25s ease;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
            <h3 style="margin:0; font-size:1.1rem; color:var(--text-primary);"><i class="fas fa-palette" style="color:var(--primary); margin-right:8px;"></i> Crear Nuevo Color / Acabado</h3>
            <button type="button" onclick="closeModalNuevoColor()" style="background:none; border:none; color:var(--text-muted); font-size:1.4rem; cursor:pointer; padding:0;">&times;</button>
        </div>
        <div style="margin-bottom:1rem;">
            <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:6px; font-weight:bold;">Nombre del Color o Acabado <span style="color:#EF5350;">*</span></label>
            <input type="text" id="inputNuevoColor" class="form-control" placeholder="Ej: ROBLE CEMENTO, CEDRO, WENGUE..." style="text-transform:uppercase;">
        </div>
        <div style="margin-bottom:1.2rem;">
            <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:6px; font-weight:bold;">Código Abreviado de Color</label>
            <input type="text" id="inputCodigoColor" class="form-control" placeholder="Ej: ROB-CEM, CED, WEN..." style="text-transform:uppercase; font-weight:700;">
            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:6px; margin-bottom:0;">Si lo dejas en blanco, se generará a partir del nombre.</p>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-outline" onclick="closeModalNuevoColor()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="guardarNuevoColor()"><i class="fas fa-save"></i> Guardar Color</button>
        </div>
    </div>
</div>

<script>
function openModalNuevoColor() {
    document.getElementById('inputNuevoColor').value = '';
    document.getElementById('inputCodigoColor').value = '';
    document.getElementById('modalNuevoColor').style.display = 'flex';
    setTimeout(() => document.getElementById('inputNuevoColor').focus(), 100);
}

function closeModalNuevoColor() {
    document.getElementById('modalNuevoColor').style.display = 'none';
}

function guardarNuevoColor() {
    const input = document.getElementById('inputNuevoColor');
    const inputCode = document.getElementById('inputCodigoColor');
    const val = input.value.trim();
    const code = inputCode ? inputCode.value.trim() : '';

    if (!val) {
        alert('⚠️ Por favor ingresa el nombre del nuevo color.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('nombre', val);
    if (code) formData.append('codigo', code);

    fetch('/carpicenter_sys/views/color_controller.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const color = res.color;
            let existingRow = document.getElementById('color-row-' + color.id);
            if (!existingRow) {
                const container = document.getElementById('coloresContainer');
                const colCode = color.codigo || 'COL' + color.id;
                const prodCode = document.querySelector('input[name="codigo"]')?.value || 'PRD';
                const newRowHtml = `
                    <div id="color-row-${color.id}" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-primary); padding:10px; border-radius:8px; border:1px solid var(--border-color); flex-direction:column; gap:8px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; justify-content:space-between; width:100%; align-items:center; flex-wrap:wrap; gap:8px;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <label style="font-size:0.85rem; margin:0; font-weight:bold; color:var(--text-primary);">${color.nombre}</label>
                                <span class="doc-badge" style="font-size:0.68rem; padding:1px 5px;">${colCode}</span>
                                <span style="font-size:0.7rem; color:var(--green); font-weight:600;">(Nuevo)</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <span style="font-size:0.75rem; color:var(--text-muted);">Cód. SKU:</span>
                                    <input type="text" name="codigo_variante[${color.id}]" value="${prodCode}-${colCode}" class="form-control" style="width:115px; padding:3px 6px; height:auto; font-size:0.8rem; font-weight:700; text-transform:uppercase;">
                                </div>
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <span style="font-size:0.75rem; color:var(--text-muted);">Stock:</span>
                                    <input type="number" name="colores[${color.id}]" min="0" value="0" class="form-control" style="width:65px; padding:3px 6px; height:auto; font-size:0.85rem;">
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; width:100%; align-items:center; gap:10px; background:var(--bg-secondary); padding:6px; border-radius:4px;">
                            <div style="width:35px; height:35px; background:var(--bg-primary); border-radius:4px; display:flex; align-items:center; justify-content:center; border:1px solid var(--border-color);"><i class="fas fa-image" style="color:var(--text-muted); font-size:0.8rem;"></i></div>
                            <input type="file" name="imagen_color[${color.id}]" accept="image/jpeg, image/png, image/webp" style="font-size:0.75rem; width:100%; color:var(--text-muted);" title="Subir nueva foto para este color">
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('afterbegin', newRowHtml);
            }
            closeModalNuevoColor();
        } else {
            alert('❌ ' + (res.message || 'No se pudo guardar el color.'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('❌ Error de conexión al crear el color.');
    });
function handleNombreChange(val) {
    const inputCode = document.getElementById('inputProdCodigo');
    if (!inputCode) return;
    
    // Solo auto-generar si el usuario no ha editado el campo manualmente (o si está creando un nuevo producto)
    <?php if (!$producto_edit): ?>
    if (inputCode.dataset.userEdited === 'true' && inputCode.value.trim() !== '') return;
    
    let upper = val.trim().toUpperCase();
    if (!upper) {
        inputCode.value = '';
        return;
    }

    let pref = 'PR';
    let cleanName = upper;

    if (upper.startsWith('SILLA')) { pref = 'SI'; cleanName = upper.substring(5).trim(); }
    else if (upper.startsWith('BANCO')) { pref = 'BA'; cleanName = upper.substring(5).trim(); }
    else if (upper.startsWith('BUTACA')) { pref = 'BU'; cleanName = upper.substring(6).trim(); }
    else if (upper.startsWith('MESA')) { pref = 'ME'; cleanName = upper.substring(4).trim(); }
    else if (upper.startsWith('SET')) { pref = 'SE'; cleanName = upper.substring(3).trim(); }
    else if (upper.startsWith('ESCRITORIO')) { pref = 'ES'; cleanName = upper.substring(10).trim(); }

    cleanName = cleanName.replace(/[^A-Z0-9]/g, '');
    if (cleanName) {
        const genCode = 'CA-' + pref + '-' + cleanName;
        inputCode.value = genCode;
    }
    <?php endif; ?>
}
</script>
</body>
</html>
