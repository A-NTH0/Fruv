<?php
$flagsJson = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;

// Título, icono y color de cada columna (el controlador no se modifica).
$meta = [
    'pedido'      => ['Pendientes',  'inbox',        'rose',    'Pedidos que necesitan revisión y aceptación.'],
    'confirmado'  => ['Aceptados',   'check-circle', 'blue',    'Pedidos aceptados que esperan organización o salida.'],
    'en_transito' => ['En tránsito', 'truck',        'amber',   'Pedidos que están con el repartidor.'],
    'entregado'   => ['Entregados',  'award',        'emerald', 'Pedidos completados.'],
    'cancelado'   => ['Cancelados',  'x-circle',     'slate',   'Pedidos que ya no continúan en el flujo.'],
    'credito'     => ['A crédito',   'credit-card',  'purple',  'Pedidos con saldo pendiente de cobro.'],
    'pagado'      => ['Pagados',     'check-circle', 'green',   'Pedidos liquidados.'],
];

$kpis = [
    ['pedido',      'nuevo',      'Pendientes',  'rose'],
    ['confirmado',  'confirmado', 'Aceptados',   'blue'],
    ['en_transito', 'transito',   'En tránsito', 'amber'],
    ['entregado',   'completado', 'Entregados',  'emerald'],
    ['credito',     'credito',    'A crédito',   'purple'],
    ['pagado',      'pagado',     'Pagados',     'green'],
    ['cancelado',   'cancelado',  'Cancelados',  'slate'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Fruver App - Seguimiento de Pedidos</title>
    <link rel="stylesheet" href="<?= base_url('vendor/fonts/fonts.css') ?>">
    <script src="<?= base_url('vendor/lucide/lucide.min.js') ?>"></script>
    <script src="<?= base_url('vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= base_url('css/pedidos.css') ?>?v=<?= filemtime(FCPATH . 'css/pedidos.css') ?>">
    <link rel="stylesheet" href="<?= base_url('vendor/tailwind/tailwind.css') ?>">
</head>
<body>

    <?= view('plantilla/menu') ?>

    <main class="main-content">
        <div class="ped-wrap">

            <header class="ped-header">
                <div class="ped-hero-copy">
                    <span class="ped-kicker"><i data-lucide="activity" aria-hidden="true"></i> CENTRO DE OPERACIONES</span>
                    <h1>Seguimiento de pedidos</h1>
                    <p>Visualiza qué necesita atención, organiza las entregas y consulta cada pedido.</p>
                </div>

                <div class="ped-tools">
                    <div class="search-box">
                        <i data-lucide="search" class="w-4 h-4"></i>
                        <input type="search" id="buscador-pedidos" placeholder="Cliente, producto o número" aria-label="Buscar pedidos por cliente, producto o número">
                        <button id="btn-limpiar-busqueda" onclick="limpiarBusqueda()" class="hidden" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">✕</button>
                    </div>
                    <button type="button" onclick="abrirModalNuevoPedido()" class="btn-primary btn-nuevo">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Nuevo pedido
                    </button>
                </div>
                <div class="ped-hero-summary" aria-label="Resumen de pedidos activos">
                    <div>
                        <span>Requieren atención</span>
                        <strong data-summary="atencion"><?= (int) ($stats['nuevo'] ?? 0) + (int) ($stats['confirmado'] ?? 0) ?></strong>
                        <small>pendientes y aceptados</small>
                    </div>
                    <div class="ped-summary-divider"></div>
                    <div>
                        <span>En reparto</span>
                        <strong data-summary="transito"><?= (int) ($stats['transito'] ?? 0) ?></strong>
                        <small>en tránsito</small>
                    </div>
                    <div class="ped-hero-icon"><i data-lucide="package-check" aria-hidden="true"></i></div>
                </div>
            </header>

            <div id="resultado-busqueda" class="hidden resultado-busqueda">
                <h3 id="titulo-resultado-busqueda">Resultados</h3>
                <div id="lista-resultado-busqueda"></div>
            </div>

            <div class="workflow-label">
                <div>
                    <span class="workflow-step">FLUJO DE PEDIDOS</span>
                    <h2>Pedidos por estado</h2>
                </div>
                <p>Selecciona una etapa para ver sus pedidos y acciones.</p>
            </div>
            <nav class="stats-grid" role="tablist" aria-label="Filtrar pedidos por estado">
                <?php foreach ($kpis as [$col, $stat, $label, $color]): ?>
                    <button type="button" role="tab" aria-controls="panel-pedidos-<?= $col ?>"
                            aria-selected="<?= $col === 'pedido' ? 'true' : 'false' ?>"
                            class="kpi-chip kpi-<?= $color ?><?= $col === 'pedido' ? ' is-active' : '' ?>"
                            onclick="scrollToColumn('<?= $col ?>')">
                        <span class="kpi-dot"></span>
                        <span class="kpi-label"><?= $label ?></span>
                        <span class="value stat-value" data-stat="<?= $stat ?>"><?= $stats[$stat] ?? 0 ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <div class="kanban-scroll-container">
                <div class="kanban-board-wrapper" id="kanbanWrapper">
                    <div class="kanban-board" id="kanban-board">
                        <?php foreach ($columnas as $key => $col): ?>
                            <?php [$titulo, $icono, $color, $descripcion] = $meta[$key] ?? [$col['titulo'], $col['icon'], 'slate', 'Pedidos de esta etapa.']; ?>
                            <section id="panel-pedidos-<?= $key ?>"
                                     class="kanban-column col-<?= $key ?><?= $key === 'pedido' ? ' is-active' : '' ?>"
                                     data-estado="<?= $key ?>" role="tabpanel" aria-label="<?= esc($titulo) ?>"
                                     <?= $key === 'pedido' ? '' : 'hidden' ?>>
                                <div class="col-head">
                                    <div class="col-title">
                                        <i data-lucide="<?= $icono ?>" class="w-4 h-4"></i>
                                        <div>
                                            <h2><?= $titulo ?></h2>
                                            <p class="col-description"><?= esc($descripcion) ?></p>
                                        </div>
                                    </div>
                                    <span class="count-pedidos" data-total="<?= $totals[$key] ?? 0 ?>"><?= $totals[$key] ?? 0 ?> pedidos</span>
                                </div>

                                <div class="col-body overflow-y-auto" data-columna="<?= $key ?>"></div>

                                <div class="col-foot">
                                    <button class="pagination-prev" data-estado="<?= $key ?>"
                                        onclick="cambiarPaginaColumna('<?= $key ?>', 'prev')"
                                        <?= ($currentPage ?? 1) <= 1 ? 'disabled style="opacity:0.5"' : '' ?>>◀ Anterior</button>
                                    <span class="pagina-actual" data-estado="<?= $key ?>">Pág. <?= $currentPage ?? 1 ?></span>
                                    <button class="pagination-next" data-estado="<?= $key ?>"
                                        onclick="cambiarPaginaColumna('<?= $key ?>', 'next')">Siguiente ▶</button>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Detalle -->
    <div id="modalDetalle" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalDetalle(event)">
        <div class="modal-box max-w-3xl" onclick="event.stopPropagation()">
            <div class="modal-head">
                <h2>📄 Detalle del pedido</h2>
                <button onclick="cerrarModalDetalle()" class="modal-x"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div id="detalleContenido" class="p-5"></div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="modalEditar" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalEditar(event)">
        <div class="modal-box max-w-4xl" onclick="event.stopPropagation()">
            <div class="modal-head">
                <h2>✏️ Editar pedido #<span id="edit-id_pedido"></span></h2>
                <button onclick="cerrarModalEditar()" class="modal-x"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div id="editarContenido" class="p-5"></div>
        </div>
    </div>

    <!-- Modal Abono -->
    <div id="modalAbono" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalAbono(event)">
        <div class="modal-box max-w-md" onclick="event.stopPropagation()">
            <div class="modal-head">
                <h2>💰 Registrar abono</h2>
                <button onclick="cerrarModalAbono()" class="modal-x"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5">
                <input type="hidden" id="abono_id_pedido">
                <div class="mb-4 p-3 bg-purple-50 rounded-lg">
                    <p class="text-sm text-purple-700 font-medium">Deuda actual: <span id="abono_deuda_display" class="font-bold text-purple-900">$0.00</span></p>
                </div>
                <div class="mb-4">
                    <label class="campo-label">Monto del abono</label>
                    <input type="number" id="abono_monto" step="0.01" min="0.01" class="campo" placeholder="0.00">
                </div>
                <div class="mb-5">
                    <label class="campo-label">Fecha del abono</label>
                    <input type="date" id="abono_fecha" class="campo">
                </div>
                <div class="flex gap-3">
                    <button onclick="registrarAbono()" class="btn-modal bg-purple-500 hover:bg-purple-600 text-white">💾 Guardar abono</button>
                    <button onclick="cerrarModalAbono()" class="btn-modal bg-gray-200 hover:bg-gray-300 text-gray-700">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Pedido -->
    <div id="modalNuevoPedido" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalNuevoPedido(event)">
        <div class="modal-box np-modal max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="np-titulo" onclick="event.stopPropagation()">

            <header class="np-head">
                <span class="np-head-icon" aria-hidden="true"><i data-lucide="package-plus" class="w-5 h-5"></i></span>
                <div class="np-head-copy">
                    <h2 id="np-titulo">Nuevo pedido</h2>
                    <p>Captura los datos, agrega los productos y revisa el total antes de guardar.</p>
                </div>
                <button type="button" onclick="cerrarModalNuevoPedido()" class="modal-x" aria-label="Cerrar"><i data-lucide="x" class="w-5 h-5"></i></button>
            </header>

            <div class="np-body">

                <!-- 1 · Datos del pedido -->
                <section class="np-card" aria-labelledby="np-sec-datos">
                    <h3 class="np-section-title" id="np-sec-datos"><span class="np-step">1</span> Datos del pedido</h3>

                    <div class="np-grid">
                        <div class="np-field">
                            <label class="campo-label" for="np-modalidad">Modalidad de pago</label>
                            <select id="np-modalidad" class="campo">
                                <option value="contado">De contado</option>
                                <option value="credito">A crédito</option>
                            </select>
                        </div>
                        <div class="np-field">
                            <label class="campo-label" for="np-cliente-texto">Cliente</label>
                            <input type="hidden" id="np-cliente">
                            <div class="autocomplete-wrap">
                                <input type="text" id="np-cliente-texto" class="autocomplete-input" placeholder="Buscar cliente…" autocomplete="off">
                                <div id="np-cliente-lista" class="autocomplete-list"></div>
                            </div>
                        </div>
                        <div class="np-field">
                            <label class="campo-label" for="np-repartidor-texto">Repartidor <span class="np-opt">(opcional)</span></label>
                            <input type="hidden" id="np-repartidor">
                            <div class="autocomplete-wrap">
                                <input type="text" id="np-repartidor-texto" class="autocomplete-input" placeholder="Buscar repartidor…" autocomplete="off">
                                <div id="np-repartidor-lista" class="autocomplete-list"></div>
                            </div>
                        </div>
                        <div class="np-field">
                            <label class="campo-label" for="np-fecha_pedido">Fecha del pedido</label>
                            <input type="date" id="np-fecha_pedido" class="campo" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="np-field">
                            <label class="campo-label" for="np-fecha_entrega">Fecha de entrega <span class="np-opt">(opcional)</span></label>
                            <input type="date" id="np-fecha_entrega" class="campo">
                        </div>
                        <div class="np-field">
                            <label class="campo-label" for="np-horario">Horario <span class="np-opt">(opcional)</span></label>
                            <input type="time" id="np-horario" class="campo">
                        </div>
                    </div>

                    <!-- Solo aparece cuando el cliente seleccionado es preferencial -->
                    <div id="np-descuento-box" class="np-discount hidden" role="group" aria-labelledby="np-desc-title">
                        <div class="np-discount-info">
                            <span class="np-discount-icon" aria-hidden="true"><i data-lucide="badge-percent" class="w-5 h-5"></i></span>
                            <div>
                                <strong id="np-desc-title">Cliente preferencial</strong>
                                <p>Escribe el porcentaje de descuento que quieres otorgarle. Se aplicará a todos los productos del pedido.</p>
                            </div>
                        </div>
                        <div class="np-discount-input">
                            <label class="campo-label" for="np-descuento">Descuento (%)</label>
                            <div class="np-suffix">
                                <input type="number" id="np-descuento" inputmode="decimal" min="0" max="<?= \App\Models\ClienteModel::DESCUENTO_MAXIMO ?>" step="0.01" placeholder="0">
                                <span aria-hidden="true">%</span>
                            </div>
                            <small>Máximo <?= \App\Models\ClienteModel::DESCUENTO_MAXIMO ?>%</small>
                        </div>
                    </div>
                </section>

                <!-- 2 · Productos -->
                <section class="np-card" aria-labelledby="np-sec-prods">
                    <h3 class="np-section-title" id="np-sec-prods"><span class="np-step">2</span> Productos</h3>

                    <div class="np-add">
                        <div class="np-field np-grow">
                            <label class="campo-label" for="np-sel-prod-texto">Producto</label>
                            <input type="hidden" id="np-prod-id">
                            <div class="autocomplete-wrap">
                                <input type="text" id="np-sel-prod-texto" class="autocomplete-input" placeholder="Buscar producto…" autocomplete="off">
                                <div id="np-prod-lista" class="autocomplete-list"></div>
                            </div>
                        </div>
                        <div class="np-field np-w-cant">
                            <label class="campo-label" for="np-cant-prod">Cantidad</label>
                            <input type="number" id="np-cant-prod" value="1" min="0.01" step="0.01" class="campo">
                        </div>
                        <div class="np-field np-w-unidad">
                            <label class="campo-label" for="np-unidad-prod">Unidad</label>
                            <select id="np-unidad-prod" class="campo">
                                <option value="kilos">Kilos</option>
                                <option value="domos">Domos</option>
                                <option value="ramos">Ramos</option>
                            </select>
                        </div>
                        <div class="np-field np-w-precio">
                            <label class="campo-label" for="np-prod-precio">Precio</label>
                            <input type="number" id="np-prod-precio" min="0" step="0.01" class="campo np-precio" placeholder="$ 0.00">
                        </div>
                        <button type="button" onclick="agregarFilaProducto()" class="np-btn-add"><i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i> Agregar</button>
                    </div>
                    <p class="np-hint"><i data-lucide="lightbulb" class="w-3.5 h-3.5" aria-hidden="true"></i> El precio se llena con el de catálogo y puedes cambiarlo para este cliente.</p>

                    <div class="np-table-wrap">
                        <table class="np-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Producto</th>
                                    <th scope="col" class="text-center">Cant.</th>
                                    <th scope="col" class="text-center">Unidad</th>
                                    <th scope="col" class="text-right">Precio</th>
                                    <th scope="col" class="text-right">Subtotal</th>
                                    <th scope="col" class="text-center"><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody id="lista-productos-body">
                                <tr><td colspan="6" class="np-empty">Aún no hay productos agregados</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <footer class="np-footer">
                <!-- Pago inicial: solo si la modalidad es crédito (desde $0). De contado se toma el total como pagado -->
                <div id="np-pago-box" class="np-pay hidden">
                    <label class="campo-label" for="np-pago-inicial">Pago inicial / enganche <span class="np-opt">(puede ser $0)</span></label>
                    <input type="number" id="np-pago-inicial" min="0" step="0.01" value="0" class="campo" placeholder="0.00">
                    <p>Saldo pendiente: <strong id="np-saldo">$0.00</strong></p>
                </div>

                <dl class="np-totals" aria-live="polite">
                    <div class="np-row"><dt>Subtotal</dt><dd id="np-subtotal">$0.00</dd></div>
                    <div class="np-row np-row-desc hidden" id="np-desc-row"><dt>Descuento (<span id="np-desc-pct">0</span>%)</dt><dd id="np-desc-monto">−$0.00</dd></div>
                    <div class="np-row np-row-total"><dt>Total del pedido</dt><dd id="total-modal">$0.00</dd></div>
                </dl>

                <div class="np-actions">
                    <button type="button" onclick="cerrarModalNuevoPedido()" class="np-btn-cancel">Cancelar</button>
                    <button type="button" onclick="guardarNuevoPedido()" class="np-btn-save"><i data-lucide="save" class="w-4 h-4" aria-hidden="true"></i> Guardar pedido</button>
                </div>
            </footer>
        </div>
    </div>

    <!-- Variables globales de PHP hacia JS -->
    <script>
        const BASE_URL       = '<?= base_url() ?>/';
        const PAGINA_INICIAL = <?= (int) ($currentPage ?? 1) ?>;

        const CLIENTES_DATA = <?= json_encode(array_map(fn($c) => [
            'id'           => $c['id_cliente'],
            'label'        => trim($c['nombre'] . ' ' . $c['ap_paterno']) . (\App\Models\ClienteModel::esPreferencial((int) $c['compras'], $c['preferencial_manual'] ?? 0) ? ' ⭐ Preferencial' : ''),
            'preferencial' => \App\Models\ClienteModel::esPreferencial((int) $c['compras'], $c['preferencial_manual'] ?? 0),
        ], $clientes)) ?>;

        const DESCUENTO_MAXIMO = <?= \App\Models\ClienteModel::DESCUENTO_MAXIMO ?>;

        const REPARTIDORES_DATA = <?= json_encode(array_map(fn($r) => [
            'id'    => $r['id_repartidor'],
            'label' => $r['nombre'] . ' ' . $r['ap_paterno'],
        ], $repartidores)) ?>;

        const PRODUCTOS_DATA = <?= json_encode(array_map(fn($p) => [
            'id'            => $p['id_producto'],
            'label'         => $p['nombre'] . ' (Disp: ' . ($p['stock_disponible'] ?? 0) . ')',
            'nombre_limpio' => $p['nombre'],
            'precio'        => floatval($p['precio'] ?? 0),
            'stock'         => floatval($p['stock_disponible'] ?? 0)
        ], $productos)) ?>;

        // Pedidos de la primera carga: las tarjetas se dibujan desde pedidos-flujo.js
        const PEDIDOS_INICIALES = <?= json_encode($pedidos, $flagsJson) ?: '{}' ?>;
        const TOTALES_ESTADO    = <?= json_encode($totals, $flagsJson) ?: '{}' ?>;
    </script>

    <script src="<?= base_url('js/pedidos.js') ?>?v=<?= filemtime(FCPATH . 'js/pedidos.js') ?>"></script>
    <script src="<?= base_url('js/pedidos-flujo.js') ?>?v=<?= filemtime(FCPATH . 'js/pedidos-flujo.js') ?>"></script>
</body>
</html>