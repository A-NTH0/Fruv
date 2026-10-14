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
    <link rel="stylesheet" href="<?= base_url('css/pedidos.css') ?>">
</head>
<body class="bg-gray-50">

    <?= view('plantilla/menu') ?>

    <main class="main-content flex-1 p-4 md:p-8">
        <div class="max-w-full mx-auto">

            <div class="flex justify-between items-center mb-5 flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-800 mt-2 md:mt-0">📋 Seguimiento de Pedidos</h1>
                    <p class="text-gray-500 text-xs md:text-sm">Gestión visual del flujo completo de pedidos</p>
                </div>
                <button onclick="abrirModalNuevoPedido()" class="btn-primary text-white px-5 py-2.5 rounded-xl font-bold flex items-center gap-2 shadow-md text-sm">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Nuevo Pedido
                </button>
            </div>

     
            <div class="mb-5">
                <div class="relative max-w-md">
                    <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                    <input type="text" id="buscador-pedidos"
                        placeholder="Buscar por nombre de cliente"
                        class="w-full pl-9 pr-10 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-400 outline-none text-sm bg-white shadow-sm">
                    <button id="btn-limpiar-busqueda" onclick="limpiarBusqueda()"
                        class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none"
                        title="Limpiar">✕</button>
                </div>
                <div id="resultado-busqueda" class="hidden mt-3 bg-white rounded-2xl shadow border border-gray-100 p-4 max-w-3xl">
                    <h3 class="font-bold text-gray-700 text-sm mb-3" id="titulo-resultado-busqueda">Resultados</h3>
                    <div id="lista-resultado-busqueda" class="space-y-2 max-h-96 overflow-y-auto"></div>
                </div>
            </div>

            <div class="stats-grid mb-7">
                <div class="card-kpi" onclick="scrollToColumn('pedido')"><p class="label">📥 Nuevos</p><h2 class="value stat-value" data-stat="nuevo"><?= $stats['nuevo'] ?? 0 ?></h2></div>
                <div class="card-kpi" onclick="scrollToColumn('confirmado')"><p class="label">✅ Confirmados</p><h2 class="value stat-value" data-stat="confirmado"><?= $stats['confirmado'] ?? 0 ?></h2></div>
                <div class="card-kpi" onclick="scrollToColumn('en_transito')"><p class="label">🚚 En Tránsito</p><h2 class="value stat-value" data-stat="transito"><?= $stats['transito'] ?? 0 ?></h2></div>
                <div class="card-kpi" onclick="scrollToColumn('entregado')"><p class="label">🏆 Entregados</p><h2 class="value stat-value" data-stat="completado"><?= $stats['completado'] ?? 0 ?></h2></div>
                <div class="card-kpi" onclick="scrollToColumn('credito')"><p class="label">💰 A Crédito</p><h2 class="value stat-value" data-stat="credito"><?= $stats['credito'] ?? 0 ?></h2></div>
                <div class="card-kpi" onclick="scrollToColumn('pagado')"><p class="label">✅ Pagados</p><h2 class="value stat-value" data-stat="pagado"><?= $stats['pagado'] ?? 0 ?></h2></div>
                <div class="card-kpi" onclick="scrollToColumn('cancelado')"><p class="label">❌ Cancelados</p><h2 class="value stat-value" data-stat="cancelado"><?= $stats['cancelado'] ?? 0 ?></h2></div>
            </div>

            <div class="kanban-scroll-container">
                <button id="scrollLeftBtn" class="scroll-btn"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                <div class="kanban-board-wrapper" id="kanbanWrapper">
                    <div class="kanban-board" id="kanban-board">
                        <?php foreach ($columnas as $key => $col): ?>
                            <div class="kanban-column flex flex-col" data-estado="<?= $key ?>">
                                <div class="p-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="<?= $col['icon'] ?>" class="w-4 h-4 text-<?= $col['color'] ?>-500"></i>
                                            <h2 class="font-extrabold text-gray-800 text-base"><?= $col['titulo'] ?></h2>
                                        </div>
                                        <span class="bg-<?= $col['color'] ?>-100 text-<?= $col['color'] ?>-700 px-2.5 py-0.5 rounded-full text-xs font-bold count-pedidos" data-total="<?= $totals[$key] ?? 0 ?>">
                                            <?= $totals[$key] ?? 0 ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-1 overflow-y-auto p-3 space-y-3 min-h-[420px]" data-columna="<?= $key ?>">
                                    <?php if (isset($pedidos[$key]) && !empty($pedidos[$key])): ?>
                                        <?php foreach ($pedidos[$key] as $pedido): ?>
                                            <div class="tarjeta-pedido p-3 hover:shadow-md transition-all"
                                                data-id="<?= $pedido['id_pedido'] ?>"
                                                data-cliente="<?= strtolower($pedido['cliente_nombre'] . ' ' . ($pedido['cliente_apellido'] ?? '')) ?>"
                                                data-productos="<?= strtolower(implode(' ', array_column($pedido['productos'] ?? [], 'nombre_producto'))) ?>">

                                                <div class="flex justify-between items-start mb-2">
                                                    <div class="flex-1">
                                                        <h3 class="font-bold text-gray-800 text-sm cliente-nombre"><?= $pedido['cliente_nombre'] ?> <?= $pedido['cliente_apellido'] ?? '' ?></h3>
                                                        <p class="text-[11px] text-gray-400">Pedido #<?= $pedido['id_pedido'] ?></p>
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <span class="text-xs font-black text-gray-700 mr-1">$<?= number_format($pedido['total'] ?? 0, 2) ?></span>
                                                        <button onclick="event.stopPropagation(); editarPedido(<?= $pedido['id_pedido'] ?>)" class="p-1 bg-amber-50 text-amber-600 rounded-md hover:bg-amber-100" title="Editar"><i data-lucide="edit-2" class="w-3 h-3"></i></button>
                                                        <button onclick="event.stopPropagation(); verDetallePedido(<?= $pedido['id_pedido'] ?>)" class="p-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100" title="Ver detalle"><i data-lucide="eye" class="w-3 h-3"></i></button>
                                                        <button onclick="event.stopPropagation(); confirmarEliminarPedido(<?= $pedido['id_pedido'] ?>)" class="p-1 bg-red-50 text-red-500 rounded-md hover:bg-red-100" title="Eliminar"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
                                                    </div>
                                                </div>

                                                <div class="text-[11px] text-gray-500 space-y-0.5 mb-2 productos-lista">
                                                    <?php if (!empty($pedido['productos'])): ?>
                                                        <?php $primeros = array_slice($pedido['productos'], 0, 2); ?>
                                                        <?php foreach ($primeros as $prod): ?>
                                                            <p>• <span class="producto-nombre"><?= $prod['nombre_producto'] ?></span> (x<?= $prod['cantidad'] ?>)</p>
                                                        <?php endforeach; ?>
                                                        <?php if (count($pedido['productos']) > 2): ?>
                                                            <p class="text-gray-400">+<?= count($pedido['productos']) - 2 ?> más</p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <p class="text-gray-300 italic">Sin productos</p>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($pedido['id_repartidor']) && !empty($pedido['repartidor_nombre'])): ?>
                                                    <div class="flex items-center gap-1 text-[10px] text-gray-400 mt-1 pt-1 border-t border-gray-100">
                                                        <i data-lucide="user" class="w-3 h-3"></i>
                                                        <span><?= $pedido['repartidor_nombre'] ?> <?= $pedido['repartidor_apellido'] ?? '' ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($key == 'credito' && ($pedido['deuda_restante'] ?? 0) > 0): ?>
                                                    <div class="mt-1 text-[11px] font-semibold text-purple-600">Saldo: $<?= number_format($pedido['deuda_restante'], 2) ?></div>
                                                <?php endif; ?>

                                                <div class="mt-2 flex gap-2" onclick="event.stopPropagation()">
                                                    <?php if ($key == 'pedido'): ?>
                                                        <button onclick="confirmarAccion(<?= $pedido['id_pedido'] ?>, 'confirmado')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg text-[11px] font-bold">✅ Confirmar</button>
                                                        <button onclick="confirmarAccion(<?= $pedido['id_pedido'] ?>, 'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>
                                                    <?php elseif ($key == 'confirmado'): ?>
                                                    
                                                        <button onclick="confirmarAccion(<?= $pedido['id_pedido'] ?>, 'en_transito')" class="flex-1 bg-blue-500 text-white py-1.5 rounded-lg text-[11px] font-bold">🚚 Enviar</button>
                                                        <button onclick="confirmarAccion(<?= $pedido['id_pedido'] ?>, 'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>
                                                    <?php elseif ($key == 'en_transito'): ?>
                                                        <button onclick="confirmarAccion(<?= $pedido['id_pedido'] ?>, 'entregado')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg text-[11px] font-bold">📦 Entregado</button>
                                                        <button onclick="confirmarAccion(<?= $pedido['id_pedido'] ?>, 'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>
                                                    <?php elseif ($key == 'credito' && ($pedido['deuda_restante'] ?? 0) > 0): ?>
                                                        <button onclick="abrirModalAbono(<?= $pedido['id_pedido'] ?>, <?= $pedido['deuda_restante'] ?>)" class="w-full bg-purple-500 text-white py-1.5 rounded-lg text-[11px] font-bold">💰 Registrar Abono</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-10 opacity-60">
                                            <i data-lucide="<?= $col['icon'] ?>" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                                            <p class="text-gray-400 text-xs">No hay pedidos</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="p-3 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                                    <div class="flex items-center justify-between gap-2">
                                        <button class="text-xs text-gray-500 hover:text-rose-500 transition-colors pagination-prev"
                                            data-estado="<?= $key ?>"
                                            onclick="cambiarPaginaColumna('<?= $key ?>', 'prev')"
                                            <?= ($currentPage ?? 1) <= 1 ? 'disabled style="opacity:0.5"' : '' ?>>
                                            ◀ Anterior
                                        </button>
                                        <span class="text-xs text-gray-600 pagina-actual" data-estado="<?= $key ?>">Pág. <?= $currentPage ?? 1 ?></span>
                                        <button class="text-xs text-gray-500 hover:text-rose-500 transition-colors pagination-next"
                                            data-estado="<?= $key ?>"
                                            onclick="cambiarPaginaColumna('<?= $key ?>', 'next')">
                                            Siguiente ▶
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button id="scrollRightBtn" class="scroll-btn"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
            </div>

        </div>
    </main>


    <div id="modalDetalle" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalDetalle(event)">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">📄 Detalle del Pedido</h2>
                <button onclick="cerrarModalDetalle()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div id="detalleContenido" class="p-6"></div>
        </div>
    </div>


    <div id="modalEditar" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalEditar(event)">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">✏️ Editar Pedido #<span id="edit-id_pedido"></span></h2>
                <button onclick="cerrarModalEditar()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div id="editarContenido" class="p-6"></div>
        </div>
    </div>

    <div id="modalAbono" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalAbono(event)">
        <div class="bg-white rounded-2xl max-w-md w-full" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">💰 Registrar Abono</h2>
                <button onclick="cerrarModalAbono()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div class="p-6">
                <input type="hidden" id="abono_id_pedido">
                <div class="mb-4 p-3 bg-purple-50 rounded-lg">
                    <p class="text-sm text-purple-700 font-medium">Deuda actual: <span id="abono_deuda_display" class="font-bold text-purple-900">$0.00</span></p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Monto del Abono</label>
                    <input type="number" id="abono_monto" step="0.01" min="0.01" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-purple-400 outline-none" placeholder="0.00">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Fecha del Abono</label>
                    <input type="date" id="abono_fecha" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-purple-400 outline-none">
                </div>
                <div class="flex gap-3">
                    <button onclick="registrarAbono()" class="flex-1 bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-xl font-bold transition-colors">💾 Guardar Abono</button>
                    <button onclick="cerrarModalAbono()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-colors">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

  
    <div id="modalNuevoPedido" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalNuevoPedido(event)">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">📦 Nuevo Pedido</h2>
                <button onclick="cerrarModalNuevoPedido()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Modalidad</label>
                        <select id="np-modalidad" class="w-full p-2 border rounded-lg">
                            <option value="contado">💵 Contado</option>
                            <option value="credito">💰 Crédito</option>
                        </select>
                    </div>
                
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Cliente</label>
                        <input type="hidden" id="np-cliente">
                        <div class="autocomplete-wrap">
                            <input type="text" id="np-cliente-texto" class="autocomplete-input" placeholder="Buscar cliente..." autocomplete="off">
                            <div id="np-cliente-lista" class="autocomplete-list"></div>
                        </div>
                    </div>
                   
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Repartidor</label>
                        <input type="hidden" id="np-repartidor">
                        <div class="autocomplete-wrap">
                            <input type="text" id="np-repartidor-texto" class="autocomplete-input" placeholder="Buscar repartidor..." autocomplete="off">
                            <div id="np-repartidor-lista" class="autocomplete-list"></div>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Fecha Pedido</label>
                        <input type="date" id="np-fecha_pedido" class="w-full p-2 border rounded-lg" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Fecha Entrega</label>
                        <input type="date" id="np-fecha_entrega" class="w-full p-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Horario</label>
                        <input type="time" id="np-horario" class="w-full p-2 border rounded-lg">
                    </div>
                </div>

             
                <div class="bg-gray-100 p-3 rounded-lg mb-3">
                    <label class="font-bold text-sm">Agregar Productos</label>
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

                <div class="flex gap-3">
                    <button onclick="guardarNuevoPedido()" class="flex-1 bg-rose-500 hover:bg-rose-600 text-white py-3 rounded-xl font-bold transition-colors">💾 Guardar Pedido</button>
                    <button onclick="cerrarModalNuevoPedido()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-colors">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const BASE_URL = '<?= base_url() ?>/';

        const CLIENTES_DATA = <?= json_encode(array_map(fn($c) => [
            'id'    => $c['id_cliente'],
            'label' => $c['nombre'] . ' ' . $c['ap_paterno'],
        ], $clientes)) ?>;

        const REPARTIDORES_DATA = <?= json_encode(array_map(fn($r) => [
            'id'    => $r['id_repartidor'],
            'label' => $r['nombre'] . ' ' . $r['ap_paterno'],
        ], $repartidores)) ?>;

        const PRODUCTOS_DATA = <?= json_encode(array_map(fn($p) => [
            'id'     => $p['id_producto'],
            'label'  => $p['nombre'],
            'precio' => floatval($p['precio'] ?? 0),
        ], $productos)) ?>;

        let productosSeleccionados = [];

        let paginaActualPorEstado = {
            pedido: <?= $currentPage ?? 1 ?>,
            confirmado: 1, en_transito: 1, entregado: 1,
            cancelado: 1, credito: 1, pagado: 1
        };

 
        function crearAutocomplete(inputId, hiddenId, listaId, datos, onSelect, mensajeVacio) {
            const input  = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);
            const lista  = document.getElementById(listaId);
            if (!input || !lista) return;

            let activeIdx = -1;

            function mostrar(filtrados) {
                lista.innerHTML = '';
                activeIdx = -1;
                if (!filtrados.length) {
                    lista.innerHTML = `<div class="autocomplete-empty">${mensajeVacio || 'Sin resultados'}</div>`;
                    lista.classList.add('open');
                    return;
                }
                filtrados.forEach((item, i) => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.textContent = item.label;
                    div.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        seleccionar(item);
                    });
                    lista.appendChild(div);
                });
                lista.classList.add('open');
            }

            function seleccionar(item) {
                input.value  = item.label;
                hidden.value = item.id;
                lista.classList.remove('open');
                if (onSelect) onSelect(item);
            }

            function cerrar() {
                lista.classList.remove('open');
                activeIdx = -1;
            }

            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                hidden.value = '';
                if (!q) { cerrar(); return; }
                const filtrados = datos.filter(d => d.label.toLowerCase().includes(q)).slice(0, 12);
                mostrar(filtrados);
            });

            input.addEventListener('keydown', (e) => {
                const items = lista.querySelectorAll('.autocomplete-item');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = Math.min(activeIdx + 1, items.length - 1);
                    items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = Math.max(activeIdx - 1, 0);
                    items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeIdx >= 0 && items[activeIdx]) items[activeIdx].dispatchEvent(new Event('mousedown'));
                } else if (e.key === 'Escape') {
                    cerrar();
                }
            });

            input.addEventListener('focus', () => {
                if (input.value.trim()) {
                    const q = input.value.trim().toLowerCase();
                    mostrar(datos.filter(d => d.label.toLowerCase().includes(q)).slice(0, 12));
                }
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !lista.contains(e.target)) cerrar();
            });
        }

        function scrollToColumn(estado) {
            const col = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
            if (col) col.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function initKanbanScroll() {
            const wrapper  = document.getElementById('kanbanWrapper');
            const leftBtn  = document.getElementById('scrollLeftBtn');
            const rightBtn = document.getElementById('scrollRightBtn');
            if (!wrapper) return;
            const update = () => {
                const max = wrapper.scrollWidth - wrapper.clientWidth;
                leftBtn.classList.toggle('disabled', wrapper.scrollLeft <= 5);
                rightBtn.classList.toggle('disabled', wrapper.scrollLeft >= max - 5);
            };
            leftBtn.onclick  = () => { wrapper.scrollBy({ left: -340, behavior: 'smooth' }); setTimeout(update, 200); };
            rightBtn.onclick = () => { wrapper.scrollBy({ left:  340, behavior: 'smooth' }); setTimeout(update, 200); };
            wrapper.addEventListener('scroll', update);
            update();
        }

        async function cambiarPaginaColumna(estado, direccion) {
            let nuevaPagina = paginaActualPorEstado[estado];
            if (direccion === 'next') nuevaPagina++;
            else if (direccion === 'prev') { if (nuevaPagina <= 1) return; nuevaPagina--; }

            const columna    = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
            const contenedor = columna.querySelector('.overflow-y-auto');
            contenedor.innerHTML = `<div class="flex justify-center items-center py-10"><span class="text-gray-500 text-sm">Cargando...</span></div>`;

            try {
                const res  = await fetch(`${BASE_URL}pedidos/getPedidosPaginados?estado=${estado}&page=${nuevaPagina}&per_page=10`);
                const data = await res.json();
                if (data.success) {
                    paginaActualPorEstado[estado] = nuevaPagina;
                    actualizarColumnaKanban(estado, data.pedidos, nuevaPagina, data.totalPages);
                    if (data.stats) actualizarStatsUI(data.stats);
                } else throw new Error(data.message);
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudieron cargar los pedidos', 'error');
            }
        }

        function actualizarStatsUI(stats) {
            const map = {
                nuevo: stats.nuevo, confirmado: stats.confirmado,
                transito: stats.transito, completado: stats.completado,
                credito: stats.credito, pagado: stats.pagado, cancelado: stats.cancelado
            };
            document.querySelectorAll('.stat-value').forEach(el => {
                if (map[el.dataset.stat] !== undefined) el.textContent = map[el.dataset.stat];
            });
        }

        function actualizarColumnaKanban(estado, pedidos, paginaActual, totalPages) {
            const columna    = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
            if (!columna) return;
            const contenedor = columna.querySelector('.overflow-y-auto');
            contenedor.innerHTML = '';
            if (!pedidos || !pedidos.length) {
                contenedor.innerHTML = `<div class="text-center py-10 opacity-60"><p class="text-gray-400 text-xs">No hay pedidos en esta página</p></div>`;
            } else {
                pedidos.forEach(p => contenedor.appendChild(crearTarjetaPedido(p, estado)));
            }
            const span = columna.querySelector('.pagina-actual');
            if (span) span.textContent = `Pág. ${paginaActual}`;
            const prev = columna.querySelector('.pagination-prev');
            const next = columna.querySelector('.pagination-next');
            if (prev) { prev.disabled = paginaActual <= 1; prev.style.opacity = paginaActual <= 1 ? '0.5' : '1'; }
            if (next) { next.disabled = paginaActual >= totalPages; next.style.opacity = paginaActual >= totalPages ? '0.5' : '1'; }
            lucide.createIcons();
        }

        function crearTarjetaPedido(pedido, estado) {
            const div = document.createElement('div');
            div.className = 'tarjeta-pedido p-3 hover:shadow-md transition-all';
            div.dataset.id       = pedido.id_pedido;
            div.dataset.cliente  = ((pedido.cliente_nombre || '') + ' ' + (pedido.cliente_apellido || '')).toLowerCase();
            div.dataset.productos = (pedido.productos || []).map(p => p.nombre_producto).join(' ').toLowerCase();

            const prodsHtml = (pedido.productos || []).slice(0, 2)
                .map(p => `<p>• ${p.nombre_producto} (x${p.cantidad})</p>`).join('');
            const masProds  = (pedido.productos || []).length > 2
                ? `<p class="text-gray-400">+${pedido.productos.length - 2} más</p>` : '';

            const botonesEstado = (() => {
                if (estado === 'pedido')
                    return `<button onclick="confirmarAccion(${pedido.id_pedido},'confirmado')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg text-[11px] font-bold">✅ Confirmar</button>
                            <button onclick="confirmarAccion(${pedido.id_pedido},'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>`;
                if (estado === 'confirmado')
                    return `<button onclick="confirmarAccion(${pedido.id_pedido},'en_transito')" class="flex-1 bg-blue-500 text-white py-1.5 rounded-lg text-[11px] font-bold">🚚 Enviar</button>
                            <button onclick="confirmarAccion(${pedido.id_pedido},'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>`;
                if (estado === 'en_transito') {
                    const esCredito = pedido.modalidad_pago === 'credito';
                    return `<button onclick="confirmarAccion(${pedido.id_pedido},'entregado')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg text-[11px] font-bold">${esCredito ? '📦 Entregar y cobrar' : '📦 Entregado'}</button>
                            <button onclick="confirmarAccion(${pedido.id_pedido},'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>`;
                }
                if (estado === 'credito' && pedido.deuda_restante > 0)
                    return `<button onclick="abrirModalAbono(${pedido.id_pedido},${pedido.deuda_restante})" class="w-full bg-purple-500 text-white py-1.5 rounded-lg text-[11px] font-bold">💰 Registrar Abono</button>`;
                return '';
            })();

            div.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 text-sm cliente-nombre">${pedido.cliente_nombre || ''} ${pedido.cliente_apellido || ''}</h3>
                        <p class="text-[11px] text-gray-400">Pedido #${pedido.id_pedido}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-xs font-black text-gray-700 mr-1">$${parseFloat(pedido.total || 0).toFixed(2)}</span>
                        <button onclick="editarPedido(${pedido.id_pedido})" class="p-1 bg-amber-50 text-amber-600 rounded-md hover:bg-amber-100" title="Editar"><i data-lucide="edit-2" class="w-3 h-3"></i></button>
                        <button onclick="verDetallePedido(${pedido.id_pedido})" class="p-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100" title="Ver detalle"><i data-lucide="eye" class="w-3 h-3"></i></button>
                        <button onclick="confirmarEliminarPedido(${pedido.id_pedido})" class="p-1 bg-red-50 text-red-500 rounded-md hover:bg-red-100" title="Eliminar"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
                    </div>
                </div>
                <div class="text-[11px] text-gray-500 space-y-0.5 mb-2 productos-lista">${prodsHtml || '<p class="text-gray-300 italic">Sin productos</p>'}${masProds}</div>
                ${pedido.id_repartidor && pedido.repartidor_nombre ? `<div class="flex items-center gap-1 text-[10px] text-gray-400 mt-1 pt-1 border-t border-gray-100"><i data-lucide="user" class="w-3 h-3"></i><span>${pedido.repartidor_nombre} ${pedido.repartidor_apellido || ''}</span></div>` : ''}
                ${estado === 'credito' && pedido.deuda_restante > 0 ? `<div class="mt-1 text-[11px] font-semibold text-purple-600">Saldo: $${parseFloat(pedido.deuda_restante).toFixed(2)}</div>` : ''}
                <div class="mt-2 flex gap-2">${botonesEstado}</div>`;
            return div;
        }


        function normalizarTexto(txt) {
            return (txt || '').toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, ''); // quita tildes
        }

        function inicializarBuscador() {
            const buscador   = document.getElementById('buscador-pedidos');
            const btnLimpiar = document.getElementById('btn-limpiar-busqueda');
            if (!buscador) return;

            buscador.addEventListener('input', () => {
                const q = normalizarTexto(buscador.value.trim());
                if (!q) { limpiarBusqueda(); return; }
                btnLimpiar.classList.remove('hidden');
                filtrarTarjetasKanban(q);
            });

            buscador.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') limpiarBusqueda();
            });
        }

        function filtrarTarjetasKanban(q) {
            const panel  = document.getElementById('resultado-busqueda');
            const lista  = document.getElementById('lista-resultado-busqueda');
            const titulo = document.getElementById('titulo-resultado-busqueda');

            const tarjetas = document.querySelectorAll('.tarjeta-pedido');
            const encontradas = [];

            tarjetas.forEach(tarjeta => {
                const cliente    = normalizarTexto(tarjeta.dataset.cliente || '');
       
                const repSpan    = tarjeta.querySelector('.flex.items-center.gap-1 span');
                const repartidor = normalizarTexto(repSpan ? repSpan.textContent : '');

                if (cliente.includes(q) || repartidor.includes(q)) {
                    encontradas.push(tarjeta);
                }
            });

            panel.classList.remove('hidden');
            lista.innerHTML = '';

            if (!encontradas.length) {
                titulo.textContent = 'Sin resultados';
                lista.innerHTML = '<p class="text-gray-400 text-sm text-center py-4">No se encontraron pedidos con ese nombre</p>';
                return;
            }

            titulo.textContent = `${encontradas.length} resultado(s)`;

            const estadoLabels = {
                pedido:      { label: '📥 Nuevo',      cls: 'bg-rose-100 text-rose-700' },
                confirmado:  { label: '✅ Confirmado', cls: 'bg-blue-100 text-blue-700' },
                en_transito: { label: '🚚 En Tránsito',cls: 'bg-amber-100 text-amber-700' },
                entregado:   { label: '🏆 Entregado',  cls: 'bg-emerald-100 text-emerald-700' },
                cancelado:   { label: '❌ Cancelado',  cls: 'bg-gray-100 text-gray-700' },
                credito:     { label: '💰 Crédito',    cls: 'bg-purple-100 text-purple-700' },
                pagado:      { label: '✅ Pagado',     cls: 'bg-green-100 text-green-700' },
            };

            encontradas.forEach(tarjeta => {
                const idPedido    = tarjeta.dataset.id;
                const columna     = tarjeta.closest('.kanban-column');
                const estadoKey   = columna ? columna.dataset.estado : '';
                const est         = estadoLabels[estadoKey] || { label: estadoKey, cls: 'bg-gray-100 text-gray-700' };

                const nombreCliente = tarjeta.querySelector('.cliente-nombre')?.textContent.trim() || '';
                const productos     = tarjeta.querySelector('.productos-lista')?.textContent.trim().replace(/\s+/g, ' ') || 'Sin productos';
                const totalSpan     = tarjeta.querySelector('.text-xs.font-black');
                const total         = totalSpan ? totalSpan.textContent.trim() : '';

                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 hover:shadow-sm transition-all cursor-pointer';
                div.onclick = () => verDetallePedido(idPedido);
                div.innerHTML = `
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="font-bold text-gray-800 text-sm">${nombreCliente}</span>
                            <span class="text-xs text-gray-400">Pedido #${idPedido}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 truncate">${productos}</p>
                    </div>
                    <div class="flex items-center gap-2 ml-3 shrink-0">
                        <span class="text-xs font-bold text-gray-700">${total}</span>
                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full ${est.cls}">${est.label}</span>
                        <button onclick="event.stopPropagation(); verDetallePedido(${idPedido})" class="p-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                            <i data-lucide="eye" class="w-3 h-3"></i>
                        </button>
                    </div>`;
                lista.appendChild(div);
            });

            lucide.createIcons();
        }

        function limpiarBusqueda() {
            document.getElementById('buscador-pedidos').value = '';
            document.getElementById('resultado-busqueda').classList.add('hidden');
            document.getElementById('btn-limpiar-busqueda').classList.add('hidden');
        }

  
        async function confirmarAccion(idPedido, nuevoEstado) {
            const msgs = {
                confirmado:  ['¿Confirmar este pedido?',        '✅ Confirmar'],
                cancelado:   ['¿Cancelar este pedido?',         '❌ Cancelar'],
                entregado:   ['¿Marcar como entregado?',        '📦 Entregar'],
                en_transito: ['¿Enviar pedido a repartidor?',   '🚚 Enviar'],
            };
            const [msg, btn] = msgs[nuevoEstado] || ['¿Continuar?', 'Aceptar'];

            const result = await Swal.fire({
                title: msg, icon: 'question',
                showCancelButton: true, cancelButtonText: 'No',
                confirmButtonColor: '#E91E63', confirmButtonText: btn,
            });
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const res  = await fetch(BASE_URL + 'pedidos/cambiarEstado', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ id_pedido: idPedido, nuevo_estado: nuevoEstado }),
                });
                const data = await res.json();
                Swal.close();
                if (data.success) {
                    if (data.stats) actualizarStatsUI(data.stats);
                    Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'No se pudo actualizar el estado', 'error');
                }
            } catch (e) {
                Swal.close();
                console.error(e);
                Swal.fire('Error', 'Error de conexión al servidor', 'error');
            }
        }

        function confirmarEliminarPedido(id) {
            Swal.fire({
                title: '¿Eliminar pedido?', text: 'Esta acción no se puede deshacer',
                icon: 'warning', showCancelButton: true, cancelButtonText: 'Cancelar',
                confirmButtonColor: '#E91E63', confirmButtonText: 'Sí, eliminar',
            }).then(r => { if (r.isConfirmed) window.location.href = BASE_URL + 'pedidos/eliminar/' + id; });
        }

   
        function verDetallePedido(idPedido) {
            fetch(BASE_URL + 'pedidos/obtenerDetallePedido?id_pedido=' + idPedido)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { Swal.fire('Error', 'No se pudo cargar el detalle', 'error'); return; }
                const p = data.pedido;

                let prodsHtml = '<div class="space-y-2">';
                if (data.detalles?.length) {
                    data.detalles.forEach(d => {
                        prodsHtml += `<div class="flex justify-between items-center border-b pb-2">
                            <div><span class="font-medium">${d.nombre_producto}</span><span class="text-xs text-gray-400 ml-1">(${d.unidad_venta})</span></div>
                            <div class="text-right"><div>x${d.cantidad}</div><div class="text-sm font-medium">$${(d.cantidad * d.precio_venta).toFixed(2)}</div></div>
                        </div>`;
                    });
                } else prodsHtml += '<p class="text-gray-400 text-center">Sin productos</p>';
                prodsHtml += '</div>';

                let estadosHtml = '<div class="space-y-2 max-h-40 overflow-y-auto">';
                if (data.historial_estados?.length) {
                    data.historial_estados.forEach(e => {
                        const cls = {
                            pedido:'bg-rose-100 text-rose-700', confirmado:'bg-blue-100 text-blue-700',
                            en_transito:'bg-amber-100 text-amber-700', entregado:'bg-emerald-100 text-emerald-700',
                            cancelado:'bg-gray-100 text-gray-700', credito:'bg-purple-100 text-purple-700',
                            pagado:'bg-green-100 text-green-700',
                        }[e.estado] || 'bg-gray-100 text-gray-700';
                        estadosHtml += `<div class="flex justify-between p-2 rounded-lg ${cls}">
                            <span class="font-medium">${e.estado}</span>
                            <span class="text-xs">${new Date(e.fecha).toLocaleString('es-MX')}</span>
                        </div>`;
                    });
                } else estadosHtml += '<p class="text-gray-400 text-center">Sin historial</p>';
                estadosHtml += '</div>';

                let html = `<div class="space-y-5">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl">
                        <h3 class="font-bold text-blue-800 mb-3">👤 Cliente</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><span class="text-gray-500">Nombre:</span> <span class="font-medium">${p.cliente_nombre} ${p.cliente_apellido || ''}</span></div>
                            <div><span class="text-gray-500">Teléfono:</span> <span class="font-medium">${p.cliente_telefono || 'N/A'}</span></div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-rose-50 to-orange-50 p-4 rounded-xl">
                        <h3 class="font-bold text-rose-800 mb-3">📦 Pedido</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><span class="text-gray-500">Pedido #:</span> <span class="font-medium">${p.id_pedido}</span></div>
                            <div><span class="text-gray-500">Fecha:</span> <span class="font-medium">${p.fecha || 'N/A'}</span></div>
                            <div><span class="text-gray-500">Entrega programada:</span> <span class="font-medium">${p.fecha_entrega_programada || 'Pendiente'}</span></div>
                            <div><span class="text-gray-500">Horario:</span> <span class="font-medium">${p.horario_entrega || 'No especificado'}</span></div>
                            <div><span class="text-gray-500">Repartidor:</span> <span class="font-medium">${p.repartidor_nombre || 'Sin asignar'} ${p.repartidor_apellido || ''}</span></div>
                            <div><span class="text-gray-500">Pago:</span> <span class="font-medium">${p.modalidad_pago === 'credito' ? '💰 Crédito' : '💵 Contado'}</span></div>
                            <div class="col-span-2"><span class="text-gray-500">Total:</span> <span class="font-bold text-rose-600 text-lg">$${parseFloat(p.total).toFixed(2)}</span></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl"><h3 class="font-bold text-gray-700 mb-3">🛒 Productos</h3>${prodsHtml}</div>`;

                if (p.modalidad_pago === 'credito') {
                    let abonosHtml = '<div class="space-y-2">';
                    if (data.historial_abonos?.length) {
                        data.historial_abonos.forEach(a => {
                            abonosHtml += `<div class="flex justify-between p-2 bg-gray-50 rounded-lg">
                                <span>${new Date(a.fecha_abono).toLocaleDateString('es-MX')}</span>
                                <span class="text-green-600 font-bold">+$${parseFloat(a.monto).toFixed(2)}</span>
                            </div>`;
                        });
                    } else abonosHtml += '<p class="text-gray-400 text-center">Sin abonos registrados</p>';
                    abonosHtml += '</div>';

                    html += `<div class="bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-xl">
                        <h3 class="font-bold text-purple-800 mb-3">💳 Crédito</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><span class="text-gray-500">Total:</span> <span class="font-medium">$${parseFloat(p.total).toFixed(2)}</span></div>
                            <div><span class="text-gray-500">Deuda restante:</span> <span class="font-bold text-rose-600">$${parseFloat(p.deuda_restante).toFixed(2)}</span></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl"><h3 class="font-bold text-gray-700 mb-3">📋 Historial de Abonos</h3>${abonosHtml}</div>`;
                }

                html += `<div class="bg-gray-50 p-4 rounded-xl"><h3 class="font-bold text-gray-700 mb-3">🕐 Historial de Estados</h3>${estadosHtml}</div></div>`;

                document.getElementById('detalleContenido').innerHTML = html;
                document.getElementById('modalDetalle').classList.remove('hidden');
                document.getElementById('modalDetalle').classList.add('flex');
                lucide.createIcons();
            }).catch(e => { console.error(e); Swal.fire('Error', 'Error al cargar el detalle', 'error'); });
        }

        function cerrarModalDetalle(event) {
            if (!event || event.target === document.getElementById('modalDetalle')) {
                document.getElementById('modalDetalle').classList.add('hidden');
                document.getElementById('modalDetalle').classList.remove('flex');
            }
        }

        function editarPedido(idPedido) {
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(BASE_URL + 'pedidos/obtenerDetallePedido?id_pedido=' + idPedido)
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (!data.success) { Swal.fire('Error', data.message || 'No se pudo cargar el pedido', 'error'); return; }
                const p = data.pedido;

                let productosExistentesHtml = '';
                if (data.detalles && data.detalles.length > 0) {
                    data.detalles.forEach(d => {
                        productosExistentesHtml += `
                            <tr data-producto-id="${d.id_producto3 || d.id_producto}">
                                <td class="py-2 text-left font-medium text-sm">${d.nombre_producto}</td>
                                <td class="py-2 text-center">
                                    <input type="number" value="${d.cantidad}" min="0.01" step="0.01"
                                        class="w-20 p-1 border rounded text-center cant-producto"
                                        data-id="${d.id_producto3 || d.id_producto}" data-precio="${d.precio_venta}">
                                </td>
                                <td class="py-2 text-center text-sm">${d.unidad_venta}</td>
                                <td class="py-2 text-right text-sm">$${parseFloat(d.precio_venta).toFixed(2)}</td>
                                <td class="py-2 text-right font-bold text-sm subtotal">$${(d.cantidad * d.precio_venta).toFixed(2)}</td>
                                <td class="py-2 text-center">
                                    <button onclick="eliminarFilaProducto(this)" class="text-rose-500 hover:text-rose-700">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                } else {
                    productosExistentesHtml = '<tr id="fila-vacia-edit"><td colspan="6" class="text-center py-4 text-gray-400">No hay productos</td></tr>';
                }

                const clienteActual    = CLIENTES_DATA.find(c => c.id == p.id_cliente1);
                const repartidorActual = REPARTIDORES_DATA.find(r => r.id == p.id_repartidor);

                document.getElementById('edit-id_pedido').textContent = p.id_pedido;
                document.getElementById('editarContenido').innerHTML = `
                    <form id="formEditarPedido">
                        <input type="hidden" name="id_pedido" value="${p.id_pedido}">
                        <input type="hidden" id="edit-cliente-id" name="id_cliente" value="${p.id_cliente1 || ''}">
                        <input type="hidden" id="edit-repartidor-id" name="id_repartidor" value="${p.id_repartidor || ''}">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Modalidad de Pago</label>
                                <select name="modalidad_pago" class="w-full p-2 border rounded-lg">
                                    <option value="contado" ${p.modalidad_pago == 'contado' ? 'selected' : ''}>💵 Contado</option>
                                    <option value="credito"  ${p.modalidad_pago == 'credito'  ? 'selected' : ''}>💰 Crédito</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cliente</label>
                                <div class="autocomplete-wrap">
                                    <input type="text" id="edit-cliente-texto" class="autocomplete-input"
                                        placeholder="Buscar cliente..." autocomplete="off"
                                        value="${clienteActual ? clienteActual.label : ''}">
                                    <div id="edit-cliente-lista" class="autocomplete-list"></div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Repartidor</label>
                                <div class="autocomplete-wrap">
                                    <input type="text" id="edit-repartidor-texto" class="autocomplete-input"
                                        placeholder="Buscar repartidor..." autocomplete="off"
                                        value="${repartidorActual ? repartidorActual.label : ''}">
                                    <div id="edit-repartidor-lista" class="autocomplete-list"></div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fecha Pedido</label>
                                <input type="date" name="fecha" value="${p.fecha || ''}" class="w-full p-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fecha Entrega</label>
                                <input type="date" name="fecha_entrega_programada" value="${p.fecha_entrega_programada || ''}" class="w-full p-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Horario Entrega</label>
                                <input type="time" name="horario_entrega" value="${p.horario_entrega || ''}" class="w-full p-2 border rounded-lg">
                            </div>
                        </div>
                        <div class="bg-gray-100 p-4 rounded-lg mb-4">
                            <h3 class="font-bold text-gray-700 mb-3 text-sm">🛒 Agregar Productos</h3>
                            <div class="flex gap-2 flex-wrap">
                                <input type="hidden" id="edit-prod-id">
                                <input type="hidden" id="edit-prod-precio">
                                <div class="autocomplete-wrap flex-1 min-w-[180px]">
                                    <input type="text" id="edit-sel-prod-texto" class="autocomplete-input text-sm" placeholder="Buscar producto..." autocomplete="off">
                                    <div id="edit-prod-lista" class="autocomplete-list"></div>
                                </div>
                                <input type="number" id="edit-cant-prod" value="1" min="0.01" step="0.01" class="w-24 p-2 border rounded-lg text-sm">
                                <select id="edit-unidad-prod" class="w-28 p-2 border rounded-lg text-sm">
                                    <option value="kilos">Kilos</option>
                                    <option value="domos">Domos</option>
                                    <option value="ramos">Ramos</option>
                                    <option value="caja">Caja</option>
                                    <option value="mazo">Mazo</option>
                                    <option value="arpilla">Arpilla</option>
                                </select>
                                <button type="button" onclick="agregarProductoEnEdicion()" class="bg-rose-500 hover:bg-rose-600 text-white px-4 rounded-lg text-sm font-bold">+ Agregar</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h3 class="font-bold text-gray-700 mb-3 text-sm">📦 Productos del Pedido</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="p-2 text-left">Producto</th>
                                            <th class="p-2 text-center">Cantidad</th>
                                            <th class="p-2 text-center">Unidad</th>
                                            <th class="p-2 text-right">Precio Unit.</th>
                                            <th class="p-2 text-right">Subtotal</th>
                                            <th class="p-2 text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="edit-productos-body">${productosExistentesHtml}</tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="4" class="p-2 text-right font-bold">TOTAL:</td>
                                            <td class="p-2 text-right font-bold text-rose-600" id="edit-total-modal">$${parseFloat(p.total || 0).toFixed(2)}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </form>
                    <div class="flex gap-3 mt-4">
                        <button onclick="guardarEdicionPedido()" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-xl font-bold transition-colors">💾 Guardar Cambios</button>
                        <button onclick="cerrarModalEditar()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-colors">Cancelar</button>
                    </div>`;

                document.getElementById('modalEditar').classList.remove('hidden');
                document.getElementById('modalEditar').classList.add('flex');
                lucide.createIcons();
                
                crearAutocomplete('edit-cliente-texto', 'edit-cliente-id', 'edit-cliente-lista', CLIENTES_DATA);
                crearAutocomplete('edit-repartidor-texto', 'edit-repartidor-id', 'edit-repartidor-lista', REPARTIDORES_DATA);
                crearAutocomplete('edit-sel-prod-texto', 'edit-prod-id', 'edit-prod-lista', PRODUCTOS_DATA, (item) => {
                    document.getElementById('edit-prod-precio').value = item.precio;
                });

                document.querySelectorAll('#edit-productos-body .cant-producto').forEach(input => {
                    input.addEventListener('change', function() {
                        const precio   = parseFloat(this.dataset.precio);
                        const cantidad = parseFloat(this.value);
                        const row      = this.closest('tr');
                        row.querySelector('.subtotal').textContent = '$' + (precio * cantidad).toFixed(2);
                        recalcularTotalEdicion();
                    });
                });

            }).catch(err => { Swal.close(); console.error(err); Swal.fire('Error', 'No se pudo cargar el pedido para editar', 'error'); });
        }

        function agregarProductoEnEdicion() {
            const id       = document.getElementById('edit-prod-id').value;
            const precio   = parseFloat(document.getElementById('edit-prod-precio').value);
            const nombre   = document.getElementById('edit-sel-prod-texto').value.trim();
            const cantidad = parseFloat(document.getElementById('edit-cant-prod').value);
            const unidad   = document.getElementById('edit-unidad-prod').value;

            if (!id)                              { Swal.fire('Error', 'Seleccione un producto de la lista', 'error'); return; }
            if (isNaN(cantidad) || cantidad <= 0) { Swal.fire('Error', 'Cantidad inválida', 'error');                  return; }
            if (!precio || isNaN(precio))         { Swal.fire('Error', 'Este producto no tiene precio', 'error');      return; }

            const filaExistente = document.querySelector(`#edit-productos-body tr[data-producto-id="${id}"]`);
            if (filaExistente) {
                const inputCant = filaExistente.querySelector('.cant-producto, .cant-nueva');
                const nuevaCant = parseFloat(inputCant.value) + cantidad;
                inputCant.value = nuevaCant;
                filaExistente.querySelector('.subtotal').textContent = '$' + (parseFloat(inputCant.dataset.precio) * nuevaCant).toFixed(2);
                recalcularTotalEdicion();
                document.getElementById('edit-cant-prod').value = 1;
                return;
            }

            const filaVacia = document.getElementById('fila-vacia-edit');
            if (filaVacia) filaVacia.remove();

            const subtotal = precio * cantidad;
            const tr = document.createElement('tr');
            tr.setAttribute('data-producto-id', id);
            tr.innerHTML = `
                <td class="py-2 text-left font-medium text-sm">${nombre}</td>
                <td class="py-2 text-center">
                    <input type="number" value="${cantidad}" min="0.01" step="0.01"
                        class="w-20 p-1 border rounded text-center cant-nueva"
                        data-id="${id}" data-precio="${precio}">
                </td>
                <td class="py-2 text-center text-sm">${unidad}</td>
                <td class="py-2 text-right text-sm">$${precio.toFixed(2)}</td>
                <td class="py-2 text-right font-bold text-sm subtotal">$${subtotal.toFixed(2)}</td>
                <td class="py-2 text-center">
                    <button onclick="eliminarFilaProducto(this)" class="text-rose-500 hover:text-rose-700">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>`;
            document.getElementById('edit-productos-body').appendChild(tr);

            tr.querySelector('.cant-nueva').addEventListener('change', function() {
                const p = parseFloat(this.dataset.precio);
                const c = parseFloat(this.value);
                tr.querySelector('.subtotal').textContent = '$' + (p * c).toFixed(2);
                recalcularTotalEdicion();
            });

            document.getElementById('edit-cant-prod').value = 1;
            document.getElementById('edit-sel-prod-texto').value = '';
            document.getElementById('edit-prod-id').value = '';
            document.getElementById('edit-prod-precio').value = '';
            recalcularTotalEdicion();
            lucide.createIcons();
        }

        function eliminarFilaProducto(btn) {
            btn.closest('tr').remove();
            const body = document.getElementById('edit-productos-body');
            if (!body.querySelector('tr')) {
                body.innerHTML = '<tr id="fila-vacia-edit"><td colspan="6" class="text-center py-4 text-gray-400">No hay productos</td></tr>';
            }
            recalcularTotalEdicion();
        }

        function recalcularTotalEdicion() {
            let total = 0;
            document.querySelectorAll('#edit-productos-body tr').forEach(row => {
                const sub = row.querySelector('.subtotal');
                if (sub) total += parseFloat(sub.textContent.replace('$', '')) || 0;
            });
            document.getElementById('edit-total-modal').textContent = '$' + total.toFixed(2);
        }

        function guardarEdicionPedido() {
            const form     = document.getElementById('formEditarPedido');
            const formData = new FormData(form);

            const clienteId    = document.getElementById('edit-cliente-id').value;
            const repartidorId = document.getElementById('edit-repartidor-id').value;
            formData.set('id_cliente',    clienteId);
            formData.set('id_repartidor', repartidorId);

            const productos = [];
            document.querySelectorAll('#edit-productos-body tr[data-producto-id]').forEach(row => {
                const id    = row.dataset.productoId;
                const input = row.querySelector('.cant-producto, .cant-nueva');
                if (input && id) {
                    const cantidad = parseFloat(input.value);
                    const precio   = parseFloat(input.dataset.precio);
                    if (!isNaN(cantidad) && cantidad > 0) productos.push({ id, cantidad, precio });
                }
            });

            if (!clienteId)             { Swal.fire('Error', 'Seleccione un cliente', 'error');         return; }
            if (productos.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }

            formData.append('productos', JSON.stringify(productos));
            formData.append('total', parseFloat(document.getElementById('edit-total-modal').textContent.replace('$', '')) || 0);

            Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(BASE_URL + 'pedidos/actualizar', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) Swal.fire('¡Éxito!', 'Pedido actualizado correctamente', 'success').then(() => location.reload());
                else Swal.fire('Error', data.message || 'Error al actualizar', 'error');
            })
            .catch(() => { Swal.close(); Swal.fire('Error', 'Error de conexión', 'error'); });
        }

        function cerrarModalEditar(event) {
            if (!event || event.target === document.getElementById('modalEditar')) {
                document.getElementById('modalEditar').classList.add('hidden');
                document.getElementById('modalEditar').classList.remove('flex');
            }
        }

        function abrirModalAbono(idPedido, deudaActual) {
            document.getElementById('abono_id_pedido').value           = idPedido;
            document.getElementById('abono_monto').value               = '';
            document.getElementById('abono_fecha').value               = new Date().toISOString().slice(0, 10);
            document.getElementById('abono_deuda_display').textContent = '$' + parseFloat(deudaActual).toFixed(2);
            document.getElementById('abono_monto').max                 = deudaActual;
            document.getElementById('modalAbono').classList.remove('hidden');
            document.getElementById('modalAbono').classList.add('flex');
        }

        async function registrarAbono() {
            const idPedido = document.getElementById('abono_id_pedido').value;
            const monto    = parseFloat(document.getElementById('abono_monto').value);
            const fecha    = document.getElementById('abono_fecha').value;

            if (!monto || monto <= 0) { Swal.fire('Error', 'Ingresa un monto válido mayor a 0', 'error'); return; }
            if (!fecha)               { Swal.fire('Error', 'Selecciona una fecha de abono', 'error');       return; }

            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const res  = await fetch(BASE_URL + 'pedidos/registrarAbono', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ id_pedido: idPedido, monto, fecha_abono: fecha }),
                });
                const data = await res.json();
                Swal.close();
                if (data.success) {
                    cerrarModalAbono();
                    Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'No se pudo registrar el abono', 'error');
                }
            } catch (e) {
                Swal.close();
                console.error(e);
                Swal.fire('Error', 'Error de conexión al servidor', 'error');
            }
        }

        function cerrarModalAbono(event) {
            if (!event || event.target === document.getElementById('modalAbono')) {
                document.getElementById('modalAbono').classList.add('hidden');
                document.getElementById('modalAbono').classList.remove('flex');
            }
        }

        function abrirModalNuevoPedido() {
            productosSeleccionados = [];
            renderizarProductos();
            document.getElementById('np-fecha_pedido').value  = new Date().toISOString().slice(0, 10);
            document.getElementById('np-modalidad').value     = 'contado';
            document.getElementById('np-cliente').value       = '';
            document.getElementById('np-cliente-texto').value = '';
            document.getElementById('np-repartidor').value       = '';
            document.getElementById('np-repartidor-texto').value = '';
            document.getElementById('np-fecha_entrega').value = '';
            document.getElementById('np-horario').value       = '';
            document.getElementById('np-sel-prod-texto').value = '';
            document.getElementById('np-prod-id').value        = '';
            document.getElementById('np-prod-precio').value    = '';
            document.getElementById('modalNuevoPedido').classList.remove('hidden');
            document.getElementById('modalNuevoPedido').classList.add('flex');
        }

        function cerrarModalNuevoPedido(event) {
            if (!event || event.target === document.getElementById('modalNuevoPedido')) {
                document.getElementById('modalNuevoPedido').classList.add('hidden');
                document.getElementById('modalNuevoPedido').classList.remove('flex');
            }
        }

        function agregarFilaProducto() {
            const id     = document.getElementById('np-prod-id').value;
            const precio = parseFloat(document.getElementById('np-prod-precio').value);
            const nombre = document.getElementById('np-sel-prod-texto').value.trim();
            const cant   = parseFloat(document.getElementById('np-cant-prod').value);
            const unidad = document.getElementById('np-unidad-prod').value;

            if (!id)     { Swal.fire('Error', 'Seleccione un producto de la lista', 'error'); return; }
            if (isNaN(cant) || cant <= 0) { Swal.fire('Error', 'Cantidad inválida', 'error'); return; }
            if (!precio || isNaN(precio) || precio <= 0) { Swal.fire('Error', 'Ingresa el precio para este producto', 'error'); return; }

            const existente = productosSeleccionados.find(p => p.id === id);
            if (existente) {
                existente.cant += cant;
                existente.sub   = existente.cant * existente.precio;
            } else {
                productosSeleccionados.push({ id, nombre, cant, precio, unidad, sub: precio * cant });
            }
            document.getElementById('np-cant-prod').value      = 1;
            document.getElementById('np-sel-prod-texto').value = '';
            document.getElementById('np-prod-id').value        = '';
            document.getElementById('np-prod-precio').value    = '';
            renderizarProductos();
        }

        function eliminarProducto(index) {
            productosSeleccionados.splice(index, 1);
            renderizarProductos();
        }

        function renderizarProductos() {
            const body = document.getElementById('lista-productos-body');
            if (!body) return;
            if (productosSeleccionados.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-400">No hay productos agregados</td></tr>';
                const tm = document.getElementById('total-modal');
                if (tm) tm.textContent = '$0.00';
                return;
            }
            let html = '', total = 0;
            productosSeleccionados.forEach((p, i) => {
                total += p.sub;
                html  += `<tr class="border-b">
                    <td class="py-2 text-left font-medium">${p.nombre}</td>
                    <td class="py-2 text-center">${p.cant}</td>
                    <td class="py-2 text-center">${p.unidad}</td>
                    <td class="py-2 text-right">$${p.precio.toFixed(2)}</td>
                    <td class="py-2 text-right font-bold">$${p.sub.toFixed(2)}</td>
                    <td class="py-2 text-center">
                        <button onclick="eliminarProducto(${i})" class="text-rose-500 hover:text-rose-700">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </td>
                </tr>`;
            });
            body.innerHTML = html;
            const tm = document.getElementById('total-modal');
            if (tm) tm.textContent = `$${total.toFixed(2)}`;
            lucide.createIcons();
        }

        async function guardarNuevoPedido() {
            const clienteId    = document.getElementById('np-cliente').value;
            const clienteTexto = document.getElementById('np-cliente-texto').value.trim();
            const repId        = document.getElementById('np-repartidor').value;
            const repTexto     = document.getElementById('np-repartidor-texto').value.trim();

            if (!clienteId && clienteTexto) {
                Swal.fire('Cliente no válido', 'Debes seleccionar un cliente registrado de la lista. Si no existe, regístralo primero.', 'error'); return;
            }
            if (!clienteId) {
                Swal.fire('Error', 'Seleccione un cliente de la lista', 'error'); return;
            }
            if (repTexto && !repId) {
                Swal.fire('Repartidor no válido', 'Debes seleccionar un repartidor registrado de la lista. Si no existe, regístralo primero.', 'error'); return;
            }
            if (productosSeleccionados.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }

            const total = productosSeleccionados.reduce((s, p) => s + p.sub, 0);

            Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const res = await fetch(BASE_URL + 'pedidos/guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        id_cliente:    clienteId,
                        id_repartidor: repId,
                        modalidad_pago: document.getElementById('np-modalidad').value,
                        total,
                        fecha_pedido:  document.getElementById('np-fecha_pedido').value,
                        fecha_entrega: document.getElementById('np-fecha_entrega').value,
                        horario:       document.getElementById('np-horario').value,
                        productos:     JSON.stringify(productosSeleccionados.map(p => ({ id: p.id, cantidad: p.cant, precio: p.precio, unidad: p.unidad }))),
                    }),
                });
                const data = await res.json();
                Swal.close();
                if (data.success) {
                    cerrarModalNuevoPedido();
                    Swal.fire('¡Éxito!', 'Pedido guardado correctamente', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Error al guardar', 'error');
                }
            } catch (e) {
                Swal.close();
                console.error(e);
                Swal.fire('Error', 'Error de conexión al servidor', 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initKanbanScroll();
            inicializarBuscador();
            lucide.createIcons();

            crearAutocomplete('np-cliente-texto',    'np-cliente',    'np-cliente-lista',    CLIENTES_DATA,    null,         'Se debe registrar primero al cliente');
            crearAutocomplete('np-repartidor-texto', 'np-repartidor', 'np-repartidor-lista', REPARTIDORES_DATA, null,        'Se debe registrar primero al repartidor');
            crearAutocomplete('np-sel-prod-texto',   'np-prod-id',    'np-prod-lista',       PRODUCTOS_DATA,   (item) => {
                document.getElementById('np-prod-precio').value = item.precio;
            }, 'Se debe registrar primero el producto');
        });
    </script>
</body>
</html>