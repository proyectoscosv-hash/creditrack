<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::login');
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::loginSubmit');
$routes->match(['get', 'post'], 'logout', 'AuthController::logout');
$routes->match(['get', 'post'], 'home.php', 'Home::index', ['filter' => 'auth']);
$routes->match(['get', 'post'], 'home', 'Home::index', ['filter' => 'auth']);

// M3 Plug-in Hooks (Agency CRUD, Operational Map, Cascading Geo & Geolocation API):
$routes->group('agencias', ['filter' => 'supervisor'], static function (RouteCollection $routes) {
    $routes->get('', 'AgenciaController::index');
    $routes->get('/', 'AgenciaController::index');
    $routes->get('crear', 'AgenciaController::crear');
    $routes->post('guardar', 'AgenciaController::guardar');
    $routes->post('', 'AgenciaController::guardar');
    $routes->post('/', 'AgenciaController::guardar');
    $routes->get('mapa', 'AgenciaController::mapa');
    $routes->get('ver/(:num)', 'AgenciaController::ver/$1');
    $routes->get('editar/(:num)', 'AgenciaController::editar/$1');
    $routes->post('actualizar/(:num)', 'AgenciaController::actualizar/$1');
    $routes->post('deshabilitar/(:num)', 'AgenciaController::deshabilitar/$1');
});

$routes->group('api', ['filter' => 'supervisor'], static function (RouteCollection $routes) {
    $routes->get('agencias/geolocalizacion', 'Api\AgenciaApiController::geolocalizacion');
    $routes->group('geo', static function (RouteCollection $routes) {
        $routes->get('paises', 'GeoController::paises');
        $routes->get('departamentos', 'GeoController::departamentos');
        $routes->get('municipios', 'GeoController::municipios');
        $routes->get('distritos', 'GeoController::distritos');
    });
});

// Explicit rejection of physical DELETE on agency routes (R4 / CA-11: 405 Method Not Allowed)
$routes->delete('agencias/(:any)', static function () {
    return \Config\Services::response()->setStatusCode(405)->setBody('Method Not Allowed');
});
$routes->delete('api/agencias/(:any)', static function () {
    return \Config\Services::response()->setStatusCode(405)->setJSON(['status' => 'error', 'error' => 'method_not_allowed']);
});

// M2 Guard: Explicitly block Supervisor (403) from user/role/permission routes even before controllers exist
$routes->add('(?i:usuarios|roles|permisos)', 'UserAdminGuardController::notFound', ['filter' => 'user_admin_guard']);
$routes->add('(?i:usuarios|roles|permisos)/(:any)', 'UserAdminGuardController::notFound', ['filter' => 'user_admin_guard']);
$routes->add('(?i:api/(?:usuarios|roles|permisos))', 'UserAdminGuardController::notFound', ['filter' => 'user_admin_guard']);
$routes->add('(?i:api/(?:usuarios|roles|permisos)/(:any))', 'UserAdminGuardController::notFound', ['filter' => 'user_admin_guard']);
