<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('login', 'LogingController::login');
$routes->post('login', 'LogingController::autenticar');
$routes->get('logout', 'LogingController::cerrarSesion');
$routes->get('/', 'LogingController::login');
$routes->get('dashboard', 'HomeController::index');

$routes->get('productos', 'ProductosController::index');
$routes->post('productos/guardar', 'ProductosController::guardar');
$routes->post('productos/actualizar', 'ProductosController::actualizar');
$routes->post('productos/cambiarEstado/(:num)', 'ProductosController::cambiarEstado/$1');
$routes->get('productos/verificarEliminar/(:num)', 'ProductosController::verificarEliminar/$1');
$routes->get('productos/eliminar/(:num)', 'ProductosController::eliminar/$1');

$routes->get('inventario', 'InventarioController::index');
$routes->post('inventario/registrar-entrada', 'InventarioController::registrarEntrada');
$routes->post('inventario/registrar-merma', 'InventarioController::registrarMerma');
$routes->post('inventario/procesar-merma-masiva', 'InventarioController::procesarMermaMasiva');
$routes->get('inventario/detalles-stock/(:num)', 'InventarioController::detallesStock/$1');

$routes->get('clientes', 'ClientesController::index');
$routes->post('clientes/guardar', 'ClientesController::guardar');
$routes->post('clientes/actualizar', 'ClientesController::actualizar');
$routes->post('clientes/baja', 'ClientesController::darDeBaja');
$routes->post('clientes/preferencial', 'ClientesController::preferencial');
$routes->get('clientes/verificarEliminar/(:num)', 'ClientesController::verificarEliminar/$1');
$routes->get('clientes/eliminar/(:num)', 'ClientesController::eliminar/$1');
$routes->get('clientes/historial/(:num)', 'ClientesController::historial/$1');
$routes->post('clientes/recargar', 'ClientesController::recargar');
$routes->get('clientes/pedido/nuevo/(:num)', 'ClientesController::nuevoPedido/$1');
$routes->get('clientes/pedido/(:num)', 'ClientesController::catalogoPedido/$1');
$routes->post('clientes/pedido/agregar', 'ClientesController::agregarProductoPedido');
$routes->post('clientes/pedido/actualizar', 'ClientesController::actualizarCantidadPedido');
$routes->post('clientes/pedido/quitar', 'ClientesController::quitarProductoPedido');
$routes->post('clientes/pedido/enviar', 'ClientesController::enviarPedidoVendedor');

$routes->get('repartidores', 'RepartidoresController::index');
$routes->post('repartidores/guardar', 'RepartidoresController::guardar');
$routes->post('repartidores/actualizar', 'RepartidoresController::actualizar');
$routes->get('repartidores/verificarEliminar/(:num)', 'RepartidoresController::verificarEliminar/$1');
$routes->get('repartidores/eliminar/(:num)', 'RepartidoresController::eliminar/$1');

$routes->get('vendedores', 'VendedoresController::index');
$routes->post('vendedores/guardar', 'VendedoresController::guardar');
$routes->post('vendedores/actualizar', 'VendedoresController::actualizar');
$routes->post('vendedores/eliminar', 'VendedoresController::eliminar');

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

// ── Flujo de pedidos del administrador (aceptar / pendiente / repartidor) ──
$routes->post('pedidos/aceptar', 'PedidosFlujoController::aceptar');
$routes->post('pedidos/poner-pendiente', 'PedidosFlujoController::ponerPendiente');
$routes->post('pedidos/asignar-repartidor', 'PedidosFlujoController::asignarRepartidor');

// ── Cliente: carrito, historial y perfil ──
$routes->get('carrito', 'CarritoController::index');
$routes->get('carrito/resumen', 'CarritoController::resumenJson');
$routes->post('carrito/agregar', 'CarritoController::agregar');
$routes->post('carrito/actualizar', 'CarritoController::actualizar');
$routes->post('carrito/quitar', 'CarritoController::quitar');
$routes->post('carrito/vaciar', 'CarritoController::vaciar');
$routes->post('carrito/confirmar', 'CarritoController::confirmar');

$routes->get('mis-pedidos', 'ClientePedidosController::index');
$routes->get('mis-pedidos/estados', 'ClientePedidosController::estados');
$routes->post('mis-pedidos/cancelar', 'ClientePedidosController::cancelar');
$routes->post('mis-pedidos/abonar', 'ClientePedidosController::abonar');
$routes->get('perfil', 'ClientePedidosController::perfil');