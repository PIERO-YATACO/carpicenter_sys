<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

global $db;

header('Content-Type: application/json; charset=utf-8');

$rawInput   = file_get_contents('php://input');
$input      = !empty($rawInput) ? (json_decode($rawInput, true) ?? []) : [];
$rawMessage = trim($input['message'] ?? $_POST['message'] ?? $_GET['message'] ?? '');
$action     = trim($input['action']  ?? '');  // sub-action for interactive flows
$params     = $input['params'] ?? [];          // extra params for sub-actions

// ============================================================
// Helper: normalise text (lowercase + no accents + no punctuation)
// ============================================================
if (!function_exists('normalizeText')) {
    function normalizeText(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n'
        ]);
        // Strip punctuation and special symbols
        $text = preg_replace('/[^\w\s]/u', '', $text);
        return trim($text);
    }
}

// ============================================================
// Helper: build interactive button HTML rendered in the chat
// ============================================================
if (!function_exists('renderActionButtons')) {
    function renderActionButtons(string $title, array $buttons): string {
        $html  = "<p><strong>{$title}</strong></p>";
        $html .= "<div class='bot-action-row'>";
        foreach ($buttons as $btn) {
            $label   = htmlspecialchars($btn['label']);
            $encData = htmlspecialchars(json_encode($btn['data']), ENT_QUOTES);
            $html .= "<button class='bot-action-btn' onclick='botTriggerAction({$encData})'>{$label}</button>";
        }
        $html .= "</div>";
        return $html;
    }
}

