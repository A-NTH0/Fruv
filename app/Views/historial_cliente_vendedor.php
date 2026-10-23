<?php use App\Models\PedidoClienteModel; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Historial de compras</title>
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
                <h1>Historial de compras</h1>
                <p><?= esc(trim($cliente['nombre'] . ' ' . $cliente['ap_paterno'] . ' ' . $cliente['ap_materno'])) ?></p>
            </div>
            <a href="<?= base_url('clientes') ?>" class="btn-ghost">Volver a clientes</a>
        </header>

        <?php if ($mensaje = session()->getFlashdata('error')): ?>
            <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= esc($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($mensaje = session()->getFlashdata('success')): ?>
            <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800"><?= esc($mensaje) ?></div>
        <?php endif; ?>

        <?php if (! $pedidos): ?>
            <div class="empty-box">
                <p>Este cliente aún no tiene compras registradas.</p>
                <a href="<?= base_url('clientes/pedido/nuevo/' . (int) $cliente['id_cliente']) ?>" class="btn-confirm" style="display:inline-block;width:auto;padding:.7rem 1.1rem">
                    Crear pedido nuevo
                </a>
            </div>
        <?php else: ?>
            <section class="pedidos-lista">
                <?php foreach ($pedidos as $pedido): ?>
                    <?php $puedeRecargar = in_array($pedido['estado_actual'] ?? '', ['entregado', 'credito', 'pagado'], true); ?>
                    <article class="pedido-card">
                        <div class="pedido-top">
                            <div>
                                <h2>Pedido #<?= (int) $pedido['numero_pedido_cliente'] ?></h2>
                                <p><?= esc(date('d/m/Y', strtotime($pedido['fecha']))) ?> · <?= esc($pedido['pago']['modalidad_texto']) ?></p>
                            </div>
                            <div class="pedido-right">
                                <span class="pedido-total">$<?= number_format((float) $pedido['total'], 2) ?></span>
                                <span class="badge badge-<?= esc($pedido['estado']['color']) ?>"><?= esc($pedido['estado']['texto']) ?></span>
                            </div>
                        </div>
                        <details class="pedido-prods">
                            <summary>Ver productos (<?= count($pedido['productos']) ?>)</summary>
                            <ul>
                                <?php foreach ($pedido['productos'] as $producto): ?>
                                    <li>
                                        <span><?= esc($producto['nombre_producto']) ?>
                                            <small>× <?= esc(rtrim(rtrim(number_format((float) $producto['cantidad'], 2, '.', ''), '0'), '.')) ?> <?= esc($producto['unidad_venta']) ?></small>
                                        </span>
                                        <strong>$<?= number_format((float) $producto['total'], 2) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="<?= base_url('clientes/pedido/' . (int) $cliente['id_cliente'] . '?pedido=' . (int) $pedido['id_pedido']) ?>"
                               class="btn-ghost inline-flex items-center justify-center">
                                Agregar productos
                            </a>
                            <?php if ($puedeRecargar): ?>
                                <button type="button" onclick="abrirRecarga(<?= (int) $pedido['id_pedido'] ?>)"
                                        class="btn-confirm" style="width:auto;padding:.7rem 1.1rem">
                                    Recargar pedido
                                </button>
                            <?php else: ?>
                                <p class="self-center text-sm text-gray-500">Disponible para recargar cuando el pedido sea entregado.</p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <?php if ($puedeRecargar && $pedido['productos']): ?>
                        <div id="modal-recarga-<?= (int) $pedido['id_pedido'] ?>" class="vendor-reload-overlay hidden"
                             role="dialog" aria-modal="true" aria-labelledby="titulo-recarga-<?= (int) $pedido['id_pedido'] ?>">
                            <section class="vendor-reload-modal">
                                <header class="vendor-reload-heading">
                                    <div>
                                        <p class="vendor-eyebrow">Pedido entregado</p>
                                        <h2 id="titulo-recarga-<?= (int) $pedido['id_pedido'] ?>">Recargar pedido #<?= (int) $pedido['numero_pedido_cliente'] ?></h2>
                                        <p>Ajusta las cantidades antes de enviar el nuevo pedido al administrador.</p>
                                    </div>
                                    <button type="button" class="vendor-reload-close"
                                            onclick="cerrarRecarga(<?= (int) $pedido['id_pedido'] ?>)"
                                            aria-label="Cerrar ventana de recarga">×</button>
                                </header>
                                <form action="<?= base_url('clientes/recargar') ?>" method="post">
                                    <input type="hidden" name="id_cliente" value="<?= (int) $cliente['id_cliente'] ?>">
                                    <input type="hidden" name="id_pedido" value="<?= (int) $pedido['id_pedido'] ?>">
                                    <ul class="vendor-reload-products">
                                        <?php foreach ($pedido['productos'] as $producto): ?>
                                            <?php
                                                $idProducto = (int) $producto['id_producto3'];
                                                $paso = $producto['unidad_venta'] === 'kilos' ? '0.5' : '1';
                                            ?>
                                            <li>
                                                <span>
                                                    <strong><?= esc($producto['nombre_producto']) ?></strong>
                                                    <small>$<?= number_format((float) $producto['precio_venta'], 2) ?> / <?= esc($producto['unidad_venta']) ?></small>
                                                </span>
                                                <label>
                                                    <span class="sr-only">Cantidad de <?= esc($producto['nombre_producto']) ?></span>
                                                    <input type="number" name="cantidades[<?= $idProducto ?>]"
                                                           value="<?= esc($producto['cantidad']) ?>" min="<?= $paso ?>"
                                                           step="<?= $paso ?>" max="10000" required>
                                                    <small><?= esc($producto['unidad_venta']) ?></small>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <p class="vendor-reload-note">Se aplicarán precios actuales y se comprobará la existencia antes de crear el pedido.</p>
                                    <footer class="vendor-reload-actions">
                                        <button type="button" class="btn-ghost" onclick="cerrarRecarga(<?= (int) $pedido['id_pedido'] ?>)">Cancelar</button>
                                        <button type="submit" class="btn-confirm">Confirmar recarga</button>
                                    </footer>
                                </form>
                            </section>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>

            <?php if ($paginas > 1): ?>
                <nav class="paginacion" aria-label="Paginación del historial">
                    <?php if ($pagina > 1): ?><a href="?page=<?= $pagina - 1 ?>">← Anteriores</a><?php endif; ?>
                    <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                    <?php if ($pagina < $paginas): ?><a href="?page=<?= $pagina + 1 ?>">Siguientes →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <script>
        lucide.createIcons();
        function abrirRecarga(id) {
            const modal = document.getElementById('modal-recarga-' + id);
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }
        function cerrarRecarga(id) {
            const modal = document.getElementById('modal-recarga-' + id);
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }
        document.querySelectorAll('.vendor-reload-overlay').forEach(modal => {
            modal.addEventListener('click', event => {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.vendor-reload-overlay:not(.hidden)').forEach(modal => {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                });
            }
        });
    </script>
</body>
</html>
