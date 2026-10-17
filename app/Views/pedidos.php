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

    <!-- Modal Detalle -->
    <div id="modalDetalle" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalDetalle(event)">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">📄 Detalle del Pedido</h2>
                <button onclick="cerrarModalDetalle()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div id="detalleContenido" class="p-6"></div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="modalEditar" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4" onclick="cerrarModalEditar(event)">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">✏️ Editar Pedido #<span id="edit-id_pedido"></span></h2>
                <button onclick="cerrarModalEditar()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div id="editarContenido" class="p-6"></div>
        </div>
    </div>

    <!-- Modal Abono -->
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

    <!-- Modal Nuevo Pedido -->
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

    <!-- Variables globales de PHP hacia JS -->
    <script>
        const BASE_URL       = '<?= base_url() ?>/';
        const PAGINA_INICIAL = <?= (int) ($currentPage ?? 1) ?>;

        const CLIENTES_DATA = <?= json_encode(array_map(fn($c) => [
            'id'    => $c['id_cliente'],
            'label' => $c['nombre'] . ' ' . $c['ap_paterno'],
        ], $clientes)) ?>;

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
    </script>

    <!-- Archivo JavaScript externo -->
    <script src="<?= base_url('js/pedidos.js') ?>"></script>
</body>
</html>