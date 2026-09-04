<?php

/** @var \App\Core\Router $router */

$router->get('/admin', 'AdminController@dashboard')->middleware('auth')->middleware('admin');
$router->get('/admin/dashboard', 'AdminController@dashboard')->middleware('auth')->middleware('admin');
// Outras rotas admin (CRUD) podem ser adicionadas depois