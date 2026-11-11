<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::login');
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::loginSubmit');
$routes->match(['get', 'post'], 'logout', 'AuthController::logout');
$routes->match(['get', 'post'], 'home.php', 'Home::index', ['filter' => 'auth']);
$routes->match(['get', 'post'], 'home', 'Home::index', ['filter' => 'auth']);
