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
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:z-[2000] focus:bg-white focus:text-gray-900 focus:p-3 focus:rounded">Saltar al contenido</a>
    <div class="flex min-h-screen">
        <?= view('plantilla/menu') ?>

        <main id="contenido" class="main-content flex-1 p-8">
            <?php $credentialNotice = session()->getFlashdata('credential'); ?>
            <?php $formError = session()->getFlashdata('error'); ?>
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Catálogo de Clientes</h1>
                    <p class="text-gray-600">Gestión de clientes y contacto</p>
                </div>
                
                <div class="flex items-center gap-4">
                    <form action="<?= base_url('clientes') ?>" method="get" id="formBuscar" class="relative" onsubmit="event.preventDefault();">
                        <i data-lucide="search" aria-hidden="true" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                        <label for="inputBuscar" class="sr-only">Buscar cliente por nombre, apellido o teléfono</label>
                        <input type="text" name="buscar" id="inputBuscar" 
                               value="<?= esc($busqueda ?? '') ?>" 
                               placeholder="Buscar cliente..." 
                               oninput="buscarDinamico()"
                               autocomplete="off"
                               class="pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none text-sm w-64 shadow-sm">
                    </form>

                    <button onclick="abrirModalNuevo()" class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 shadow-lg transition-transform active:scale-95">
                        <i data-lucide="plus" class="w-5 h-5" aria-hidden="true"></i> Nuevo Cliente
                    </button>
                </div>
            </div>

            <?php if ($formError): ?>
                <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= esc($formError) ?></div>
            <?php endif; ?>
            <?php if ($credentialNotice): ?>
                <section role="status" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                    <h2 class="font-bold"><?= esc($credentialNotice['tipo']) ?> creado y correo enviado a <?= esc($credentialNotice['email']) ?>.</h2>
                    <p class="mt-1">Contraseña generada (se muestra solo esta vez): <strong class="font-mono select-all"><?= esc($credentialNotice['password']) ?></strong></p>
                </section>
            <?php endif; ?>

            <div id="contenedor-clientes" aria-live="polite" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (!empty($clientes)): ?>
                    <?php foreach ($clientes as $cliente): ?>
                    <?php
                        $compras       = (int) ($cliente['compras'] ?? 0);
                        $manual        = (int) ($cliente['preferencial_manual'] ?? 0);
                        $esPreferencial = \App\Models\ClienteModel::esPreferencial($compras, $manual);
                        $faltan        = max(0, \App\Models\ClienteModel::UMBRAL_PREFERENCIAL - $compras);
                        $datosEditar   = [
                            'id_cliente' => $cliente['id_cliente'], 'nombre' => $cliente['nombre'],
                            'ap_paterno' => $cliente['ap_paterno'], 'ap_materno' => $cliente['ap_materno'],
                            'telefono'   => $cliente['telefono'],   'email'      => $cliente['email'],
                            'calle'      => $cliente['calle'],
                            'numero'     => $cliente['numero'],     'colonia'    => $cliente['colonia'],
                            'municipio'  => $cliente['municipio'],  'estado'     => $cliente['estado'],
                        ];
                        $nombreCompleto = trim($cliente['nombre'] . ' ' . $cliente['ap_paterno'] . ' ' . $cliente['ap_materno']);
                    ?>
                    <article class="cliente-card p-6 bg-white rounded-xl shadow-sm border border-gray-100" aria-label="Cliente <?= esc($nombreCompleto, 'attr') ?>">
                        <div class="flex items-start gap-4">
                            <div class="icon-container bg-pink-50 text-pink-600 p-3 rounded-lg">
                                <i data-lucide="user" class="w-6 h-6" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1">
                               <h3 class="font-bold text-gray-800 text-lg uppercase tracking-tight">
                                 <?= esc($nombreCompleto) ?>
                               </h3>
                               <?php if ($esPreferencial): ?>
                                   <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 text-xs font-bold">
                                       <i data-lucide="star" class="w-3 h-3" aria-hidden="true"></i> Cliente preferencial
                                   </span>
                                   <p class="mt-1 text-xs text-gray-600"><?= $manual === 1 ? 'Asignado manualmente' : 'Automático · ' . $compras . ' pedido(s) de contado pagados' ?></p>
                               <?php else: ?>
                                   <p class="mt-1 text-xs text-gray-600"><?= $compras ?> pedido(s) de contado pagados · faltan <?= $faltan ?> para ser preferencial</p>
                               <?php endif; ?>
                                
                                <div class="mt-4 space-y-2 text-sm text-gray-600">
                                    <p class="flex items-center gap-2">
                                        <i data-lucide="phone" class="w-4 h-4 text-gray-500" aria-hidden="true"></i> 
                                        <span class="sr-only">Teléfono:</span> <?= esc($cliente['telefono']) ?>
                                    </p>
                                    <p class="flex items-center gap-2 break-all">
                                        <i data-lucide="mail" class="w-4 h-4 text-gray-500" aria-hidden="true"></i>
                                        <?= esc($cliente['email']) ?>
                                    </p>
                                    <p class="flex items-start gap-2">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-gray-500 mt-0.5" aria-hidden="true"></i> 
                                        <span>
                                            <?= (!empty($cliente['calle'])) ? esc("{$cliente['calle']} #{$cliente['numero']}, {$cliente['colonia']}") : 'Sin dirección' ?>
                                            <br>
                                            <span class="text-xs font-bold text-pink-700">
                                                <?= esc($cliente['municipio'] ?? '') ?><?= (!empty($cliente['municipio']) && !empty($cliente['estado'])) ? ', ' : '' ?><?= esc($cliente['estado'] ?? '') ?>
                                            </span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex gap-2">
                            <button type="button" data-cliente="<?= esc(json_encode($datosEditar), 'attr') ?>" onclick="editarCliente(JSON.parse(this.dataset.cliente))" aria-label="Editar cliente <?= esc($nombreCompleto, 'attr') ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded-lg flex items-center justify-center gap-2 transition-colors">
                                <i data-lucide="edit-3" class="w-4 h-4" aria-hidden="true"></i> Editar
                            </button>
                            <?php if (! $esPreferencial || $manual === 1): ?>
                            <button type="button" onclick="togglePreferencial(<?= (int) $cliente['id_cliente'] ?>, <?= $manual === 1 ? 0 : 1 ?>)" aria-label="<?= $manual === 1 ? 'Quitar' : 'Marcar como' ?> cliente preferencial: <?= esc($nombreCompleto, 'attr') ?>" title="<?= $manual === 1 ? 'Quitar marca de preferencial' : 'Marcar como cliente preferencial' ?>" class="bg-yellow-50 hover:bg-yellow-100 text-yellow-800 p-2 rounded-lg transition-colors">
                                <i data-lucide="star" class="w-5 h-5" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" onclick="confirmarBaja(<?= (int) $cliente['id_cliente'] ?>)" aria-label="Dar de baja a <?= esc($nombreCompleto, 'attr') ?>" title="Dar de baja (borra sus datos personales)" class="bg-amber-50 hover:bg-amber-100 text-amber-900 p-2 rounded-lg transition-colors">
                                <i data-lucide="user-x" class="w-5 h-5" aria-hidden="true"></i>
                            </button>
                            <button type="button" onclick="confirmarEliminar(<?= (int) $cliente['id_cliente'] ?>)" aria-label="Eliminar cliente <?= esc($nombreCompleto, 'attr') ?>" class="bg-red-50 hover:bg-red-100 text-red-700 p-2 rounded-lg transition-colors">
                                <i data-lucide="trash-2" class="w-5 h-5" aria-hidden="true"></i>
                            </button>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-12 text-center text-gray-600 italic">
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
            <div id="modalCliente" role="dialog" aria-modal="true" aria-labelledby="modalTitle" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center overflow-y-auto p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden">
                    <div class="p-6 flex justify-between items-center border-b border-gray-100 shrink-0">
                        <h2 id="modalTitle" class="text-2xl font-extrabold text-gray-800">Cliente</h2>
                        <button type="button" onclick="cerrarModal()" aria-label="Cerrar ventana" class="text-gray-600 hover:text-gray-900">
                            <i data-lucide="x" class="w-6 h-6" aria-hidden="true"></i>
                        </button>
                    </div>

                    <form id="formCliente" action="" method="POST" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 overflow-y-auto p-6 space-y-4">
                            <input type="hidden" name="id_cliente" id="input_id">
                            <div>
                                <label for="input_nombre" class="block text-xs font-bold text-gray-700 uppercase mb-1">Nombre *</label>
                                <input type="text" name="nombre" id="input_nombre" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="input_ap_paterno" class="block text-xs font-bold text-gray-700 uppercase mb-1">Ap. Paterno</label>
                                    <input type="text" name="ap_paterno" id="input_ap_paterno" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                </div>
                                <div>
                                    <label for="input_ap_materno" class="block text-xs font-bold text-gray-700 uppercase mb-1">Ap. Materno</label>
                                    <input type="text" name="ap_materno" id="input_ap_materno" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                </div>
                            </div>
                            <div>
                                <label for="input_telefono" class="block text-xs font-bold text-gray-700 uppercase mb-1">Teléfono *</label>
                                <input type="text" name="telefono" id="input_telefono" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                            </div>
                            <div>
                                <label for="input_email" class="block text-xs font-bold text-gray-700 uppercase mb-1">Correo electrónico *</label>
                                <input type="email" name="email" id="input_email" maxlength="50" autocomplete="email" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                <p class="mt-1 text-xs text-gray-500">Al crear el cliente se generará una contraseña segura y se enviará a este correo.</p>
                            </div>
                        <div class="border-t border-gray-100 pt-4 space-y-4">
                            <div>
                                <label for="input_calle" class="block text-xs font-bold text-gray-700 uppercase mb-1">Calle</label>
                                <input type="text" name="calle" id="input_calle" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="input_numero" class="block text-xs font-bold text-gray-700 uppercase mb-1">Número</label>
                                    <input type="text" name="numero" id="input_numero" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                </div>
                                <div>
                                    <label for="input_colonia" class="block text-xs font-bold text-gray-700 uppercase mb-1">Colonia</label>
                                    <input type="text" name="colonia" id="input_colonia" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="input_municipio" class="block text-xs font-bold text-gray-700 uppercase mb-1">Municipio</label>
                                    <input type="text" name="municipio" id="input_municipio" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                </div>
                                <div>
                                    <label for="input_estado" class="block text-xs font-bold text-gray-700 uppercase mb-1">Estado</label>
                                    <input type="text" name="estado" id="input_estado" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-600 focus:border-pink-600 outline-none">
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="flex gap-3 border-t border-gray-100 bg-white p-6 pt-4 shrink-0">
                            <button type="button" onclick="cerrarModal()" class="flex-1 px-4 py-2 border border-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-50">Cancelar</button>
                            <button type="submit" id="btnSubmit" class="flex-1 px-4 py-2 bg-pink-600 text-white font-bold rounded-lg shadow-lg hover:bg-pink-700 transition-colors">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <form id="formBaja" action="<?= base_url('clientes/baja') ?>" method="POST" class="hidden">
        <input type="hidden" name="id_cliente" id="baja_id">
    </form>

    <script>
        lucide.createIcons();
        const modal = document.getElementById('modalCliente');
        const form  = document.getElementById('formCliente');

        let timeout = null;
        let ultimoFoco = null;
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
            ultimoFoco = document.activeElement;
            document.getElementById('input_nombre').focus();
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
            document.getElementById('input_email').value      = cliente.email;
            document.getElementById('input_calle').value      = cliente.calle       || '';
            document.getElementById('input_numero').value     = cliente.numero      || '';
            document.getElementById('input_colonia').value    = cliente.colonia     || '';
            document.getElementById('input_municipio').value  = cliente.municipio   || '';
            document.getElementById('input_estado').value     = cliente.estado      || '';

            modal.classList.remove('hidden');
            ultimoFoco = document.activeElement;
            document.getElementById('input_nombre').focus();
        }

        function cerrarModal() {
            modal.classList.add('hidden');
            if (ultimoFoco) ultimoFoco.focus();
        }

        // Teclado (WCAG 2.1.1 / 2.1.2): Esc cierra; Tab no se escapa del modal
        document.addEventListener('keydown', function (e) {
            if (modal.classList.contains('hidden')) return;
            if (e.key === 'Escape') { cerrarModal(); return; }
            if (e.key !== 'Tab') return;
            const f = modal.querySelectorAll('button, input, select, textarea, a[href]');
            const primero = f[0], ultimo = f[f.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        });

        // Baja manual (RNF-04): anonimiza datos personales y conserva el historial de pedidos
        /** Ajuste manual del administrador: marca o quita a un cliente como preferencial. */
        function togglePreferencial(id, valor) {
            Swal.fire({
                title: valor ? '¿Marcar como cliente preferencial?' : '¿Quitar la marca de preferencial?',
                text: valor ? 'Se aplica sin importar su historial de pagos ni su número de pedidos.'
                            : 'Seguirá siendo preferencial solo si cumple 5 pedidos pagados de contado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ca8a04',
                cancelButtonColor: '#4b5563',
                confirmButtonText: valor ? 'Sí, marcar' : 'Sí, quitar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                try {
                    const res  = await fetch("<?= base_url('clientes/preferencial') ?>", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id_cliente: id, valor: valor })
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: data.message, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'No se pudo actualizar', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Error de conexión al servidor', 'error');
                }
            });
        }

        function confirmarBaja(id) {
            Swal.fire({
                title: '¿Dar de baja al cliente?',
                text: 'Se borrarán su nombre, teléfono y dirección. Sus pedidos se conservan sin datos personales. No se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b45309',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'Dar de baja',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('baja_id').value = id;
                    document.getElementById('formBaja').submit();
                }
            });
        }

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