<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['rol_nombre'] = 'Super Admin';
$_SESSION['local_id'] = 1;
$_SESSION['local_nombre'] = 'Central';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'c:/xampp/htdocs/carpicenter_sys/modules/contratos/contratos.php';
