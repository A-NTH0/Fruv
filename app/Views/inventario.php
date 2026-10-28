<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('css/inventario.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/merma.css') ?>">  
</head>
<body class="bg-[#fbfbfb]">
    <?= view('plantilla/menu') ?>

    <main class="ml-64 p-10">
        <header class="mb-10">
            <h1 class="text-3xl font-bold text-[#1f2937] tracking-tight">Inventario</h1>
            <p class="text-gray-400 text-base mt-1">Control completo de entradas, existencias y mermas</p>
        </header>

        <nav class="tabs bg-[#f3f4f6] p-1.5 rounded-full w-fit mb-8 flex gap-2">
            <button onclick="cambiarPestaña('seccion-entradas', this)" class="btn-tab active px-6 py-2 rounded-full flex items-center gap-2 text-sm font-medium transition-all">
                <i data-lucide="package-plus" class="w-4 h-4"></i> Registro de Entradas
            </button>
            <button onclick="cambiarPestaña('seccion-existencias', this)" class="btn-tab px-6 py-2 rounded-full flex items-center gap-2 text-sm font-medium text-gray-500 transition-all">
                <i data-lucide="boxes" class="w-4 h-4"></i> Control de Existencias
            </button>
            <button onclick="cambiarPestaña('seccion-mermas', this)" class="btn-tab px-6 py-2 rounded-full flex items-center gap-2 text-sm font-medium text-gray-500 transition-all">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i> Registro de Mermas
            </button>
        </nav>

        <!-- ========================================== -->
        <!-- PESTAÑA 1: REGISTRO DE ENTRADAS            -->
        <!-- ========================================== -->
        <div id="seccion-entradas" class="tab-content block">
            <div class="flex flex-col lg:flex-row gap-8 items-start">

                <div class="w-full lg:w-[450px] bg-white p-8 rounded-[32px] border border-[#f1f1f1] shadow-sm">
                    <h2 class="text-2xl font-bold text-gray-800 mb-8 tracking-tight">Nueva Entrada</h2>
                    
                    <form action="<?= base_url('inventario/registrar-entrada') ?>" method="post" class="space-y-5">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Buscar Producto</label>
                            <div class="relative">
                                <input list="lista-productos" id="busqueda_producto" name="nombre_producto" 
                                    placeholder="Escribe para buscar..."
                                    autocomplete="off"
                                    class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20">
                                <datalist id="lista-productos">
                                    <?php 
                                    $mapaExis = [];
                                    foreach ($existencias as $ex) { $mapaExis[$ex['nombre']] = $ex['exis_venta']; }
                                    $mapaCompras = [];
                                    foreach ($comprasAnteriores as $compra) {
                                        $mapaCompras[$compra['id_producto2']] = $compra;
                                    }

                                    foreach ($productos as $p): 
                                        $stockActual = $mapaExis[$p['nombre']] ?? 0;
                                        $compraAnterior = $mapaCompras[$p['id_producto']] ?? [];
                                    ?>
                                        <option
                                            data-id="<?= esc($p['id_producto']) ?>"
                                            data-stock="<?= esc($stockActual) ?>"
                                            data-precio="<?= esc($compraAnterior['precio_compra'] ?? '') ?>"
                                            data-unidad-compra="<?= esc($compraAnterior['unidad_compra'] ?? '') ?>"
                                            data-unidad-venta="<?= esc($compraAnterior['unidad_venta'] ?? '') ?>"
                                            data-equivalente="<?= esc($compraAnterior['equivalente'] ?? '') ?>"
                                            data-tiene-historial="<?= isset($compraAnterior['id_producto2']) ? '1' : '0' ?>"
                                            value="<?= esc($p['nombre']) ?>"
                                            label="<?= esc($p['nombre']) ?> (Stock actual: <?= esc($stockActual) ?>)"></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <input type="hidden" name="id_producto2" id="id_producto_hidden">
                            </div>
                            <p id="ayuda-compra-anterior" class="mt-2 ml-1 text-xs text-gray-400">
                                Selecciona un producto para cargar los datos de su última compra.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">F. Registro</label>
                                <input type="date" id="fecha_entrada" name="fecha_entrada" 
                                    class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">F. Caducidad (+5 Días)</label>
                                <input type="date" id="fecha_caducidad" name="fecha_caducidad" 
                                    class="w-full bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-3.5 text-emerald-700 font-bold cursor-not-allowed" readonly required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Cantidad Recibida</label>
                                <input type="number" id="cantidad_entrada" name="cantidad" step="0.01" placeholder="0.00" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required min="0">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Precio Compra ($)</label>
                                <input type="number" id="precio_entrada" name="precio_compra" step="0.01" placeholder="$ 0.00" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required min="0">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Se compró en (U. Compra)</label>
                                <select name="unidad_compra" id="unidad_compra" onchange="cambiarTextoConversion()" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required>
                                    <option value="caja">Caja</option>
                                    <option value="mazo">Mazo</option>
                                    <option value="arpilla">Arpilla</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Se venderá por (U. Venta)</label>
                                <select name="unidad_venta" id="unidad_venta" onchange="cambiarTextoConversion()" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required>
                                    <option value="kilos">Kilos</option>
                                    <option value="domos">Domos</option>
                                    <option value="ramos">Ramos</option>
                                </select>
                            </div>
                        </div>

                        <!-- Pregunta Dinámica de Conversión -->
                        <div class="bg-amber-50/80 p-4 rounded-2xl border border-amber-200 space-y-3">
                            <div>
                                <label id="pregunta_dinamica" class="block text-xs font-extrabold text-amber-800 mb-1.5">
                                    ¿Cuántos Kilos trae cada Caja?
                                </label>
                                <input type="number" id="equivalente" name="equivalente" step="0.01" placeholder="Ej: 18.5" 
                                    class="w-full bg-white border border-amber-200 rounded-xl px-4 py-2.5 text-gray-800 font-bold focus:ring-2 focus:ring-amber-400 outline-none" required min="0">
                                <small class="text-[11px] text-amber-700 block mt-1">
                                    El sistema multiplicará esto por la cantidad recibida.
                                </small>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-amber-200/60">
                                <span class="text-[11px] font-bold text-amber-900 uppercase tracking-wider">Total a sumar al stock:</span>
                                <div class="flex items-center gap-1.5">
                                    <input type="number" id="conversion" name="conversion" step="0.01" readonly 
                                        class="w-28 bg-transparent border-none text-right font-black text-base text-amber-900 focus:ring-0 p-0" value="0.00" required>
                                    <span id="unidad_resumen_texto" class="text-xs font-bold text-amber-800">Kilos</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-[#a1143a] text-white py-4 rounded-2xl font-bold text-sm uppercase tracking-widest hover:bg-[#820f2e] transform active:scale-[0.98] transition-all shadow-lg shadow-[#a1143a]/20 mt-4">
                            Guardar Entrada
                        </button>
                    </form>
                </div>

                <!-- Historial de Entradas -->
                <div class="flex-1 bg-white p-8 rounded-[32px] border border-[#f1f1f1] shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
                        <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Historial de Entradas</h2>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i data-lucide="search" class="w-4 h-4"></i>
                            </span>
                            <input type="text" id="filtro-entradas"
                                placeholder="Buscar producto..."
                                autocomplete="off"
                                class="pl-9 pr-4 py-2 bg-[#f9fafb] border-none rounded-xl text-sm text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20 w-48">
                        </div>
                    </div>

                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-400 text-[10px] uppercase tracking-widest border-b">
                                <th class="pb-4 font-bold">Producto</th>
                                <th class="pb-4 font-bold text-center">Cantidad</th>
                                <th class="pb-4 font-bold text-right">Precio</th>
                                <th class="pb-4 font-bold text-center">Fecha</th>
                                <th class="pb-4 font-bold text-right">U. Venta</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-entradas" class="text-gray-600">
                            <?php foreach ($entradas as $entrada): ?>
                            <tr class="fila-entrada border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                                <td class="py-3.5 font-medium text-gray-900"><?= esc($entrada['nombre']) ?></td>
                                <td class="py-3.5 text-center"><?= esc($entrada['cantidad']) ?></td>
                                <td class="py-3.5 text-right font-semibold">$<?= number_format($entrada['precio_compra'], 2) ?></td>
                                <td class="py-3.5 text-center text-gray-400 text-sm"><?= esc($entrada['fecha']) ?></td>
                                <td class="py-3.5 text-right text-gray-400 text-sm"><?= esc($entrada['unidad_venta']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p id="sin-resultados-entradas" class="hidden text-center text-gray-400 text-sm py-8">
                        No se encontraron entradas.
                    </p>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- PESTAÑA 2: CONTROL DE EXISTENCIAS (LIMPIA) -->
        <!-- ========================================== -->
        <div id="seccion-existencias" class="tab-content hidden">
            <div class="mb-6">
                <div class="relative max-w-md">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </span>
                    <input type="text" id="filtro-existencias" 
                        placeholder="Filtrar productos en existencia..." 
                        class="pl-10 w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 text-sm focus:ring-2 focus:ring-[#a1143a]/10 outline-none"
                        autocomplete="off">
                </div>
            </div>
            
            <div class="bg-white p-8 rounded-[32px] border border-[#f1f1f1] shadow-sm">
                <h2 class="text-2xl font-bold text-gray-800 mb-8 tracking-tight">Control de Existencias</h2>
                <table class="w-full text-left" id="tablaExistencias">
                    <thead>
                        <tr class="text-gray-400 text-[10px] uppercase tracking-widest border-b">
                            <th class="pb-4 font-bold">Producto</th>
                            <th class="pb-4 font-bold text-center">Unidad</th>
                            <th class="pb-4 font-bold text-center">Total Bodega</th>
                            <th class="pb-4 font-bold text-center">Bloqueado</th>
                            <th class="pb-4 font-bold text-center">Disponible</th>
                            <th class="pb-4 font-bold text-center">Estado Stock</th>
                            <th class="pb-4 font-bold text-right">Radiografía</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existencias as $exis): ?>
                        <?php $stockDisp = (int) ($exis['exis_venta'] ?? 0); ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 font-bold text-gray-900 prod-nombre"><?= esc($exis['nombre']) ?></td>
                            <td class="py-4 text-center text-xs uppercase font-bold text-gray-400"><?= esc($exis['unidad_venta'] ?? 'kilos') ?></td>
                            <td class="py-4 text-center font-medium text-gray-700"><?= esc($exis['exis_total_general']) ?></td>
                            <td class="py-4 text-center text-amber-600 font-semibold"><?= esc($exis['exis_bloqueo']) ?></td>
                            <td class="py-4 text-center text-emerald-600 font-extrabold text-base"><?= $stockDisp ?></td>
                            
                            <td class="py-4 text-center">
                                <?php if ($stockDisp < 20): ?>
                                    <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-rose-100 text-rose-700">
                                        Stock Bajo
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-emerald-100 text-emerald-700">
                                        Disponible
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 text-right">
                                <button type="button" onclick="abrirRadiografia(<?= $exis['id_producto'] ?>, '<?= esc($exis['nombre']) ?>')" 
                                        class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all inline-flex items-center gap-1.5">
                                    📊 Detalles
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- PESTAÑA 3: REGISTRO DE MERMAS              -->
        <!-- ========================================== -->
        <div id="seccion-mermas" class="tab-content hidden">
            <div class="mb-6 flex justify-between items-center gap-4">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </span>
                    <input type="text" id="filtro-mermas" 
                        placeholder="Filtrar productos en merma..." 
                        class="pl-10 w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 text-sm focus:ring-2 focus:ring-[#a1143a]/10 outline-none">
                </div>
                <button id="btnRegistrarMerma" class="bg-[#a1143a] text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:opacity-90 transition-all shadow-md flex items-center gap-2 shrink-0">
                    <i data-lucide="plus" class="w-4 h-4"></i> Registrar Merma
                </button>
            </div>

            <div class="bg-white p-8 rounded-[32px] border border-[#f1f1f1] shadow-sm">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Historial de Mermas</h2>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 text-[10px] uppercase tracking-widest border-b">
                            <th class="pb-4 font-bold">Producto</th>
                            <th class="pb-4 font-bold text-center">Cantidad</th>
                            <th class="pb-4 font-bold text-center">Fecha</th>
                            <th class="pb-4 font-bold text-right">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mermas as $merma): ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 font-medium text-gray-900"><?= esc($merma['nombre_producto']) ?></td>
                            <td class="py-4 text-center font-bold text-rose-600">-<?= esc($merma['cantidad']) ?></td>
                            <td class="py-4 text-center text-gray-400 text-sm"><?= esc($merma['fecha']) ?></td>
                            <td class="py-4 text-right text-sm italic text-gray-400"><?= esc($merma['notas']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ========================================================== -->
    <!-- MODAL 1: REGISTRAR MERMA (CON OPCIÓN DE AGREGAR OTRA MERMA) -->
    <!-- ========================================================== -->
    <div id="myModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg p-8 rounded-[32px] shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <span class="close absolute top-6 right-6 text-gray-300 hover:text-gray-600 cursor-pointer text-2xl">&times;</span>
            <h2 class="text-2xl font-bold text-gray-800 mb-6 tracking-tight">Registrar Merma</h2>

            <!-- Datalist global de productos en existencia para todas las filas del modal -->
            <datalist id="lista-mermas">
                <?php
                $mapaExistencias = [];
                foreach ($existencias as $ex) {
                    $mapaExistencias[$ex['nombre']] = $ex['exis_venta'];
                }

                $productosVistos = [];
                foreach ($todasEntradas as $e):
                    $idProducto = $e['id_producto2'];
                    if (isset($productosVistos[$idProducto])) continue;
                    $productosVistos[$idProducto] = true;
                    $disp = $mapaExistencias[$e['nombre']] ?? '0';
                ?>
                    <option data-id="<?= $e['id_entrada'] ?>" data-stock="<?= esc($disp) ?>" value="<?= esc($e['nombre']) ?> (Disp: <?= esc($disp) ?>)"></option>
                <?php endforeach; ?>
            </datalist>

            <form id="formRegistrarMerma" action="<?= base_url('inventario/registrar-merma') ?>" method="POST" class="space-y-5">
                
                <!-- Contenedor dinámico de filas de merma -->
                <div id="contenedor-filas-merma" class="space-y-4 max-h-64 overflow-y-auto pr-1">
                    <div class="fila-merma-item bg-gray-50/70 p-4 rounded-2xl border border-gray-100 space-y-3 relative">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Buscar Producto en Existencia</label>
                            <input list="lista-mermas" 
                                   placeholder="Escribe el producto..." 
                                   autocomplete="off"
                                   oninput="sincronizarFilaMerma(this)"
                                   class="input-busqueda-merma w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20 outline-none"
                                   required>
                            <input type="hidden" name="id_entrada[]" class="input-id-entrada-merma">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Cantidad</label>
                            <input type="number" name="cantidad[]" min="0.01" step="0.01" placeholder="0.00" required 
                                   class="input-cantidad-merma w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#a1143a]/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Botón para agregar otra merma en el mismo modal -->
                <button type="button" onclick="agregarOtraFilaMerma()" 
                        class="w-full py-3 border-2 border-dashed border-[#a1143a]/30 text-[#a1143a] hover:bg-[#a1143a]/5 rounded-2xl font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Agregar otra merma
                </button>

                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Fecha</label>
                    <input type="date" id="fecha_merma" name="fecha" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Notas</label>
                    <textarea name="notas" rows="2" placeholder="Motivo de la merma..." class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 focus:ring-2 focus:ring-[#a1143a]/20 resize-none"></textarea>
                </div>
                <button type="submit" class="w-full bg-[#a1143a] text-white py-4 rounded-2xl font-bold text-sm uppercase tracking-widest shadow-lg shadow-[#a1143a]/20">
                    Confirmar Merma
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: RADIOGRAFÍA DE STOCK Y KARDEX     -->
    <!-- ========================================== -->
    <div id="modalRadiografia" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden items-center justify-center p-4" onclick="if(event.target === this) cerrarRadiografia()">
        <div class="bg-white w-full max-w-lg p-8 rounded-[32px] shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-5">
                <h3 class="text-xl font-extrabold text-gray-800">
                    📊 Radiografía: <span id="rad_nombre" class="text-[#a1143a] uppercase"></span>
                </h3>
                <button type="button" onclick="cerrarRadiografia()" class="text-gray-400 hover:text-gray-700 text-2xl font-bold">&times;</button>
            </div>

            <div class="space-y-3">
                <div class="bg-slate-50 p-4 rounded-2xl border-l-4 border-blue-500 flex justify-between items-center">
                    <div>
                        <p class="text-[11px] font-bold text-slate-500 uppercase">📦 Stock Físico en Bodega</p>
                        <p class="text-xs text-slate-400">Suma total en inventario</p>
                    </div>
                    <p id="rad_fisico" class="text-2xl font-black text-slate-800">0</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-amber-50/70 p-4 rounded-2xl border-l-4 border-amber-500">
                        <p class="text-[11px] font-bold text-amber-800 uppercase">🔒 Bloqueado</p>
                        <p id="rad_bloqueado" class="text-xl font-black text-slate-800 mt-1">0</p>
                        <p class="text-[10px] text-amber-700">En pedidos activos</p>
                    </div>
                    <div class="bg-emerald-50/70 p-4 rounded-2xl border-l-4 border-emerald-500">
                        <p class="text-[11px] font-bold text-emerald-800 uppercase">✅ Libre (Venta)</p>
                        <p id="rad_libre" class="text-xl font-black text-slate-800 mt-1">0</p>
                        <p class="text-[10px] text-emerald-700">Disponible para pedidos</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div class="bg-gray-50 p-3 rounded-xl text-center border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase">📈 Pedidos con este prod.</p>
                        <p id="rad_ventas" class="text-base font-extrabold text-gray-800 mt-0.5">0</p>
                    </div>
                    <div class="bg-rose-50/60 p-3 rounded-xl text-center border border-rose-100">
                        <p class="text-[10px] font-bold text-rose-700 uppercase">🗑️ Total Mermas</p>
                        <p id="rad_mermas" class="text-base font-extrabold text-rose-700 mt-0.5">0</p>
                    </div>
                </div>
            </div>

            <h4 class="mt-6 mb-3 text-xs font-extrabold text-gray-500 uppercase tracking-wider">📋 Historial de Movimientos (Kardex)</h4>
            <div class="max-h-52 overflow-y-auto border border-gray-100 rounded-2xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 sticky top-0 text-gray-400 uppercase text-[10px]">
                        <tr>
                            <th class="p-3 border-b">Fecha</th>
                            <th class="p-3 border-b">Movimiento</th>
                            <th class="p-3 border-b text-center">Cant.</th>
                            <th class="p-3 border-b">Detalle</th>
                        </tr>
                    </thead>
                    <tbody id="tablaKardex" class="divide-y divide-gray-50 text-gray-600">
                    </tbody>
                </table>
            </div>

            <button type="button" onclick="cerrarRadiografia()" class="w-full mt-6 bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-2xl font-bold text-xs uppercase tracking-widest transition-all">
                Cerrar Radiografía
            </button>
        </div>
    </div>

    <script>
        const BASE_URL = "<?= rtrim(base_url(), '/') ?>/";
    </script>
    <script src="<?= base_url('js/inventario.js') ?>?v=<?= filemtime(FCPATH . 'js/inventario.js') ?>"></script>
</body>
</html> 