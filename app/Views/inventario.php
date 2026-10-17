<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
                                    class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20">
                                <datalist id="lista-productos">
    <?php 
    // Creamos un mapa rápido de existencias para mostrar el stock en la lista
    $mapaExis = [];
    foreach ($existencias as $ex) { $mapaExis[$ex['nombre']] = $ex['exis_venta']; }
    
    foreach ($productos as $p): 
        $stockActual = $mapaExis[$p['nombre']] ?? 0;
    ?>
        <option data-id="<?= $p['id_producto'] ?>" value="<?= esc($p['nombre']) ?> (Stock actual: <?= $stockActual ?>)"></option>
    <?php endforeach; ?>
</datalist>
                                <input type="hidden" name="id_producto2" id="id_producto_hidden">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">F. Registro</label>
                                <input type="date" id="fecha_entrada" name="fecha_entrada" 
                                    class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">F. Caducidad</label>
                                <input type="date" id="fecha_caducidad" name="fecha_caducidad" 
                                    class="w-full bg-gray-100 border-none rounded-2xl px-4 py-3.5 text-gray-400 font-medium cursor-not-allowed" readonly required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Cantidad</label>
                                <input type="number" id="cantidad_entrada" name="cantidad" step="0.01" placeholder="0.00" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required min="0">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Precio Compra</label>
                                <input type="number" id="precio_entrada" name="precio_compra" step="0.01" placeholder="$ 0.00" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required min="0">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">U. Compra</label>
                                <select name="unidad_compra" id="unidad_compra" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 appearance-none focus:ring-2 focus:ring-[#a1143a]/20" required>
                                    <option value="caja">Caja</option>
                                    <option value="mazo">Mazo</option>
                                    <option value="arpilla">Arpilla</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">U. Venta</label>
                                <select name="unidad_venta" id="unidad_venta" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 appearance-none focus:ring-2 focus:ring-[#a1143a]/20" required>
                                    <option value="kilos">Kilos</option>
                                    <option value="domos">Domos</option>
                                    <option value="ramos">Ramos</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Equivalente</label>
                                <input type="number" id="equivalente" name="equivalente" step="0.01" placeholder="Ej: 1 caja=10kg..." class="w-full bg-gray-100 border-none rounded-2xl px-4 py-3.5 text-gray-500 font-bold" required min="0">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Conversión</label>
                                <input type="number" id="conversion" name="conversion" step="0.01" readonly class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-3.5 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20" required min="0">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-[#a1143a] text-white py-4 rounded-2xl font-bold text-sm uppercase tracking-widest hover:bg-[#820f2e] transform active:scale-[0.98] transition-all shadow-lg shadow-[#a1143a]/20 mt-4">
                            Guardar Entrada
                        </button>
                    </form>
                </div>

                
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

        <div id="seccion-existencias" class="tab-content hidden">
            <div class="mb-6">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </span>
                    <input type="text" id="filtro-existencias" 
                        placeholder="Filtrar productos en existencia..." 
                        class="pl-10 w-full max-w-md bg-gray-50 border border-gray-200 rounded-xl py-2.5 text-sm focus:ring-2 focus:ring-[#a1143a]/10 outline-none"
                        autocomplete="off">
                </div>
                <span id="error-producto" class="text-xs text-red-500 font-semibold mt-1 hidden ml-1">
                    El producto ingresado no existe. Por favor, seleccione uno válido.
                </span>
            </div>
            
            <div class="bg-white p-8 rounded-[32px] border border-[#f1f1f1] shadow-sm">
                <h2 class="text-2xl font-bold text-gray-800 mb-8 tracking-tight">Control de Existencias</h2>
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 text-[10px] uppercase tracking-widest border-b">
                            <th class="pb-4 font-bold">Producto</th>
                            <th class="pb-4 font-bold text-center">Total</th>
                            <th class="pb-4 font-bold text-center">Bloqueado</th>
                            <th class="pb-4 font-bold text-center">Disponible</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existencias as $exis): ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 font-medium text-gray-900"><?= esc($exis['nombre']) ?></td>
                            <td class="py-4 text-center"><?= esc($exis['exis_total_general']) ?></td>
                            <td class="py-4 text-center text-red-500 font-medium"><?= esc($exis['exis_bloqueo']) ?></td>
                            <td class="py-4 text-center text-green-600 font-bold"><?= esc($exis['exis_venta']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

      
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
                    <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Registro de Mermas</h2>
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
                            <td class="py-4 text-center font-medium"><?= esc($merma['cantidad']) ?></td>
                            <td class="py-4 text-center text-gray-400 text-sm"><?= esc($merma['fecha']) ?></td>
                            <td class="py-4 text-right text-sm italic text-gray-400"><?= esc($merma['notas']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>


    <div id="myModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden flex items-center justify-center">
        <div class="bg-white w-full max-w-md p-8 rounded-[32px] shadow-2xl relative">
            <span class="close absolute top-6 right-6 text-gray-300 hover:text-gray-600 cursor-pointer text-2xl">&times;</span>
            <h2 class="text-2xl font-bold text-gray-800 mb-8 tracking-tight">Registrar Merma</h2>
            <form action="<?= base_url('inventario/registrar-merma') ?>" method="POST" class="space-y-5">
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Buscar Producto en Existencia</label>
                    <div class="relative">
                        <input list="lista-mermas" id="busqueda_merma" 
                               placeholder="Escribe el producto..." 
                               class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-4 text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20 appearance-none"
                               required>
                        <datalist id="lista-mermas">
                            <?php
                         
                            $mapaExistencias = [];
                            foreach ($existencias as $ex) {
                                $mapaExistencias[$ex['nombre']] = $ex['exis_venta'];
                            }

                            $productosVistos = [];
                            foreach ($entradas as $e):
                                $idProducto = $e['id_producto2'];
                                if (isset($productosVistos[$idProducto])) continue;
                                $productosVistos[$idProducto] = true;
                                $disp = $mapaExistencias[$e['nombre']] ?? '—';
                            ?>
                                <option data-id="<?= $e['id_entrada'] ?>" value="<?= esc($e['nombre']) ?> (Disp: <?= esc($disp) ?>)"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="hidden" name="id_entrada" id="id_entrada_hidden">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Cantidad</label>
                    <input type="number" name="cantidad" min="0" required class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-4 focus:ring-2 focus:ring-[#a1143a]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Fecha</label>
                    <input type="date" id="fecha_merma" name="fecha" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-4 focus:ring-2 focus:ring-[#a1143a]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Notas</label>
                    <textarea name="notas" rows="3" class="w-full bg-[#f9fafb] border-none rounded-2xl px-4 py-4 focus:ring-2 focus:ring-[#a1143a]/20 resize-none"></textarea>
                </div>
                <button type="submit" class="w-full bg-[#a1143a] text-white py-4 rounded-2xl font-bold text-sm uppercase tracking-widest shadow-lg shadow-[#a1143a]/20">
                    Confirmar Merma
                </button>
            </form>
        </div>
    </div>

    <script>
    lucide.createIcons();

  
    function cambiarPestaña(idSeccion, boton) {
        document.querySelectorAll('.tab-content').forEach(sec => {
            sec.classList.add('hidden');
            sec.classList.remove('block');
        });
        const seccion = document.getElementById(idSeccion);
        if (seccion) {
            seccion.classList.remove('hidden');
            seccion.classList.add('block');
        }
        document.querySelectorAll('.btn-tab').forEach(btn => {
            btn.classList.remove('active', 'bg-white', 'shadow-sm', 'text-gray-900');
            btn.classList.add('text-gray-500');
        });
        boton.classList.add('active', 'bg-white', 'shadow-sm', 'text-gray-900');
        boton.classList.remove('text-gray-500');
    }




    const inputCantidad    = document.getElementById('cantidad_entrada');
    const inputPrecio      = document.getElementById('equivalente');
    const inputEquivalente = document.getElementById('conversion');

    function calcularEquivalente() {
        const cantidad    = parseFloat(inputCantidad.value) || 0;
        const equivalente = parseFloat(inputPrecio.value) || 0;
        inputEquivalente.value = (cantidad * equivalente).toFixed(2);
    }

    inputCantidad.addEventListener('input', calcularEquivalente);
    inputPrecio.addEventListener('input', calcularEquivalente);

    
    const inputFechaEntrada   = document.getElementById('fecha_entrada');
    const inputFechaCaducidad = document.getElementById('fecha_caducidad');

    function configurarLimitesCalendario() {
        const hoy = new Date();
        const maxFecha = hoy.toISOString().split('T')[0];
        const haceUnaSemana = new Date();
        haceUnaSemana.setDate(hoy.getDate() - 7);
        const minFecha = haceUnaSemana.toISOString().split('T')[0];
        inputFechaEntrada.max = maxFecha;
        inputFechaEntrada.min = minFecha;
    }
    configurarLimitesCalendario();

   
    inputFechaEntrada.addEventListener('change', function() {
        if (!this.value) return;
        const partes = this.value.split('-');
        const fecha  = new Date(parseInt(partes[0], 10), parseInt(partes[1], 10) - 1, parseInt(partes[2], 10));
        fecha.setDate(fecha.getDate() + 5);
        const anio = fecha.getFullYear();
        const mes  = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia  = String(fecha.getDate()).padStart(2, '0');
        inputFechaCaducidad.value = `${anio}-${mes}-${dia}`;
    });

    (function setFechaMermaHoy() {
        const input = document.getElementById('fecha_merma');
        if (!input) return;
        const hoy = new Date();
        input.value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
    })();

    const modal = document.getElementById("myModal");
    const btn   = document.getElementById("btnRegistrarMerma");
    const span  = document.querySelector(".close");

    btn.onclick   = () => modal.classList.remove('hidden');
    span.onclick  = () => modal.classList.add('hidden');
    window.onclick = (e) => { if (e.target === modal) modal.classList.add('hidden'); };

    const formularioEntrada = document.querySelector('form[action*="registrar-entrada"]');
    const inputBusqueda     = document.getElementById('busqueda_producto');
    const listaProductos    = document.getElementById('lista-productos');
    const hiddenInput       = document.getElementById('id_producto_hidden');

    inputBusqueda.addEventListener('input', function() {
        const val = this.value.trim();
        const opt = Array.from(listaProductos.options).find(o => o.value.toLowerCase() === val.toLowerCase());
        hiddenInput.value = opt ? opt.getAttribute('data-id') : '';
    });

    if (formularioEntrada) {
        formularioEntrada.addEventListener('submit', function(e) {
            const val = inputBusqueda.value.trim();
            const opt = Array.from(listaProductos.options).find(o => o.value.toLowerCase() === val.toLowerCase());
            if (!opt) {
                e.preventDefault();
                alert("Error: Producto no válido. Por favor, selecciona un producto existente de la lista.");
                hiddenInput.value = '';
                inputBusqueda.focus();
                return;
            }
            hiddenInput.value = opt.getAttribute('data-id');

            if (inputFechaEntrada.value) {
                const fechaIng = new Date(inputFechaEntrada.value + 'T00:00:00');
                const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
                const hace7 = new Date(); hace7.setDate(hoy.getDate() - 7); hace7.setHours(0, 0, 0, 0);
                if (fechaIng > hoy || fechaIng < hace7) {
                    e.preventDefault();
                    alert("Error: Fecha de registro inválida. No puede ser futura ni mayor a una semana en el pasado.");
                    inputFechaEntrada.focus();
                }
            }
        });
    }


    const formularioMerma    = document.querySelector('form[action*="registrarmerma"]');
    const inputBusquedaMerma = document.getElementById('busqueda_merma');
    const listaMermas        = document.getElementById('lista-mermas');
    const hiddenInputMerma   = document.getElementById('id_entrada_hidden');

    inputBusquedaMerma.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        const opt = Array.from(listaMermas.options).find(o => o.value.toLowerCase() === val);
        hiddenInputMerma.value = opt ? opt.getAttribute('data-id') : '';
    });

    if (formularioMerma) {
        formularioMerma.addEventListener('submit', function(e) {
            const val = inputBusquedaMerma.value.trim().toLowerCase();
            const opt = Array.from(listaMermas.options).find(o => o.value.toLowerCase() === val);
            if (!opt) {
                e.preventDefault();
                alert("Error: Selección inválida. Por favor, selecciona un producto exacto de la lista.");
                hiddenInputMerma.value = '';
                inputBusquedaMerma.focus();
            } else {
                hiddenInputMerma.value = opt.getAttribute('data-id');
            }
        });
    }

    document.getElementById('filtro-entradas').addEventListener('input', function() {
        const b = this.value.toLowerCase();
        const filas = document.querySelectorAll('#tbody-entradas .fila-entrada');
        let hayResultados = false;
        filas.forEach(fila => {
            const coincide = fila.querySelector('td:first-child').textContent.toLowerCase().includes(b);
            fila.style.display = coincide ? '' : 'none';
            if (coincide) hayResultados = true;
        });
        document.getElementById('sin-resultados-entradas').classList.toggle('hidden', hayResultados);
    });

    document.getElementById('filtro-existencias')?.addEventListener('input', function() {
        const b = this.value.toLowerCase();
        document.querySelectorAll('#seccion-existencias tbody tr').forEach(fila => {
            const celda = fila.querySelector('td:first-child');
            if (!celda) return;
            fila.style.display = celda.textContent.toLowerCase().includes(b) ? '' : 'none';
        });
    });

    document.getElementById('filtro-mermas')?.addEventListener('keyup', function() {
        const b = this.value.toLowerCase();
        document.querySelectorAll('#seccion-mermas tbody tr').forEach(fila => {
            fila.style.display = fila.querySelector('td:first-child').textContent.toLowerCase().includes(b) ? '' : 'none';
        });
    });
    </script>
</body>
</html>