// ============================================================
// Smart Product & Stock Natural Language Search Engine
// ============================================================
if (!function_exists('searchProductStockByMessage')) {
    function searchProductStockByMessage(string $rawMessage, ?PDO $db = null): ?string {
        if (!$db) {
            global $db;
        }
        if (!$db) return null;
        $norm = normalizeText($rawMessage);
        
        // Stop words to remove for term extraction
        $stopWords = ['hay', 'tienen', 'tienes', 'stock', 'de', 'en', 'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'cuanto', 'cuantos', 'cuanta', 'cuantas', 'disponible', 'disponibles', 'precio', 'precios', 'costo', 'quisiera', 'saber', 'por', 'favor', 'hola', 'buenas', 'tardes', 'dias', 'noches', 'amigo', 'gracias', 'tienda', 'almacen', 'existencia', 'existencias', 'tendras', 'para', 'este', 'esta', 'ver', 'dame', 'info', 'informacion'];
        
        $words = preg_split('/\s+/', $norm);
        $keywords = array_values(array_filter($words, function($w) use ($stopWords) {
            return strlen($w) >= 2 && !in_array($w, $stopWords);
        }));
        
        if (empty($keywords)) {
            return null;
        }

        // Check if user requested a specific color
        $allColores = $db->query("SELECT id, nombre FROM colores")->fetchAll(PDO::FETCH_ASSOC);
        $requestedColor = null;
        foreach ($allColores as $c) {
            $colNorm = normalizeText($c['nombre']);
            foreach ($keywords as $kw) {
                if ($colNorm === $kw || (strlen($kw) >= 4 && str_contains($colNorm, $kw))) {
                    $requestedColor = $c['nombre'];
                    break 2;
                }
            }
        }

        // Filter keywords to exclude color name for product searching
        $productKeywords = array_values(array_filter($keywords, function($w) use ($requestedColor) {
            return !$requestedColor || !str_contains(normalizeText($requestedColor), $w);
        }));

        $cleanPhrase = implode(' ', $productKeywords);
        if (empty($cleanPhrase)) {
            $cleanPhrase = implode(' ', $keywords);
        }
        
        // 1. EXACT PHRASE QUERY FIRST
        $stmt = $db->prepare("
            SELECT p.id, p.nombre, p.precio_venta, cat.nombre as categoria_nombre 
            FROM productos p 
            LEFT JOIN categorias cat ON p.categoria_id = cat.id 
            WHERE p.nombre ILIKE :term 
            ORDER BY LENGTH(p.nombre) ASC, p.nombre ASC 
            LIMIT 5
        ");
        $stmt->execute([':term' => "%{$cleanPhrase}%"]);
        $matchedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. If no exact phrase match, try phrase with keywords joined by %
        if (empty($matchedProducts) && count($productKeywords) > 1) {
            $likePattern = '%' . implode('%', $productKeywords) . '%';
            $stmt->execute([':term' => $likePattern]);
            $matchedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. If STILL no match, try searching by individual long words
        if (empty($matchedProducts)) {
            $longWords = array_filter($productKeywords, fn($w) => strlen($w) >= 4);
            if (!empty($longWords)) {
                foreach ($longWords as $kw) {
                    $stmt->execute([':term' => "%{$kw}%"]);
                    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($res)) {
                        $matchedProducts = array_merge($matchedProducts, $res);
                    }
                }
                $unique = [];
                foreach ($matchedProducts as $mp) {
                    $unique[$mp['id']] = $mp;
                }
                $matchedProducts = array_values($unique);
            }
        }

        if (empty($matchedProducts)) {
            return null;
        }

        // IF MULTIPLE PRODUCTS MATCHED (> 1) and ambiguous query:
        if (count($matchedProducts) > 1) {
            $exactOne = null;
            foreach ($matchedProducts as $mp) {
                if (normalizeText($mp['nombre']) === $cleanPhrase) {
                    $exactOne = $mp;
                    break;
                }
            }
            if ($exactOne) {
                $matchedProducts = [$exactOne];
            } else {
                $buttons = [];
                foreach ($matchedProducts as $prod) {
                    $buttons[] = [
                        'label' => '🪑 ' . $prod['nombre'],
                        'data'  => ['action' => 'stock_producto', 'prod_id' => (int)$prod['id'], 'prod_nombre' => $prod['nombre']]
                    ];
                }
                return renderActionButtons("🔍 Encontré " . count($matchedProducts) . " productos relacionados. ¿Cuál deseas consultar?", $buttons);
            }
        }

        // EXACT SINGLE PRODUCT DISPLAY
        $prod = $matchedProducts[0];
        $prodId = $prod['id'];
        $prodNombre = $prod['nombre'];
        $precio = number_format($prod['precio_venta'], 2);
        $catNombre = $prod['categoria_nombre'] ?? 'Muebles';

        $stmtInv = $db->prepare("
            SELECT c.nombre AS color_nombre, l.nombre AS local_nombre, COALESCE(il.stock_actual, 0) AS stock_qty
            FROM producto_colores pc
            JOIN colores c ON pc.color_id = c.id
            LEFT JOIN inventario_local il ON il.producto_id = pc.producto_id AND il.color_id = pc.color_id
            LEFT JOIN locales l ON il.local_id = l.id
            WHERE pc.producto_id = :pid
            ORDER BY c.nombre ASC, l.nombre ASC
        ");
        $stmtInv->execute([':pid' => $prodId]);
        $invRows = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

        $byColor = [];
        $totalStock = 0;
        foreach ($invRows as $r) {
            $cName = $r['color_nombre'];
            $lName = $r['local_nombre'] ?: 'Almacén Principal';
            $qty = (int)$r['stock_qty'];
            if (!isset($byColor[$cName])) {
                $byColor[$cName] = [
                    'locales' => [],
                    'total_color' => 0
                ];
            }
            $byColor[$cName]['locales'][$lName] = ($byColor[$cName]['locales'][$lName] ?? 0) + $qty;
            $byColor[$cName]['total_color'] += $qty;
            $totalStock += $qty;
        }

        $colorFoundAndHasStock = false;
        if ($requestedColor && isset($byColor[$requestedColor])) {
            if ($byColor[$requestedColor]['total_color'] > 0) {
                $colorFoundAndHasStock = true;
            }
        }

        $html = "";
        if ($totalStock > 0) {
            $html .= "<p>¡Hola! 😊 Sí tenemos <strong>{$prodNombre}</strong> disponible en stock.</p>";
            if ($requestedColor) {
                if ($colorFoundAndHasStock) {
                    $html .= "<p style='color:#2E7D32; font-weight:bold; margin-bottom:4px;'>✅ Stock en color <strong>" . strtoupper($requestedColor) . "</strong>: " . $byColor[$requestedColor]['total_color'] . " un.</p>";
                } else {
                    $html .= "<p style='color:#D32F2F; font-weight:bold; margin-bottom:4px;'>⚠️ Sin stock en color <strong>" . strtoupper($requestedColor) . "</strong> 😔, pero tenemos en otros colores:</p>";
                }
            }
            
            $html .= "<div class='bot-card'>";
            $html .= "<div class='bot-card-header'><strong>🪑 {$prodNombre}</strong> <span class='bot-badge' style='background:#7CA81B; color:#fff;'>S/ {$precio}</span></div>";
            $html .= "<div class='bot-card-body'>";
            $html .= "<p>🏷️ <strong>Categoría:</strong> {$catNombre}</p>";
            $html .= "<p>📊 <strong>Stock Total:</strong> <strong style='color:#2E7D32;'>{$totalStock} unidades</strong></p>";
            $html .= "<hr style='border:0; border-top:1px dashed #eee; margin:0.5rem 0;'>";
            $html .= "<p>🎨 <strong>Desglose por Color y Tienda:</strong></p>";
            
            foreach ($byColor as $cName => $cData) {
                $colorBadge = $cData['total_color'] > 0 ? "<span style='color:#2E7D32; font-weight:bold;'>{$cData['total_color']} un.</span>" : "<span style='color:#999;'>Sin stock</span>";
                $html .= "<div style='margin-bottom:0.3rem; padding:4px 8px; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; font-size:0.84rem;'>";
                $html .= "<strong>• Color {$cName}:</strong> {$colorBadge}<br>";
                foreach ($cData['locales'] as $locName => $lQty) {
                    if ($lQty > 0) {
                        $html .= "<span style='font-size:0.78rem; color:#64748b; margin-left:12px;'>🏬 {$locName}: {$lQty} un.</span><br>";
                    }
                }
                $html .= "</div>";
            }

            $html .= "</div></div>";
            $html .= "<div style='margin-top:0.6rem;'><a href='/carpicenter_sys/modules/cotizaciones/cotizacion_form.php' class='bot-action-btn' style='text-decoration:none; display:inline-block; width:100%; text-align:center;'>📝 Cotizar este producto</a></div>";

        } else {
            $html .= "<p>¡Hola! 😊 Lamento informarte que en este momento <strong>no contamos con stock disponible</strong> de <strong>{$prodNombre}</strong> en nuestros almacenes.</p>";
            $html .= "<div class='bot-card' style='border-left:4px solid #EF5350;'>";
            $html .= "<div class='bot-card-header'><strong style='color:#D32F2F;'>❌ Sin stock inmediato de {$prodNombre}</strong></div>";
            $html .= "<div class='bot-card-body'>";
            $html .= "<p>🏷️ <strong>Precio de Lista:</strong> S/ {$precio}</p>";
            $html .= "<p>💡 <strong>¿Qué puedes hacer?</strong></p>";
            $html .= "<p style='font-size:0.8rem; color:#64748b;'>Puedes registrar una <strong>Cotización</strong> o <strong>Contrato a Pedido</strong> para mandar a fabricar o reservar este modelo.</p>";
            $html .= "</div></div>";
            $html .= "<div style='margin-top:0.6rem;'><a href='/carpicenter_sys/modules/cotizaciones/cotizacion_form.php' class='bot-action-btn' style='text-decoration:none; display:inline-block; width:100%; text-align:center;'>➕ Crear Cotización para {$prodNombre}</a></div>";
        }

        return $html;
    }
}

// ============================================================
// Default suggestions shown after every reply
// ============================================================
$suggestions = [
    "📦 Stock de Productos",
    "📄 Estado de Contrato",
    "💰 Ventas de Hoy",
    "🚚 Entregas Próximas",
    "❓ Ayuda del Sistema"
];

$reply = "";

try {

    // ==================================================================
    // 1. INTERACTIVE BUTTON SUB-ACTIONS (WHEN BUTTON CLICKED)
    // ==================================================================

    if (!empty($action)) {

        if ($action === 'stock_inicio') {
            $stmtCat = $db->query("
                SELECT DISTINCT cat.id, cat.nombre
                FROM categorias cat
                JOIN productos p ON p.categoria_id = cat.id
                ORDER BY cat.nombre ASC
            ");
            $cats = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            $buttons = [[
                'label' => '📦 Todos los productos',
                'data'  => ['action' => 'stock_categoria', 'cat_id' => 0, 'cat_nombre' => 'Todos']
            ]];
            foreach ($cats as $cat) {
                $buttons[] = [
                    'label' => '🪑 ' . $cat['nombre'],
                    'data'  => ['action' => 'stock_categoria', 'cat_id' => (int)$cat['id'], 'cat_nombre' => $cat['nombre']]
                ];
            }

            $reply = renderActionButtons('📦 ¿De qué categoría quieres ver el stock?', $buttons);
        }

        elseif ($action === 'stock_categoria') {
            $catId    = (int)($params['cat_id'] ?? 0);
            $catNombre= htmlspecialchars($params['cat_nombre'] ?? 'Todos');

            if ($catId > 0) {
                $stmtP = $db->prepare("SELECT p.id, p.nombre FROM productos p WHERE p.categoria_id = :cat ORDER BY p.nombre ASC");
                $stmtP->execute([':cat' => $catId]);
            } else {
                $stmtP = $db->query("SELECT id, nombre FROM productos ORDER BY nombre ASC");
            }
            $products = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            if (empty($products)) {
                $reply = "🔍 No hay productos registrados en la categoría <strong>{$catNombre}</strong>.";
            } else {
                $buttons = [[
                    'label' => '📋 Todos los productos de ' . $catNombre,
                    'data'  => ['action' => 'stock_producto', 'prod_id' => 0, 'prod_nombre' => 'Todos en ' . $catNombre, 'cat_id' => $catId]
                ]];
                foreach ($products as $prod) {
                    $buttons[] = [
                        'label' => $prod['nombre'],
                        'data'  => ['action' => 'stock_producto', 'prod_id' => (int)$prod['id'], 'prod_nombre' => $prod['nombre'], 'cat_id' => $catId]
                    ];
                }
                $reply = renderActionButtons("🪑 Productos en <em>{$catNombre}</em> — ¿Cuál te interesa?", $buttons);
            }
        }

        elseif ($action === 'stock_producto') {
            $prodId    = (int)($params['prod_id'] ?? 0);
            $prodNombre= htmlspecialchars($params['prod_nombre'] ?? 'Todos');
            $catId     = (int)($params['cat_id'] ?? 0);

            $locales = $db->query("SELECT id, nombre FROM locales ORDER BY tipo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

            $buttons = [[
                'label' => '🌐 Todos los locales',
                'data'  => ['action' => 'stock_resultado', 'prod_id' => $prodId, 'prod_nombre' => $prodNombre, 'local_id' => 0, 'local_nombre' => 'Todos']
            ]];
            foreach ($locales as $loc) {
                $buttons[] = [
                    'label' => '🏬 ' . $loc['nombre'],
                    'data'  => ['action' => 'stock_resultado', 'prod_id' => $prodId, 'prod_nombre' => $prodNombre, 'local_id' => (int)$loc['id'], 'local_nombre' => $loc['nombre']]
                ];
            }
            $reply = renderActionButtons("🏬 ¿En qué local quieres ver el stock de <em>{$prodNombre}</em>?", $buttons);
        }

        elseif ($action === 'stock_resultado') {
            $prodId    = (int)($params['prod_id'] ?? 0);
            $prodNombre= $params['prod_nombre'] ?? 'Todos';
            $localId   = (int)($params['local_id'] ?? 0);
            $localNombre = $params['local_nombre'] ?? 'Todos';

            $whereParts = [];
            $bindParams = [];

            if ($prodId > 0) {
                $whereParts[] = 'p.id = :prod_id';
                $bindParams[':prod_id'] = $prodId;
            }
            if ($localId > 0) {
                $whereParts[] = 'il.local_id = :local_id';
                $bindParams[':local_id'] = $localId;
            }

            $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

            $stmtR = $db->prepare("
                SELECT
                    p.nombre  AS prod_nombre,
                    c.nombre  AS color_nombre,
                    p.precio_venta,
                    cat.nombre AS categoria,
                    l.nombre  AS local_nombre,
                    COALESCE(il.stock_actual, 0) AS stock_qty
                FROM inventario_local il
                JOIN productos p ON il.producto_id = p.id
                JOIN colores   c ON il.color_id    = c.id
                JOIN locales   l ON il.local_id    = l.id
                LEFT JOIN categorias cat ON p.categoria_id = cat.id
                {$where}
                ORDER BY p.nombre ASC, c.nombre ASC, l.nombre ASC
            ");
            $stmtR->execute($bindParams);
            $rows = $stmtR->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $reply = "🔍 No se encontraron registros de stock con los filtros seleccionados.";
            } else {
                $grouped = [];
                foreach ($rows as $row) {
                    $key = $row['prod_nombre'] . '||' . $row['color_nombre'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'prod_nombre'   => $row['prod_nombre'],
                            'color_nombre'  => $row['color_nombre'],
                            'precio_venta'  => $row['precio_venta'],
                            'categoria'     => $row['categoria'],
                            'locales'       => [],
                            'total'         => 0,
                        ];
                    }
                    $grouped[$key]['locales'][$row['local_nombre']] = (int)$row['stock_qty'];
                    $grouped[$key]['total'] += (int)$row['stock_qty'];
                }

                $reply = "<p><strong>📦 Stock " . ($prodId > 0 ? "de <em>{$prodNombre}</em>" : "") . "</strong> — " . count($grouped) . " variante(s) encontrada(s):</p>";
                $count = 0;
                foreach ($grouped as $item) {
                    if ($count >= 10) break;
                    $totalQty = $item['total'];
                    $totalColor = $totalQty > 0 ? '#66BB6A' : '#EF5350';

                    $localesHtml = "";
                    foreach ($item['locales'] as $lnombre => $qty) {
                        $qStyle = $qty > 0 ? 'color:#66BB6A; font-weight:bold;' : 'color:#999;';
                        $icon   = $qty > 0 ? '✅' : '⬜';
                        $localesHtml .= "<span class='bot-stock-row'>{$icon} <span class='bot-stock-local'>" . htmlspecialchars($lnombre) . "</span>: <span style='{$qStyle}'>{$qty} un.</span></span>";
                    }

                    $reply .= "
                    <div class='bot-card'>
                        <div class='bot-card-header'>
                            <strong>" . htmlspecialchars($item['prod_nombre']) . " <span style='opacity:.7;font-weight:400;'>(" . htmlspecialchars($item['color_nombre']) . ")</span></strong>
                            <span class='bot-badge' style='background:#7CA81B; color:#fff;'>S/ " . number_format($item['precio_venta'], 2) . "</span>
                        </div>
                        <div class='bot-card-body'>
                            <p>🏷️ <strong>Categoría:</strong> " . htmlspecialchars($item['categoria'] ?? 'Muebles') . "</p>
                            <div class='bot-stock-grid'>{$localesHtml}</div>
                            <p class='bot-stock-total'>📊 Total: <strong style='color:{$totalColor};'>{$totalQty} unidades</strong></p>
                        </div>
                    </div>";
                    $count++;
                }
            }
            $reply .= "<div style='margin-top:0.6rem;'><button class='bot-action-btn bot-action-secondary' onclick='botTriggerAction({\"action\":\"stock_inicio\"})'>🔄 Nueva consulta de stock</button></div>";
        }
    }

    // ==================================================================
    // 2. NATURAL LANGUAGE WRITTEN MESSAGES FROM USER
    // ==================================================================
    elseif (!empty($rawMessage)) {
        
        // A. Smart Product & Stock Lookup by Name / Color
        $smartStockReply = searchProductStockByMessage($rawMessage, $db);
        if ($smartStockReply !== null) {
            $reply = $smartStockReply;
        }

        // B. Contrato Lookup (ej. T003-00912 o números)
        elseif (
            preg_match('/t003[-\s]?(\d+)/i', $rawMessage, $matches) ||
            preg_match('/\b(\d{4,5})\b/', $rawMessage, $matches) ||
            str_contains(normalizeText($rawMessage), 'contrato') ||
            str_contains(normalizeText($rawMessage), 'pedido')
        ) {
            $searchTerm = '%' . (isset($matches[1]) ? $matches[1] : trim($rawMessage)) . '%';

            $stmt = $db->prepare("
                SELECT c.*, cli.nombre as cliente_nombre, l.nombre as local_nombre
                FROM contratos c
                LEFT JOIN clientes cli ON c.cliente_id = cli.id
                LEFT JOIN locales l ON c.local_id = l.id
                WHERE c.codigo_completo ILIKE :term OR c.numero LIKE :term OR cli.nombre ILIKE :term
                ORDER BY c.id DESC LIMIT 3
            ");
            $stmt->execute([':term' => $searchTerm]);
            $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($contratos)) {
                $reply .= "<p>📄 <strong>Se encontraron " . count($contratos) . " contrato(s):</strong></p>";
                foreach ($contratos as $c) {
                    $estadoColor = match($c['estado_contrato']) {
                        'Pendiente'           => '#FFA726',
                        'En Producción'       => '#42A5F5',
                        'Listo para Entrega'  => '#AB47BC',
                        'Entregado'           => '#66BB6A',
                        default               => '#EF5350'
                    };

                    $reply .= "
                    <div class='bot-card'>
                        <div class='bot-card-header'>
                            <strong>Contrato {$c['codigo_completo']}</strong>
                            <span class='bot-badge' style='background:{$estadoColor}; color:#fff;'>{$c['estado_contrato']}</span>
                        </div>
                        <div class='bot-card-body'>
                            <p>👤 <strong>Cliente:</strong> " . htmlspecialchars($c['cliente_nombre'] ?? 'Cliente General') . "</p>
                            <p>🏬 <strong>Tienda:</strong> " . htmlspecialchars($c['local_nombre'] ?? '—') . "</p>
                            <p>💰 <strong>Total:</strong> S/ " . number_format($c['monto_total'], 2) . " | <strong>Saldo:</strong> <span style='color:#EF5350; font-weight:bold;'>S/ " . number_format($c['monto_saldo'], 2) . "</span></p>
                        </div>
                        <a href='/carpicenter_sys/modules/contratos/contrato_view.php?id={$c['id']}' class='bot-btn-link'>Ver Ficha Completa ➔</a>
                    </div>";
                }
            } else {
                $reply = "🔍 No encontré contratos con el término <strong>" . htmlspecialchars($rawMessage) . "</strong>.";
            }
        }

        // C. Resumen de Ventas
        elseif (
            str_contains(normalizeText($rawMessage), 'venta') ||
            str_contains(normalizeText($rawMessage), 'vendimos') ||
            str_contains(normalizeText($rawMessage), 'recaudado')
        ) {
            $today = date('Y-m-d');
            $stmtVta = $db->prepare("SELECT COUNT(*) as total_count, COALESCE(SUM(total), 0) as total_monto FROM ventas WHERE DATE(fecha) = :today");
            $stmtVta->execute([':today' => $today]);
            $vtaData = $stmtVta->fetch(PDO::FETCH_ASSOC);

            $stmtCtr = $db->prepare("SELECT COUNT(*) as total_count, COALESCE(SUM(monto_adelanto), 0) as total_adelanto FROM contratos WHERE DATE(fecha_emision) = :today");
            $stmtCtr->execute([':today' => $today]);
            $ctrData = $stmtCtr->fetch(PDO::FETCH_ASSOC);

            $totalRecaudadoHoy = floatval($vtaData['total_monto']) + floatval($ctrData['total_adelanto']);

            $reply = "
            <div class='bot-card'>
                <div class='bot-card-header'><strong>💰 Resumen de Ventas de Hoy (" . date('d/m/Y') . ")</strong></div>
                <div class='bot-card-body'>
                    <p>🛒 <strong>Ventas Directas:</strong> {$vtaData['total_count']} comprobante(s) por S/ " . number_format($vtaData['total_monto'], 2) . "</p>
                    <p>💵 <strong>Adelantos en Contratos:</strong> S/ " . number_format($ctrData['total_adelanto'], 2) . "</p>
                    <hr style='border:0; border-top:1px dashed #ccc; margin:0.6rem 0;'>
                    <p style='font-size:1.1rem; font-weight:800; color:#66BB6A;'>Total Ingresado Hoy: S/ " . number_format($totalRecaudadoHoy, 2) . "</p>
                </div>
            </div>";
        }

        // D. Entregas Próximas
        elseif (
            str_contains(normalizeText($rawMessage), 'entrega') ||
            str_contains(normalizeText($rawMessage), 'despacho') ||
            str_contains(normalizeText($rawMessage), 'envio')
        ) {
            $stmt = $db->query("
                SELECT c.*, cli.nombre as cliente_nombre
                FROM contratos c
                LEFT JOIN clientes cli ON c.cliente_id = cli.id
                WHERE c.estado_contrato NOT IN ('Entregado', 'Anulado')
                ORDER BY c.fecha_entrega_estimada ASC LIMIT 4
            ");
            $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($entregas)) {
                $reply .= "<p>🚚 <strong>Próximas entregas y despachos programados:</strong></p>";
                foreach ($entregas as $e) {
                    $fEnt = !empty($e['fecha_entrega_estimada']) ? date('d/m/Y', strtotime($e['fecha_entrega_estimada'])) : 'Sin fecha';
                    $reply .= "
                    <div class='bot-card'>
                        <div class='bot-card-header'>
                            <strong>Contrato {$e['codigo_completo']}</strong>
                            <span class='bot-badge' style='background:#2196F3; color:#fff;'>{$e['tipo_entrega']}</span>
                        </div>
                        <div class='bot-card-body'>
                            <p>📅 <strong>Fecha Entrega:</strong> <strong style='color:#EF5350;'>{$fEnt}</strong></p>
                            <p>👤 <strong>Cliente:</strong> " . htmlspecialchars($e['cliente_nombre'] ?? 'Cliente General') . "</p>
                        </div>
                    </div>";
                }
            } else {
                $reply = "🎉 No hay entregas pendientes en este momento.";
            }
        }

        // E. Saludos
        elseif (
            str_contains(normalizeText($rawMessage), 'hola') ||
            str_contains(normalizeText($rawMessage), 'buenas') ||
            str_contains(normalizeText($rawMessage), 'buenos dias') ||
            str_contains(normalizeText($rawMessage), 'buenas tardes')
        ) {
            $reply = "¡Hola! 😊 Soy <strong>Carpibot</strong>, tu asistente del sistema. ¿En qué te puedo colaborar hoy?<br><br>"
                   . "Puedes preguntarme cosas como:<br>"
                   . "• <em>'¿Hay Banco Capri en stock?'</em><br>"
                   . "• <em>'¿Tienen Silla Mica en color negro?'</em><br>"
                   . "• <em>'Ver ventas de hoy'</em><br>"
                   . "• <em>'Buscar contrato T003-00912'</em>";
        }

        // F. Default Fallback if product was not found or intent unclear
        else {
            $reply = "¡Hola! 😊 No pude encontrar un producto o registro con el término <strong>\"" . htmlspecialchars($rawMessage) . "\"</strong>.<br><br>"
                   . "🔍 <strong>Puedes consultar de estas formas:</strong><br>"
                   . "• Escribe el nombre del producto (ej. <code>Banco Capri</code>, <code>Silla Mica</code>, <code>Mesa Roble</code>)<br>"
                   . "• Especifica un color (ej. <code>Banco Capri en negro</code>)<br>"
                   . "• O usa los botones interactivos de <strong>📦 Consultar Stock</strong>.";
        }
    }

    echo json_encode([
        'status'      => 'success',
        'reply'       => $reply,
        'suggestions' => $suggestions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'reply'  => '⚠️ Error al procesar la consulta: ' . htmlspecialchars($e->getMessage())
    ]);
}
?>
