<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//----------- Login --------------
$routes->get('login', 'LogingController::login');
$routes->post('login', 'LogingController::autenticar');
$routes->get('logout', 'LogingController::cerrarSesion');
// ---------- Login / Dashboard ----------
$routes->get('/', 'LogingController::login');
$routes->get('dashboard', 'HomeController::index');

// ---------- Productos ----------
$routes->get('productos', 'ProductosController::index');
$routes->post('productos/guardar', 'ProductosController::guardar');
$routes->post('productos/actualizar', 'ProductosController::actualizar');
$routes->post('productos/cambiarEstado/(:num)', 'ProductosController::cambiarEstado/$1');
$routes->get('productos/verificarEliminar/(:num)', 'ProductosController::verificarEliminar/$1');
$routes->get('productos/eliminar/(:num)', 'ProductosController::eliminar/$1');

// ---------- Inventario ----------
$routes->get('inventario', 'InventarioController::index');
$routes->post('inventario/registrar-entrada', 'InventarioController::registrarEntrada');
$routes->post('inventario/registrar-merma', 'InventarioController::registrarMerma');
$routes->post('inventario/procesar-merma-masiva', 'InventarioController::procesarMermaMasiva');
$routes->get('inventario/detalles-stock/(:num)', 'InventarioController::detallesStock/$1');

// ---------- Clientes ----------
$routes->get('clientes', 'ClientesController::index');
$routes->post('clientes/guardar', 'ClientesController::guardar');
$routes->post('clientes/actualizar', 'ClientesController::actualizar');
$routes->post('clientes/baja', 'ClientesController::darDeBaja');
$routes->get('clientes/verificarEliminar/(:num)', 'ClientesController::verificarEliminar/$1');
$routes->get('clientes/eliminar/(:num)', 'ClientesController::eliminar/$1');

// ---------- Repartidores ----------
$routes->get('repartidores', 'RepartidoresController::index');
$routes->post('repartidores/guardar', 'RepartidoresController::guardar');
$routes->post('repartidores/actualizar', 'RepartidoresController::actualizar');
$routes->get('repartidores/verificarEliminar/(:num)', 'RepartidoresController::verificarEliminar/$1');
$routes->get('repartidores/eliminar/(:num)', 'RepartidoresController::eliminar/$1');

// ---------- Pedidos ----------
$routes->get('pedidos', 'PedidosController::index');
$routes->get('pedidos/getPedidosPaginados', 'PedidosController::getPedidosPaginados');
$routes->get('pedidos/buscar', 'PedidosController::buscar');
$routes->get('pedidos/obtenerDetallePedido', 'PedidosController::obtenerDetallePedido');
$routes->get('pedidos/obtenerPedidoParaEditar', 'PedidosController::obtenerPedidoParaEditar');
$routes->get('pedidos/getStatsActualizados', 'PedidosController::getStatsActualizados');
$routes->post('pedidos/guardar', 'PedidosController::guardar');
$routes->post('pedidos/actualizar', 'PedidosController::actualizar');
$routes->post('pedidos/cambiarEstado', 'PedidosController::cambiarEstado');
$routes->post('pedidos/registrarAbono', 'PedidosController::registrarAbono');
$routes->get('pedidos/eliminar/(:num)', 'PedidosController::eliminar/$1');