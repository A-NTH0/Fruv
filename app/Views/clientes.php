<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/clientes.css') ?>">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?= view('plantilla/menu') ?>

        <main class="main-content flex-1 p-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Catálogo de Clientes</h1>
                    <p class="text-gray-500">Gestión de clientes y contacto</p>
                </div>
                
                <div class="flex items-center gap-4">
                    <form action="<?= base_url('clientes') ?>" method="get" id="formBuscar" class="relative" onsubmit="event.preventDefault();">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="buscar" id="inputBuscar" 
                               value="<?= esc($busqueda ?? '') ?>" 
                               placeholder="Buscar cliente..." 
                               oninput="buscarDinamico()"
                               autocomplete="off"
                               class="pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none text-sm w-64 shadow-sm">
                    </form>

                    <button onclick="abrirModalNuevo()" class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 shadow-lg transition-transform active:scale-95">
                        <i data-lucide="plus" class="w-5 h-5"></i> Nuevo Cliente
                    </button>
                </div>
            </div>

            <div id="contenedor-clientes" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (!empty($clientes)): ?>
                    <?php foreach ($clientes as $cliente): ?>
                    <div class="cliente-card p-6 bg-white rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-start gap-4">
                            <div class="icon-container bg-pink-50 text-pink-600 p-3 rounded-lg">
                                <i data-lucide="user" class="w-6 h-6"></i>
                            </div>
                            <div class="flex-1">
                               <h3 class="font-bold text-gray-800 text-lg uppercase tracking-tight">
                                 <?= $cliente['nombre'] ?> <?= $cliente['ap_paterno'] ?> <?= $cliente['ap_materno'] ?>
                               </h3>
                                
                                <div class="mt-4 space-y-2 text-sm text-gray-600">
                                    <p class="flex items-center gap-2">
                                        <i data-lucide="phone" class="w-4 h-4 text-gray-400"></i> 
                                        <?= $cliente['telefono'] ?>
                                    </p>
                                    <p class="flex items-start gap-2">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-0.5"></i> 
                                        <span>
                                            <?= (!empty($cliente['calle'])) ? "{$cliente['calle']} #{$cliente['numero']}, {$cliente['colonia']}" : 'Sin dirección' ?>
                                            <br>
                                            <span class="text-xs font-bold text-pink-500">
                                                <?= $cliente['municipio'] ?? '' ?><?= (!empty($cliente['municipio']) && !empty($cliente['estado'])) ? ', ' : '' ?><?= $cliente['estado'] ?? '' ?>
                                            </span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex gap-2">
                            <button onclick='editarCliente(<?= json_encode($cliente) ?>)' class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 rounded-lg flex items-center justify-center gap-2 transition-colors">
                                <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
                            </button>
                            <button onclick="confirmarEliminar(<?= $cliente['id_cliente'] ?>)" class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded-lg transition-colors">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-12 text-center text-gray-400 italic">
                        No se encontraron clientes.
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Paginación con flechas ── -->
            <?php if (isset($pager) && $pager !== null): ?>
                <?php
                    $paginaActual = $pager->getCurrentPage('group1');
                    $totalPaginas = $pager->getPageCount('group1');
                    $urlAnterior  = $pager->getPreviousPageURI('group1');
                    $urlSiguiente = $pager->getNextPageURI('group1');
                ?>
                <?php if ($totalPaginas > 1): ?>
                <div class="mt-10 flex justify-center items-center gap-3">

                    <!-- Botón Anterior -->
                    <?php if ($urlAnterior): ?>
                        <a href="<?= $urlAnterior ?>"
                           class="flex items-center gap-1 px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-pink-50 hover:text-pink-600 hover:border-pink-300 transition-colors shadow-sm">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            <span class="text-sm font-medium">Anterior</span>
                        </a>
                    <?php else: ?>
                        <span class="flex items-center gap-1 px-4 py-2 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed shadow-sm">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            <span class="text-sm font-medium">Anterior</span>
                        </span>
                    <?php endif; ?>

                    <!-- Indicador de página actual -->
                    <span class="px-5 py-2 text-sm text-gray-600 font-medium bg-white border border-gray-200 rounded-lg shadow-sm select-none">
                        Página <span class="text-pink-600 font-bold"><?= $paginaActual ?></span> de <?= $totalPaginas ?>
                    </span>

                    <!-- Botón Siguiente -->
                    <?php if ($urlSiguiente): ?>
                        <a href="<?= $urlSiguiente ?>"
                           class="flex items-center gap-1 px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-pink-50 hover:text-pink-600 hover:border-pink-300 transition-colors shadow-sm">
                            <span class="text-sm font-medium">Siguiente</span>
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php else: ?>
                        <span class="flex items-center gap-1 px-4 py-2 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed shadow-sm">
                            <span class="text-sm font-medium">Siguiente</span>
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </span>
                    <?php endif; ?>

                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ── Modal Nuevo / Editar Cliente ── -->
            <div id="modalCliente" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
                    <div class="p-6 flex justify-between items-center border-b border-gray-100">
                        <h2 id="modalTitle" class="text-2xl font-extrabold text-gray-800">Cliente</h2>
                        <button onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>

                    <form id="formCliente" action="" method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="id_cliente" id="input_id">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre *</label>
                                <input type="text" name="nombre" id="input_nombre" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ap. Paterno</label>
                                    <input type="text" name="ap_paterno" id="input_ap_paterno" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ap. Materno</label>
                                    <input type="text" name="ap_materno" id="input_ap_materno" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Teléfono *</label>
                                <input type="text" name="telefono" id="input_telefono" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Calle</label>
                                <input type="text" name="calle" id="input_calle" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Número</label>
                                    <input type="text" name="numero" id="input_numero" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Colonia</label>
                                    <input type="text" name="colonia" id="input_colonia" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Municipio</label>
                                    <input type="text" name="municipio" id="input_municipio" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Estado</label>
                                    <input type="text" name="estado" id="input_estado" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="cerrarModal()" class="flex-1 px-4 py-2 border border-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-50">Cancelar</button>
                            <button type="submit" id="btnSubmit" class="flex-1 px-4 py-2 bg-pink-600 text-white font-bold rounded-lg shadow-lg hover:bg-pink-700 transition-colors">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        const modal = document.getElementById('modalCliente');
        const form  = document.getElementById('formCliente');

        let timeout = null;
        function buscarDinamico() {
            clearTimeout(timeout);
            timeout = setTimeout(async function () {
                const query = document.getElementById('inputBuscar').value;
                const url   = `<?= base_url('clientes') ?>?buscar=${encodeURIComponent(query)}`;

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (response.ok) {
                        const html   = await response.text();
                        const parser = new DOMParser();
                        const doc    = parser.parseFromString(html, 'text/html');
                        document.getElementById('contenedor-clientes').innerHTML =
                            doc.getElementById('contenedor-clientes').innerHTML;
                        lucide.createIcons();
                    }
                } catch (error) {
                    console.error("Error al buscar clientes:", error);
                }
            }, 400);
        }

        function abrirModalNuevo() {
            document.getElementById('modalTitle').innerText  = 'Nuevo Cliente';
            document.getElementById('btnSubmit').innerText   = 'Crear';
            form.action = "<?= base_url('clientes/guardar') ?>";
            form.reset();
            document.getElementById('input_id').value = '';
            modal.classList.remove('hidden');
        }

        function editarCliente(cliente) {
            document.getElementById('modalTitle').innerText  = 'Editar Cliente';
            document.getElementById('btnSubmit').innerText   = 'Actualizar';
            form.action = "<?= base_url('clientes/actualizar') ?>";

            document.getElementById('input_id').value         = cliente.id_cliente;
            document.getElementById('input_nombre').value     = cliente.nombre;
            document.getElementById('input_ap_paterno').value = cliente.ap_paterno  || '';
            document.getElementById('input_ap_materno').value = cliente.ap_materno  || '';
            document.getElementById('input_telefono').value   = cliente.telefono;
            document.getElementById('input_calle').value      = cliente.calle       || '';
            document.getElementById('input_numero').value     = cliente.numero      || '';
            document.getElementById('input_colonia').value    = cliente.colonia     || '';
            document.getElementById('input_municipio').value  = cliente.municipio   || '';
            document.getElementById('input_estado').value     = cliente.estado      || '';

            modal.classList.remove('hidden');
        }

        function cerrarModal() { modal.classList.add('hidden'); }

        async function confirmarEliminar(id) {
            try {
                const res  = await fetch(`<?= base_url('clientes/verificarEliminar/') ?>` + id);
                const data = await res.json();

                if (!data.puede) {
                    Swal.fire({
                        title: 'No se puede eliminar',
                        html: '<p>' + data.mensaje + '</p>',
                        icon: 'error',
                        confirmButtonColor: '#e11d48',
                        confirmButtonText: 'Entendido',
                        customClass: {
                            popup:         'rounded-[2rem]',
                            confirmButton: 'rounded-xl px-6 py-3 font-bold',
                        }
                    });
                    return;
                }
            } catch (e) {
                console.error('Error al verificar cliente:', e);
            }

            Swal.fire({
                title: '¿Eliminar cliente?',
                text: "Esta acción es permanente y no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor:  '#9ca3af',
                confirmButtonText:  'Eliminar',
                cancelButtonText:   'Cancelar',
                reverseButtons: true,
                customClass: {
                    popup:         'rounded-[2rem]',
                    confirmButton: 'rounded-xl px-6 py-3 font-bold',
                    cancelButton:  'rounded-xl px-6 py-3 font-bold'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "<?= base_url('clientes/eliminar/') ?>" + id;
                }
            });
        }
    </script>
</body>
</html>
