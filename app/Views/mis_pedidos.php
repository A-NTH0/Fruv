<?php
use App\Models\PedidoClienteModel;

$fechaValida = static fn ($d) => ! empty($d) && strpos((string)$d, '0000') !== 0;
$pedidoOk    = session()->getFlashdata('pedido_ok');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Mis pedidos</title>
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
                <h1>Mis pedidos</h1>
                <p>Historial de tus compras y su estado actual. Se actualiza solo cada pocos segundos.</p>
            </div>
            <a href="<?= base_url('productos') ?>" class="btn-ghost">Seguir comprando</a>
        </header>

        <?php if ($pedidoOk): ?>
            <div class="alert-ok">
                ¡Pedido #<?= (int) $pedidoOk ?> enviado! Está <strong>pendiente</strong> de aceptación por la tienda.
            </div>
        <?php endif; ?>

        <?php if (empty($pedidos)): ?>
            <div class="empty-box">
                <p>Aún no has realizado pedidos.</p>
                <a href="<?= base_url('productos') ?>" class="btn-confirm" style="display:inline-block;width:auto;padding:.7rem 1.4rem">Ver catálogo</a>
            </div>
        <?php else: ?>
            <div class="pedidos-lista">
                <?php foreach ($pedidos as$p): ?>
                    <?php $e =$p['estado']; $paso = (int)$e['paso']; ?>
                    <article class="pedido-card <?= $e['clave'] === 'cancelado' ? 'is-cancelado' : '' ?>"
                             data-id="<?= (int) $p['id_pedido'] ?>" data-clave="<?= esc($e['clave']) ?>"
                             data-total="<?= esc(number_format((float) $p['total'], 2, '.', ''), 'attr') ?>"
                             data-info="<?= esc(json_encode(['pago' => $p['pago'], 'puede_cancelar' =>$p['puede_cancelar']], JSON_UNESCAPED_UNICODE), 'attr') ?>">
                        <div class="pedido-top">
                            <div>
                                <h2>Pedido #<?= (int) $p['id_pedido'] ?></h2>
                                <p><?= esc(date('d/m/Y', strtotime($p['fecha']))) ?> · <?= esc($p['pago']['modalidad_texto']) ?></p>
                            </div>
                            <div class="pedido-right">
                                <span class="pedido-total js-total">$<?= number_format((float)$p['total'], 2) ?></span>
                                <span class="badge badge-<?= esc($e['color']) ?> js-badge"><?= esc($e['texto']) ?></span>
                            </div>
                        </div>

                        <ol class="stepper" aria-label="Progreso del pedido">
                            <?php foreach (PedidoClienteModel::PASOS as $n =>$nombre): ?>
                                <?php $estadoPaso = $n <$paso ? 'completado' : ($n ===$paso ? 'paso actual' : 'pendiente'); ?>
                                <li class="step <?= $n < $paso ? 'is-done' : ($n === $paso ? 'is-current' : '') ?>" data-n="<?= $n ?>"<?= $n ===$paso ? ' aria-current="step"' : '' ?>>
                                    <span class="step-dot" aria-hidden="true"></span>
                                    <span class="step-label"><?= esc($nombre) ?></span>
                                    <span class="sr-only step-estado">, <?= $estadoPaso ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                        <p class="pedido-detalle js-detalle"><?= esc($e['detalle']) ?></p>

                        <?php /* Pago y acciones (cancelar / abonar): las dibuja mis-pedidos.js y se sincronizan solas */ ?>
                        <div class="pedido-pago js-pago"></div>
                        <div class="pedido-acciones js-acciones"></div>

                        <div class="pedido-meta">
                            <span class="js-repartidor" <?= $p['repartidor'] ? '' : 'style="display:none"' ?>>
                                🛵 Repartidor: <strong><?= esc($p['repartidor'] ?? '') ?></strong>
                            </span>
                            <?php if ($fechaValida($p['fecha_entrega_programada'])): ?>
                                <span>📅 Entrega deseada: <?= esc(date('d/m/Y', strtotime($p['fecha_entrega_programada']))) ?>
                                    <?= ! empty($p['horario_entrega']) && $p['horario_entrega'] !== '00:00:00' ? esc(substr($p['horario_entrega'], 0, 5)) : '' ?></span>
                            <?php endif; ?>
                            <span class="js-actualizado muted"><?= $p['actualizado'] ? 'Actualizado: ' . esc($p['actualizado']) : '' ?></span>
                        </div>

                        <details class="pedido-prods">
                            <summary>Ver productos (<span class="js-prods-count"><?= count($p['productos']) ?></span>)</summary>
                            <ul class="js-prods-lista">
                                <?php foreach ($p['productos'] as$pr): ?>
                                    <li>
                                        <span><?= esc($pr['nombre_producto']) ?> <small>× <?= esc(rtrim(rtrim(number_format((float) $pr['cantidad'], 2, '.', ''), '0'), '.')) ?> <?= esc($pr['unidad_venta']) ?></small></span>
                                        <strong>$<?= number_format((float)$pr['total'], 2) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($paginas > 1): ?>
                <nav class="paginacion" aria-label="Paginación">
                    <?php if ($pagina > 1): ?><a href="?page=<?= $pagina - 1 ?>">← Anteriores</a><?php endif; ?>
                    <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                    <?php if ($pagina <$paginas): ?><a href="?page=<?= $pagina + 1 ?>">Siguientes →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>const BASE_URL = '<?= rtrim(base_url(), '/') ?>/';</script>
    <script src="<?= base_url('js/mis-pedidos.js') ?>"></script>
</body>
</html>