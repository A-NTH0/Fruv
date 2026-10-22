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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('css/pedidos.css') ?>?v=<?= filemtime(FCPATH . 'css/pedidos.css') ?>">
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
        <div class="modal-box max-w-3xl" onclick="event.stopPropagation()">
            <div class="modal-head">
                <h2>📦 Nuevo pedido</h2>
                <button onclick="cerrarModalNuevoPedido()" class="modal-x"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="campo-label">Modalidad</label>
                        <select id="np-modalidad" class="campo">
                            <option value="contado">💵 Contado</option>
                            <option value="credito">💰 Crédito</option>
                        </select>
                    </div>
                    <div>
                        <label class="campo-label">Cliente</label>
                        <input type="hidden" id="np-cliente">
                        <div class="autocomplete-wrap">
                            <input type="text" id="np-cliente-texto" class="autocomplete-input" placeholder="Buscar cliente..." autocomplete="off">
                            <div id="np-cliente-lista" class="autocomplete-list"></div>
                        </div>
                    </div>
                    <div>
                        <label class="campo-label">Repartidor</label>
                        <input type="hidden" id="np-repartidor">
                        <div class="autocomplete-wrap">
                            <input type="text" id="np-repartidor-texto" class="autocomplete-input" placeholder="Buscar repartidor..." autocomplete="off">
                            <div id="np-repartidor-lista" class="autocomplete-list"></div>
                        </div>
                    </div>
                    <div>
                        <label class="campo-label">Fecha pedido</label>
                        <input type="date" id="np-fecha_pedido" class="campo" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="campo-label">Fecha entrega</label>
                        <input type="date" id="np-fecha_entrega" class="campo">
                    </div>
                    <div>
                        <label class="campo-label">Horario</label>
                        <input type="time" id="np-horario" class="campo">
                    </div>
                </div>

                <div class="bg-gray-100 p-3 rounded-lg mb-3">
                    <label class="font-bold text-sm">Agregar productos</label>
                    <div class="flex gap-2 mt-2 flex-wrap">
                        <input type="hidden" id="np-prod-id">
                        <div class="autocomplete-wrap flex-1 min-w-[180px]">
                            <input type="text" id="np-sel-prod-texto" class="autocomplete-input" placeholder="Buscar producto..." autocomplete="off">
                            <div id="np-prod-lista" class="autocomplete-list"></div>
                        </div>
                        <input type="number" id="np-cant-prod" value="1" min="0.01" step="0.01" class="w-20 p-2 border rounded-lg" placeholder="Cant">
                        <input type="number" id="np-prod-precio" min="0" step="0.01" class="w-24 p-2 border rounded-lg border-amber-300 bg-amber-50 focus:ring-2 focus:ring-amber-400 outline-none" placeholder="$ Precio">
                        <select id="np-unidad-prod" class="w-28 p-2 border rounded-lg">
                            <option value="kilos">Kilos</option>
                            <option value="domos">Domos</option>
                            <option value="ramos">Ramos</option>
                        </select>
                        <button type="button" onclick="agregarFilaProducto()" class="bg-rose-500 hover:bg-rose-600 text-white px-4 rounded-lg font-bold">+ Agregar</button>
                    </div>
                    <p class="text-[11px] text-amber-600 mt-1.5">💡 El precio se llena automático, pero puedes cambiarlo por cliente.</p>
                </div>

                <div class="max-h-40 overflow-y-auto mb-3">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 text-left">Producto</th>
                                <th class="p-2 text-center">Cant</th>
                                <th class="p-2 text-center">Unidad</th>
                                <th class="p-2 text-right">Precio</th>
                                <th class="p-2 text-right">Subtotal</th>
                                <th class="p-2 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="lista-productos-body">
                            <tr><td colspan="6" class="text-center py-4 text-gray-400">No hay productos agregados</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-rose-50 p-3 rounded-lg flex justify-between items-center mb-4">
                    <span class="font-bold text-rose-800">TOTAL DEL PEDIDO:</span>
                    <span id="total-modal" class="text-xl font-black text-rose-600">$0.00</span>
                </div>

                <!-- Pago inicial: solo si la modalidad es crédito (desde $0). De contado se toma el total como pagado -->
                <div id="np-pago-box" class="hidden bg-purple-50 p-3 rounded-lg mb-4">
                    <label class="campo-label" for="np-pago-inicial">Pago inicial / enganche <span class="text-gray-400 font-normal">(puede ser $0)</span></label>
                    <input type="number" id="np-pago-inicial" min="0" step="0.01" value="0" class="campo" placeholder="0.00">
                    <p class="text-xs text-purple-700 mt-1.5">Saldo pendiente: <strong id="np-saldo">$0.00</strong></p>
                </div>

                <div class="flex gap-3">
                    <button onclick="guardarNuevoPedido()" class="btn-modal bg-rose-500 hover:bg-rose-600 text-white">💾 Guardar pedido</button>
                    <button onclick="cerrarModalNuevoPedido()" class="btn-modal bg-gray-200 hover:bg-gray-300 text-gray-700">Cancelar</button>
                </div>
            </div>
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

        const DESCUENTO_PREFERENCIAL = <?= \App\Models\ClienteModel::DESCUENTO_PREFERENCIAL ?>;

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