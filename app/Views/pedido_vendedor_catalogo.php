<?php
$nombreCliente = trim($cliente['nombre'] . ' ' . $cliente['ap_paterno'] . ' ' . $cliente['ap_materno']);
$totalCarrito = round(array_sum(array_column($carrito, 'subtotal')), 2);
$carritoPorId = array_column($carrito, null, 'id');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Crear pedido nuevo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/pedido-vendedor.css') ?>">
</head>
<body>
    <?= view('plantilla/menu') ?>
    <main class="cli-main">
        <header class="cli-head">
            <div>
                <h1>Crear pedido nuevo</h1>
                <p>Cliente: <?= esc($nombreCliente) ?> · Se creará un pedido nuevo para el cliente.</p>
            </div>
            <a href="<?= base_url('clientes/historial/' . $idCliente) ?>" class="btn-ghost">Ver pedidos del cliente</a>
        </header>

        <?php if ($mensaje = session()->getFlashdata('error')): ?>
            <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= esc($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($mensaje = session()->getFlashdata('success')): ?>
            <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800"><?= esc($mensaje) ?></div>
        <?php endif; ?>

        <section class="vendor-order-panel">
            <div class="vendor-order-heading">
                <div>
                    <p class="vendor-eyebrow">Pedido en preparación</p>
                    <h2>Nuevo pedido · <?= esc($nombreCliente) ?></h2>
                    <p class="vendor-order-hint">
                        <?php if ($idPedidoOrigen > 0): ?>
                            Productos del pedido #<?= $idPedidoOrigen ?> cargados como punto de partida. Puedes agregar más o ajustar cantidades; al enviar, se generará un pedido nuevo.
                        <?php else: ?>
                            Este borrador comienza vacío, sin cargar productos de pedidos anteriores. Al enviarlo, quedará pendiente de revisión del administrador y aparecerá en el historial del cliente.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="vendor-order-total">
                    <span><?= count($carrito) ?> producto<?= count($carrito) === 1 ? '' : 's' ?></span>
                    <strong>$<?= number_format($totalCarrito, 2) ?></strong>
                </div>
            </div>

            <?php if (! $carrito): ?>
                <div class="vendor-cart-empty">
                    <i data-lucide="shopping-basket" aria-hidden="true"></i>
                    <p>Tu pedido está vacío</p>
                    <span>Elige productos del catálogo de abajo para empezar.</span>
                </div>
            <?php else: ?>
                <ul class="vendor-cart-list">
                    <?php foreach ($carrito as $item): ?>
                        <li>
                            <span class="vendor-cart-product">
                                <strong><?= esc($item['nombre']) ?></strong>
                                <small>$<?= number_format((float) $item['precio'], 2) ?> / <?= esc($item['unidad']) ?></small>
                            </span>
                            <span class="vendor-cart-actions">
                                <strong>$<?= number_format((float) $item['subtotal'], 2) ?></strong>
                                <form action="<?= base_url('clientes/pedido/actualizar') ?>" method="post" class="vendor-cart-edit">
                                    <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                                    <input type="hidden" name="id_producto" value="<?= (int) $item['id'] ?>">
                                    <label class="sr-only" for="pedido-cantidad-<?= (int) $item['id'] ?>">Cantidad de <?= esc($item['nombre']) ?></label>
                                    <input id="pedido-cantidad-<?= (int) $item['id'] ?>" type="number" name="cantidad"
                                           value="<?= esc($item['cantidad']) ?>" min="<?= $item['unidad'] === 'kilos' ? '0.5' : '1' ?>"
                                           step="<?= $item['unidad'] === 'kilos' ? '0.5' : '1' ?>" max="<?= esc($item['stock']) ?>" required>
                                    <button type="submit" class="vendor-cart-save">Actualizar</button>
                                </form>
                                <form action="<?= base_url('clientes/pedido/quitar') ?>" method="post">
                                    <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                                    <input type="hidden" name="id_producto" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" aria-label="Quitar <?= esc($item['nombre'], 'attr') ?> del pedido">Quitar</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form action="<?= base_url('clientes/pedido/enviar') ?>" method="post" class="vendor-checkout">
                    <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                    <div class="vendor-checkout-fields">
                        <label>
                            <span>Fecha de entrega <small>(opcional)</small></span>
                            <input type="date" name="fecha_entrega" min="<?= date('Y-m-d') ?>">
                        </label>
                        <label>
                            <span>Horario <small>(opcional)</small></span>
                            <input type="time" name="horario">
                        </label>
                        <label>
                            <span>Método de pago</span>
                            <select id="modalidadPago" name="modalidad_pago" onchange="mostrarPagoInicial()">
                                <option value="contado">De contado</option>
                                <option value="credito">A crédito</option>
                            </select>
                        </label>
                        <label id="campoPagoInicial" class="hidden">
                            <span>Pago inicial</span>
                            <input type="number" name="pago_inicial" min="0" step="0.01" value="0">
                        </label>
                    </div>
                    <div class="vendor-checkout-submit">
                        <span>Total del pedido <strong>$<?= number_format($totalCarrito, 2) ?></strong></span>
                        <button type="submit">Enviar pedido al administrador</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="vendor-catalog">
            <div class="vendor-catalog-heading">
                <div>
                    <p class="vendor-eyebrow">Productos frescos</p>
                    <h2>Catálogo</h2>
                    <p>Selecciona los productos y cantidades que necesita el cliente.</p>
                </div>
                <form action="<?= base_url('clientes/pedido/' . $idCliente) ?>" method="get" class="vendor-search">
                    <label class="sr-only" for="buscarProducto">Buscar productos</label>
                    <i data-lucide="search" aria-hidden="true"></i>
                    <input id="buscarProducto" type="search" name="buscar" value="<?= esc($busqueda) ?>"
                           placeholder="Buscar por nombre, código o categoría">
                    <button type="submit">Buscar</button>
                </form>
            </div>

            <div class="vendor-product-grid">
            <?php if ($productos): ?>
                <?php foreach ($productos as $producto): ?>
                    <?php
                        $id       = (int) $producto['id_producto'];
                        $paso     = $producto['unidad_venta'] === 'kilos' ? '0.5' : '1';
                        $stock    = (float) $producto['stock_disponible'];
                        $cantidadActual = (float) ($carritoPorId[$id]['cantidad'] ?? 0);
                    ?>
                    <article class="vendor-product-card">
                        <div class="vendor-product-image">
                            <img src="<?= base_url('uploads/' . rawurlencode($producto['imagen'] ?: 'default.jpg')) ?>"
                                 alt="<?= esc($producto['nombre']) ?>"
                                 onerror="this.src='https://placehold.co/600x400?text=Fruver'">
                            <span><?= esc($producto['categoria']) ?></span>
                        </div>
                        <div class="vendor-product-body">
                            <div>
                                <h3><?= esc($producto['nombre']) ?></h3>
                                <p class="vendor-product-description"><?= esc($producto['descripcion'] ?: 'Producto fresco disponible en Fruver.') ?></p>
                            </div>
                            <div class="vendor-product-meta">
                                <strong>$<?= number_format((float) $producto['precio'], 2) ?><small>/ <?= esc($producto['unidad_venta']) ?></small></strong>
                                <span><i data-lucide="package-check" aria-hidden="true"></i> <?= esc($stock) ?> disponibles</span>
                            </div>
                            <?php if ($cantidadActual > 0): ?>
                                <p class="vendor-in-cart"><?= esc($cantidadActual) ?> <?= esc($producto['unidad_venta']) ?> en este pedido</p>
                            <?php endif; ?>
                            <form action="<?= base_url('clientes/pedido/agregar') ?>" method="post" class="vendor-add-form">
                                <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                                <input type="hidden" name="id_producto" value="<?= $id ?>">
                                <label class="sr-only" for="cantidad-<?= $id ?>">Cantidad de <?= esc($producto['nombre']) ?></label>
                                <input id="cantidad-<?= $id ?>" type="number" name="cantidad" value="<?= $paso ?>"
                                       min="<?= $paso ?>" step="<?= $paso ?>" max="<?= esc($stock) ?>" required>
                                <button type="submit"><i data-lucide="plus" aria-hidden="true"></i> Agregar</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vendor-catalog-empty">
                    <i data-lucide="search-x" aria-hidden="true"></i>
                    <p>No encontramos productos disponibles<?= $busqueda !== '' ? ' con esa búsqueda' : '' ?>.</p>
                </div>
            <?php endif; ?>
            </div>

            <?php
                $paginaActual = $pager->getCurrentPage('catalogo');
                $totalPaginas = $pager->getPageCount('catalogo');
                $urlPagina = static function (int $pagina) use ($idCliente, $busqueda, $idPedidoOrigen): string {
                    $query = ['page_catalogo' => $pagina];
                    if ($busqueda !== '') {
                        $query['buscar'] = $busqueda;
                    }
                    if ($idPedidoOrigen > 0) {
                        $query['pedido'] = $idPedidoOrigen;
                    }

                    return base_url('clientes/pedido/' . $idCliente) . '?' . http_build_query($query);
                };
            ?>
            <?php if ($totalPaginas > 1): ?>
                <nav class="vendor-pagination" aria-label="Paginación del catálogo">
                    <?php if ($paginaActual > 1): ?>
                        <a href="<?= esc($urlPagina($paginaActual - 1), 'attr') ?>"><i data-lucide="chevron-left" aria-hidden="true"></i> Anterior</a>
                    <?php else: ?>
                        <span class="is-disabled"><i data-lucide="chevron-left" aria-hidden="true"></i> Anterior</span>
                    <?php endif; ?>
                    <span class="vendor-page-count">Página <strong><?= $paginaActual ?></strong> de <?= $totalPaginas ?></span>
                    <?php if ($paginaActual < $totalPaginas): ?>
                        <a href="<?= esc($urlPagina($paginaActual + 1), 'attr') ?>">Siguiente <i data-lucide="chevron-right" aria-hidden="true"></i></a>
                    <?php else: ?>
                        <span class="is-disabled">Siguiente <i data-lucide="chevron-right" aria-hidden="true"></i></span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>
    <script>
        lucide.createIcons();
        function mostrarPagoInicial() {
            const credito = document.getElementById('modalidadPago').value === 'credito';
            document.getElementById('campoPagoInicial').classList.toggle('hidden', !credito);
        }
    </script>
</body>
</html>
