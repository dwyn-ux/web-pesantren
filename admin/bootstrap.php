<?php
// Bootstrap untuk admin — di-include di awal setiap file admin
// agar bisa diakses langsung (tanpa lewat index.php)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    require_once ROOT_PATH . '/config/constants.php';
    require_once ROOT_PATH . '/config/session.php';
    require_once ROOT_PATH . '/config/database.php';
    require_once ROOT_PATH . '/includes/functions.php';
    require_once ROOT_PATH . '/includes/auth.php';
}
