<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Mi carrito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/cliente.css') ?>">
</head>
<body>
    <?= view('plantilla/menu') ?>

    <main class="cli-main">
        <header class="cli-head">
            <div>
                <h1>Mi carrito</h1>
                <p>Revisa tus productos. Al confirmar, la tienda recibirá tu pedido como <strong>Pendiente</strong>.</p>
            </div>
            <button type="button" class="btn-ghost" id="btn-vaciar" onclick="vaciarCarrito()">Vaciar carrito</button>
        </header>

        <div class="cart-layout">
            <section id="carrito-lista" class="cart-list" aria-live="polite"></section>

            <aside id="carrito-resumen" class="cart-summary">
                <h2>Resumen</h2>
                <div class="sum-row"><span>Productos</span><strong id="sum-cuenta">0</strong></div>
                <div class="sum-row sum-total"><span>Total</span><strong id="sum-total">$0.00</strong></div>

                <div class="sum-field">
                    <label for="fecha_entrega">Fecha de entrega deseada <small>(opcional)</small></label>
                    <input type="date" id="fecha_entrega" min="<?= esc($hoy) ?>">
                </div>
                <div class="sum-field">
                    <label for="horario">Horario deseado <small>(opcional)</small></label>
                    <input type="time" id="horario">
                </div>

                <button type="button" class="btn-confirm" id="btn-confirmar" onclick="confirmarPedido()">Confirmar pedido</button>
                <p class="sum-note">La tienda revisará la disponibilidad y aceptará tu pedido. Podrás seguir su estado en <a href="<?= base_url('mis-pedidos') ?>">Mis pedidos</a>.</p>
            </aside>
        </div>
    </main>

    <script>
        const BASE_URL = '<?= rtrim(base_url(), '/') ?>/';
        const CARRITO_INICIAL = <?= json_encode([
            'items'   => $items,
            'total'   => $total,
            'cuenta'  => count($items),
            'todo_ok' => ! in_array(false, array_column($items, 'ok'), true),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    </script>
    <script src="<?= base_url('js/carrito.js') ?>"></script>
</body>
</html>