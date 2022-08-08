<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

spl_autoload_register(function ($class) {
   $root = dirname(__DIR__);
   $file = $root . '/' . str_replace('\\', '/', $class) . '.php';
   if (is_readable($file)) {
       require_once $root . '/' . str_replace('\\', '/', $class) . '.php';
   }
});

set_error_handler('\Core\Error::errorHandler');
set_exception_handler('\Core\Error::exceptionHandler');

require_once '../vendor/autoload.php';
require_once('../env.php');

$route = new \Core\Router;

$route->add('', ['controller' => 'Index', 'action' => 'main', 'namespace' => '']);
$route->add('index/ajax', ['controller' => 'Index', 'action' => 'ajax', 'namespace' => '']);

$route->add('admin', ['controller' => 'Admin', 'action' => 'main', 'namespace' => '']);
$route->add('admin/login', ['controller' => 'Admin', 'action' => 'login', 'namespace' => '']);
$route->add('admin/logout', ['controller' => 'Admin', 'action' => 'logout', 'namespace' => '']);
$route->add('admin/settings', ['controller' => 'Admin', 'action' => 'settings', 'namespace' => '']);
$route->add('admin/users', ['controller' => 'Admin', 'action' => 'users', 'namespace' => '']);
$route->add('admin/pages', ['controller' => 'Admin', 'action' => 'pages', 'namespace' => '']);
$route->add('admin/products', ['controller' => 'Admin', 'action' => 'products', 'namespace' => '']);
$route->add('admin/ref_chars', ['controller' => 'Admin', 'action' => 'ref_chars', 'namespace' => '']);
$route->add('admin/ref_chars_values', ['controller' => 'Admin', 'action' => 'ref_chars_values', 'namespace' => '']);
$route->add('admin/ajax', ['controller' => 'Admin', 'action' => 'ajax', 'namespace' => '']);
$route->add('admin/orders', ['controller' => 'Admin', 'action' => 'orders', 'namespace' => '']);
$route->add('admin/histories', ['controller' => 'Admin', 'action' => 'histories', 'namespace' => '']);
$route->add('admin/logs', ['controller' => 'Admin', 'action' => 'logs', 'namespace' => '']);
$route->add('admin/postacceptor', ['controller' => 'Admin', 'action' => 'postacceptor', 'namespace' => '']);
$route->add('admin/banners', ['controller' => 'Admin', 'action' => 'banners', 'namespace' => '']);
$route->add('admin/brands', ['controller' => 'Admin', 'action' => 'brands', 'namespace' => '']);
$route->add('admin/site_users', ['controller' => 'Admin', 'action' => 'site_users', 'namespace' => '']);
$route->add('admin/export', ['controller' => 'Admin', 'action' => 'export', 'namespace' => '']);
$route->add('admin/seo', ['controller' => 'Admin', 'action' => 'seo', 'namespace' => '']);
$route->add('admin/banners_catalog', ['controller' => 'Admin', 'action' => 'banners_catalog', 'namespace' => '']);
$route->add('admin/ref_counters', ['controller' => 'Admin', 'action' => 'ref_counters', 'namespace' => '']);
$route->add('admin/pvz', ['controller' => 'Admin', 'action' => 'pvz', 'namespace' => '']);
$route->add('admin/depot_products', ['controller' => 'Admin', 'action' => 'depot_products', 'namespace' => '']);
$route->add('admin/depot_folders', ['controller' => 'Admin', 'action' => 'depot_folders', 'namespace' => '']);
$route->add('admin/depot_orders', ['controller' => 'Admin', 'action' => 'depot_orders', 'namespace' => '']);
$route->add('admin/depot_counterparty', ['controller' => 'Admin', 'action' => 'depot_counterparty', 'namespace' => '']);
$route->add('admin/depot_stores', ['controller' => 'Admin', 'action' => 'depot_stores', 'namespace' => '']);
$route->add('admin/depot_organizations', ['controller' => 'Admin', 'action' => 'depot_organizations', 'namespace' => '']);
$route->add('admin/depot_retailstore', ['controller' => 'Admin', 'action' => 'depot_retailstore', 'namespace' => '']);
$route->add('admin/menu', ['controller' => 'Admin', 'action' => 'menu', 'namespace' => '']);

$route->add('katalog/ajax', ['controller' => 'Catalog', 'action' => 'ajax', 'namespace' => '']);
$route->add('{url:katalog.*}', ['controller' => 'Catalog', 'action' => 'index', 'namespace' => '']);
$route->add('cart', ['controller' => 'Catalog', 'action' => 'cart', 'namespace' => '']);
$route->add('cart/checkout', ['controller' => 'Catalog', 'action' => 'checkout', 'namespace' => '']);
$route->add('katalog_yml', ['controller' => 'Catalog', 'action' => 'katalog_yml', 'namespace' => '']);

$route->add('{url:cart/pay_result.*}', ['controller' => 'Catalog', 'action' => 'payResult', 'namespace' => '']);
$route->add('{url:cart/pay_callback.*}', ['controller' => 'Catalog', 'action' => 'payCallback', 'namespace' => '']);

$route->add('{url:brendy.*}', ['controller' => 'Brand', 'action' => 'index', 'namespace' => '']);

$route->add('lk', ['controller' => 'Lk', 'action' => 'index', 'namespace' => '']);
$route->add('lk/logout', ['controller' => 'Lk', 'action' => 'logout', 'namespace' => '']);
$route->add('lk/ajax', ['controller' => 'Lk', 'action' => 'ajax', 'namespace' => '']);
$route->add('lk/orders', ['controller' => 'Lk', 'action' => 'orders', 'namespace' => '']);
$route->add('lk/confirm', ['controller' => 'Lk', 'action' => 'confirm', 'namespace' => '']);
$route->add('poisk{url:.*}', ['controller' => 'Search', 'action' => 'main', 'namespace' => '']);

$route->add('{url:.*}', ['controller' => 'Page', 'action' => 'index', 'namespace' => '']);

$route->dispatch($_SERVER['REQUEST_URI']);