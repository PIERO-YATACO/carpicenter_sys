<?php $current_page = basename($_SERVER['PHP_SELF'], '.php'); ?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="/carpicenter_sys/assets/img/logo_carpicenter_official.png?v=3" alt="Industrias Carpicenter Logo">
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Principal</div>
            <a href="/carpicenter_sys/views/dashboard.php" class="nav-item <?= $current_page=='dashboard'?'active':''; ?>">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
        
        <?php if(hasAccess('productos') || hasAccess('produccion')): ?>
        <div class="nav-section">
            <div class="nav-section-title">Gestión</div>
            <?php if(hasAccess('productos')): ?>
            <a href="/carpicenter_sys/modules/catalogos/catalogos.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'catalogos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Catálogo Digital
            </a>
            <a href="/carpicenter_sys/views/productos.php" class="nav-item <?= $current_page=='productos'?'active':''; ?>">
                <i class="fas fa-box"></i> Productos
            </a>
            <?php endif; ?>
            <?php if(hasAccess('produccion')): ?>
            <a href="/carpicenter_sys/views/materiales.php" class="nav-item <?= $current_page=='materiales'?'active':''; ?>">
                <i class="fas fa-layer-group"></i> Materiales
            </a>
            <a href="/carpicenter_sys/views/produccion.php" class="nav-item <?= $current_page=='produccion'?'active':''; ?>">
                <i class="fas fa-hammer"></i> Producción
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-section-title">Comercial e Inventario</div>
            <?php if(hasAccess('cotizaciones')): ?>
            <a href="/carpicenter_sys/modules/cotizaciones/cotizaciones.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'cotizaciones') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i> Cotizaciones
            </a>
            <?php endif; ?>
            <?php if(hasAccess('ventas')): ?>
            <a href="/carpicenter_sys/views/ventas.php" class="nav-item <?= $current_page=='ventas'?'active':''; ?>">
                <i class="fas fa-shopping-bag"></i> Ventas
            </a>
            <a href="/carpicenter_sys/modules/notas_venta/notas_venta.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'notas_venta') !== false ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Notas de Venta
            </a>
            <a href="/carpicenter_sys/views/guias.php" class="nav-item <?= $current_page=='guias'?'active':''; ?>">
                <i class="fas fa-truck"></i> Guías de Remisión
            </a>
            <?php endif; ?>
            <?php if(hasAccess('contratos')): ?>
            <a href="/carpicenter_sys/modules/contratos/contratos.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'contratos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-contract"></i> Contratos
            </a>
            <?php endif; ?>
            <?php if(hasAccess('transferencias')): ?>
            <a href="/carpicenter_sys/modules/transferencias/transferencias.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'transferencias') !== false ? 'active' : ''; ?>">
                <i class="fas fa-truck-loading"></i> Transferencias
            </a>
            <a href="/carpicenter_sys/modules/ordenes_egreso/ordenes_egreso.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'ordenes_egreso') !== false ? 'active' : ''; ?>">
                <i class="fas fa-boxes-packing"></i> Órdenes de Egreso
            </a>
            <?php endif; ?>
            <?php if(hasAccess('compras')): ?>
            <a href="/carpicenter_sys/views/compras.php" class="nav-item <?= $current_page=='compras'?'active':''; ?>">
                <i class="fas fa-cart-plus"></i> Compras
            </a>
            <?php endif; ?>
            <?php if(hasAccess('inventario')): ?>
            <a href="/carpicenter_sys/modules/inventario/existencias.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'existencias') !== false ? 'active' : ''; ?>">
                <i class="fas fa-boxes-stacked"></i> Inventario
            </a>
            <a href="/carpicenter_sys/views/kardex.php" class="nav-item <?= $current_page=='kardex'?'active':''; ?>">
                <i class="fas fa-clipboard-list"></i> Kardex
            </a>
            <?php endif; ?>
        </div>

        <?php if(hasAccess('finanzas')): ?>
        <div class="nav-section">
            <div class="nav-section-title">Finanzas y Tesorería</div>
            <a href="/carpicenter_sys/modules/finanzas/resumen_financiero.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'resumen_financiero') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Resumen
            </a>
            <a href="/carpicenter_sys/modules/finanzas/cuentas_cobrar.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'cuentas_cobrar') !== false ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-usd"></i> Por Cobrar
            </a>
            <a href="/carpicenter_sys/modules/finanzas/obligaciones_bancarias.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'obligaciones_bancarias') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> Por Pagar
            </a>
            <a href="/carpicenter_sys/modules/finanzas/cuadre_caja.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'cuadre_caja') !== false ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i> Cuadre de Caja
            </a>
            <a href="/carpicenter_sys/modules/finanzas/impuestos_fraccionamientos.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'impuestos_fraccionamientos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-university"></i> Impuestos
            </a>
            <a href="/carpicenter_sys/modules/finanzas/planilla.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'planilla') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> Planilla Semanal
            </a>
            <a href="/carpicenter_sys/modules/finanzas/personal.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'personal') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users-gear"></i> Personal
            </a>
            <a href="/carpicenter_sys/modules/finanzas/gastos_permisos.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'gastos_permisos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-building-circle-check"></i> Gastos Fijos
            </a>
        </div>
        <?php endif; ?>

        <?php if(hasAccess('compras') || hasAccess('cotizaciones') || hasAccess('clientes')): ?>
        <div class="nav-section">
            <div class="nav-section-title">Contactos</div>
            <?php if(hasAccess('proveedores')): ?>
            <a href="/carpicenter_sys/views/proveedores.php" class="nav-item <?= $current_page=='proveedores'?'active':''; ?>">
                <i class="fas fa-truck"></i> Proveedores
            </a>
            <?php endif; ?>
            <?php if(hasAccess('clientes')): ?>
            <a href="/carpicenter_sys/views/clientes.php" class="nav-item <?= $current_page=='clientes'?'active':''; ?>">
                <i class="fas fa-users"></i> Clientes
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-section-title">Sistema</div>
            <?php if(hasAccess('reportes')): ?>
            <a href="/carpicenter_sys/views/reportes.php" class="nav-item <?= $current_page=='reportes'?'active':''; ?>">
                <i class="fas fa-chart-bar"></i> Reportes
            </a>
            <?php endif; ?>
            <?php if(hasAccess('usuarios')): ?>
            <a href="/carpicenter_sys/views/usuarios.php" class="nav-item <?= $current_page=='usuarios'?'active':''; ?>">
                <i class="fas fa-user-cog"></i> Usuarios
            </a>
            <a href="/carpicenter_sys/views/roles.php" class="nav-item <?= $current_page=='roles'?'active':''; ?>">
                <i class="fas fa-shield-alt"></i> Roles y Permisos
            </a>
            <a href="/carpicenter_sys/views/configuracion.php" class="nav-item <?= $current_page=='configuracion'?'active':''; ?>">
                <i class="fas fa-cog"></i> Configuración
            </a>
            <?php endif; ?>
            <a href="/carpicenter_sys/views/perfil.php" class="nav-item <?= $current_page=='perfil'?'active':''; ?>">
                <i class="fas fa-user-circle"></i> Mi Perfil
            </a>
            <a href="#" onclick="confirmLogout(event)" class="nav-item" style="color:var(--primary-light);">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <a href="/carpicenter_sys/views/perfil.php" style="text-decoration:none; color:inherit; display:block;">
            <div class="sidebar-user">
                <?php if(!empty($_SESSION['foto_url'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['foto_url']) ?>" alt="Avatar" class="avatar" style="object-fit: cover; border: 1px solid var(--border-color);">
                <?php else: ?>
                    <div class="avatar"><?= strtoupper(substr($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                <?php endif; ?>
                <div class="user-info">
                    <span><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Administrador') ?></span>
                    <small><?= htmlspecialchars($_SESSION['email'] ?? 'admin@carpicenter.com') ?></small>
                </div>
            </div>
        </a>
    </div>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (sidebarNav) {
            // Restaurar posición
            const savedScroll = localStorage.getItem('sidebarScrollPos');
            if (savedScroll) {
                sidebarNav.scrollTop = savedScroll;
            }

            // Guardar posición al salir de la página o hacer clic
            window.addEventListener('beforeunload', function() {
                localStorage.setItem('sidebarScrollPos', sidebarNav.scrollTop);
            });
        }
    });
</script>
