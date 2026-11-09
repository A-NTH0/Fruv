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
    <script src="<?= base_url('vendor/lucide/lucide.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/pedido-vendedor.css') ?>">
    <link rel="stylesheet" href="<?= base_url('vendor/tailwind/tailwind.css') ?>">
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

        <section class="vo-panel" aria-labelledby="vo-titulo">
            <div class="vo-head">
                <div>
                    <p class="vendor-eyebrow">Pedido en preparación</p>
                    <h2 id="vo-titulo">Nuevo pedido · <?= esc($nombreCliente) ?></h2>
                    <p class="vendor-order-hint">
                        <?php if ($idPedidoOrigen > 0): ?>
                            Productos del pedido #<?= $idPedidoOrigen ?> cargados como punto de partida. Puedes agregar más o ajustar cantidades; al enviar, se generará un pedido nuevo.
                        <?php else: ?>
                            Este borrador comienza vacío. Al enviarlo, quedará pendiente de revisión del administrador y aparecerá en el historial del cliente.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="vo-badges">
                    <?php if ($esPreferencial): ?>
                        <span class="vo-badge vo-badge-pref"><i data-lucide="star" aria-hidden="true"></i> Cliente preferencial</span>
                    <?php endif; ?>
                    <span class="vo-badge"><i data-lucide="shopping-basket" aria-hidden="true"></i> <?= count($carrito) ?> producto<?= count($carrito) === 1 ? '' : 's' ?></span>
                </div>
            </div>

            <?php if (! $carrito): ?>
                <div class="vendor-cart-empty">
                    <i data-lucide="shopping-basket" aria-hidden="true"></i>
                    <p>Tu pedido está vacío</p>
                    <span>Elige productos del catálogo de abajo para empezar.</span>
                </div>
            <?php else: ?>
                <div class="vo-layout">
                    <div class="vo-items">
                        <h3 class="vo-subtitle">Productos del pedido</h3>
                        <ul class="vo-list">
                            <?php foreach ($carrito as $item): ?>
                                <li class="vo-item" data-precio="<?= (float) $item['precio'] ?>" data-cantidad="<?= (float) $item['cantidad'] ?>">
                                    <div class="vo-item-info">
                                        <strong><?= esc($item['nombre']) ?></strong>
                                        <small>$<?= number_format((float) $item['precio'], 2) ?> / <?= esc($item['unidad']) ?></small>
                                    </div>
                                    <form action="<?= base_url('clientes/pedido/actualizar') ?>" method="post" class="vo-item-edit">
                                        <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                                        <input type="hidden" name="id_producto" value="<?= (int) $item['id'] ?>">
                                        <label class="sr-only" for="pedido-cantidad-<?= (int) $item['id'] ?>">Cantidad de <?= esc($item['nombre']) ?></label>
                                        <input id="pedido-cantidad-<?= (int) $item['id'] ?>" type="number" name="cantidad"
                                               value="<?= esc($item['cantidad']) ?>" min="<?= $item['unidad'] === 'kilos' ? '0.5' : '1' ?>"
                                               step="<?= $item['unidad'] === 'kilos' ? '0.5' : '1' ?>" max="<?= esc($item['stock']) ?>" required>
                                        <button type="submit" class="vo-btn-soft">Actualizar</button>
                                    </form>
                                    <div class="vo-item-sub">
                                        <span class="vo-sub-old js-sub-old" hidden></span>
                                        <strong class="js-sub">$<?= number_format((float) $item['subtotal'], 2) ?></strong>
                                        <small class="js-sub-note" hidden></small>
                                    </div>
                                    <form action="<?= base_url('clientes/pedido/quitar') ?>" method="post" class="vo-item-del">
                                        <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                                        <input type="hidden" name="id_producto" value="<?= (int) $item['id'] ?>">
                                        <button type="submit" class="vo-btn-del" aria-label="Quitar <?= esc($item['nombre'], 'attr') ?> del pedido" title="Quitar">
                                            <i data-lucide="trash-2" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <form action="<?= base_url('clientes/pedido/enviar') ?>" method="post" class="vo-summary" id="formEnviarPedido">
                        <input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
                        <h3 class="vo-subtitle">Resumen y pago</h3>

                        <div class="vo-fields">
                            <label>
                                <span>Fecha de entrega <small>(opcional)</small></span>
                                <input type="date" name="fecha_entrega" min="<?= date('Y-m-d') ?>">
                            </label>
                            <label>
                                <span>Horario <small>(opcional)</small></span>
                                <input type="time" name="horario">
                            </label>
                            <label class="vo-full">
                                <span>Método de pago</span>
                                <select id="modalidadPago" name="modalidad_pago">
                                    <option value="contado">De contado</option>
                                    <option value="credito">A crédito</option>
                                </select>
                            </label>
                            <label id="campoPagoInicial" class="vo-full" hidden>
                                <span>Pago inicial <small>(puede ser $0)</small></span>
                                <input type="number" id="pagoInicial" name="pago_inicial" min="0" step="0.01" value="0">
                            </label>
                        </div>

                        <?php if ($esPreferencial): ?>
                            <div class="vo-discount" role="group" aria-labelledby="vo-desc-title">
                                <div class="vo-discount-copy">
                                    <i data-lucide="badge-percent" aria-hidden="true"></i>
                                    <div>
                                        <strong id="vo-desc-title">Descuento para cliente preferencial</strong>
                                        <p>Escribe el porcentaje a aplicar. Se descuenta de cada producto del pedido.</p>
                                    </div>
                                </div>
                                <label class="vo-discount-input">
                                    <span class="sr-only">Descuento en porcentaje</span>
                                    <input type="number" id="descuento" name="descuento" inputmode="decimal"
                                           min="0" max="<?= (float) $descuentoMax ?>" step="0.01" placeholder="0">
                                    <b aria-hidden="true">%</b>
                                </label>
                                <small>Máximo <?= (float) $descuentoMax ?>%</small>
                            </div>
                        <?php endif; ?>

                        <dl class="vo-totals" aria-live="polite">
                            <div><dt>Subtotal</dt><dd id="resSubtotal">$<?= number_format($totalCarrito, 2) ?></dd></div>
                            <div class="vo-desc-row" id="resDescRow" hidden><dt>Descuento (<span id="resDescPct">0</span>%)</dt><dd id="resDescMonto">−$0.00</dd></div>
                            <div class="vo-saldo-row" id="resSaldoRow" hidden><dt>Saldo pendiente</dt><dd id="resSaldo">$0.00</dd></div>
                            <div class="vo-total"><dt>Total del pedido</dt><dd id="resTotal">$<?= number_format($totalCarrito, 2) ?></dd></div>
                        </dl>

                        <button type="submit" class="vo-submit"><i data-lucide="send" aria-hidden="true"></i> Generar pedido</button>
                    </form>
                </div>
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

        (function () {
            const ES_PREFERENCIAL = <?= $esPreferencial ? 'true' : 'false' ?>;
            const DESCUENTO_MAX   = <?= (float) $descuentoMax ?>;
            const r2      = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
            const dinero  = (n) => '$' + n.toFixed(2);
            const $       = (id) => document.getElementById(id);

            const modalidad = $('modalidadPago');
            if (!modalidad) return; // carrito vacío: no hay formulario

            function descuento() {
                if (!ES_PREFERENCIAL) return 0;
                const n = parseFloat($('descuento').value);
                return isNaN(n) || n <= 0 ? 0 : Math.min(n, DESCUENTO_MAX);
            }

            /** Recalcula cada producto y el total con el descuento (mismo redondeo que el servidor). */
            function recalcular() {
                const pct = descuento();
                let bruto = 0, total = 0;

                document.querySelectorAll('.vo-item').forEach((li) => {
                    const precio   = parseFloat(li.dataset.precio);
                    const cantidad = parseFloat(li.dataset.cantidad);
                    const sinDesc  = r2(precio * cantidad);
                    const precioF  = r2(precio * (100 - pct) / 100);
                    const conDesc  = r2(precioF * cantidad);
                    bruto += sinDesc;
                    total += conDesc;

                    li.querySelector('.js-sub').textContent = dinero(conDesc);
                    const viejo = li.querySelector('.js-sub-old'), nota = li.querySelector('.js-sub-note');
                    viejo.hidden = nota.hidden = !(pct > 0);
                    viejo.textContent = dinero(sinDesc);
                    nota.textContent  = dinero(precioF) + ' c/u con −' + pct + '%';
                });

                $('resSubtotal').textContent = dinero(bruto);
                $('resDescPct').textContent  = pct;
                $('resDescMonto').textContent = '−' + dinero(Math.max(0, bruto - total));
                $('resDescRow').hidden       = !(pct > 0);
                $('resTotal').textContent    = dinero(total);

                const credito = modalidad.value === 'credito';
                $('campoPagoInicial').hidden = !credito;
                $('resSaldoRow').hidden      = !credito;
                $('pagoInicial').max         = total;
                if (credito) {
                    const inicial = parseFloat($('pagoInicial').value) || 0;
                    $('resSaldo').textContent = dinero(Math.max(0, total - inicial));
                }
            }

            modalidad.addEventListener('change', recalcular);
            $('pagoInicial').addEventListener('input', recalcular);
            if (ES_PREFERENCIAL) {
                $('descuento').addEventListener('input', recalcular);
                $('descuento').addEventListener('blur', (e) => {
                    const n = parseFloat(e.target.value);
                    if (!isNaN(n) && n > DESCUENTO_MAX) e.target.value = DESCUENTO_MAX;
                    if (!isNaN(n) && n < 0) e.target.value = '';
                    recalcular();
                });
            }
            recalcular();
        })();
    </script>
</body>
</html>