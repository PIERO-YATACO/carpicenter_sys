<?php
date_default_timezone_set('America/Lima');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /carpicenter_sys/index.php");
    exit();
}

$user_role = $_SESSION['rol_nombre'] ?? '';
$user_local_id = $_SESSION['local_id'] ?? null;
$user_local_nombre = $_SESSION['local_nombre'] ?? 'Sin Local';
$is_admin = in_array(strtolower($user_role), ['super admin', 'admin', 'administrador']);

if (!function_exists('hasAccess')) {
    // Function to check if user has access to a specific module
    function hasAccess($module) {
        global $user_role, $is_admin;
        
        // Super Admin / Administrador siempre tiene acceso total (100%)
        if ($is_admin || empty($user_role) || in_array(strtolower($user_role), ['super admin', 'admin', 'administrador'])) {
            return true;
        }

        $permissions = [
            'dashboard'      => ['Vendedor', 'Vendedora', 'Contabilidad', 'Almacén', 'Producción'],
            'catalogos'      => ['Vendedor', 'Vendedora', 'Contabilidad', 'Almacén', 'Producción'],
            'productos'      => ['Vendedor', 'Vendedora', 'Contabilidad', 'Almacén', 'Producción'],
            'materiales'     => ['Producción', 'Almacén', 'Contabilidad'],
            'produccion'     => ['Producción', 'Almacén'],
            'cotizaciones'   => ['Vendedor', 'Vendedora', 'Contabilidad'],
            'ventas'         => ['Vendedor', 'Vendedora', 'Contabilidad'],
            'notas_venta'    => ['Vendedor', 'Vendedora', 'Contabilidad'],
            'guias'          => ['Vendedor', 'Vendedora', 'Almacén', 'Contabilidad'],
            'contratos'      => ['Vendedor', 'Vendedora', 'Contabilidad', 'Producción', 'Almacén'],
            'transferencias' => ['Vendedor', 'Vendedora', 'Almacén'],
            'ordenes_egreso' => ['Almacén', 'Producción', 'Contabilidad'],
            'compras'        => ['Contabilidad', 'Almacén'],
            'inventario'     => ['Vendedor', 'Vendedora', 'Almacén', 'Contabilidad'],
            'kardex'         => ['Almacén', 'Contabilidad'],
            'finanzas'       => ['Contabilidad'],
            'cuentas_pagar'  => ['Contabilidad'],
            'cuadre_caja'    => ['Contabilidad'],
            'planilla'       => ['Contabilidad'],
            'proveedores'    => ['Contabilidad', 'Almacén'],
            'clientes'       => ['Vendedor', 'Vendedora', 'Contabilidad'],
            'reportes'       => ['Contabilidad'],
            'usuarios'       => [], // Solo Super Admin
            'roles'          => [], // Solo Super Admin
            'configuracion'  => [], // Solo Super Admin
            'perfil'         => ['Vendedor', 'Vendedora', 'Contabilidad', 'Almacén', 'Producción']
        ];

        $allowedRoles = $permissions[$module] ?? [];
        return in_array($user_role, $allowedRoles, true);
    }
}
