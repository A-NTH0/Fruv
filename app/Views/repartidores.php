<?php if(empty($isAjax)): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - <?= $title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/repartidores.css') ?>">
</head>
<body class="bg-gray-50">

    <div class="flex min-h-screen">
        <?= view('plantilla/menu') ?>

        <main class="main-content flex-1">
            <div class="max-w-7xl mx-auto p-8">
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 tracking-tight"><?= $title ?></h1>
                        <p class="text-gray-500">Gestión de personal y disponibilidad de entrega</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <form action="<?= base_url('repartidores') ?>" method="get" id="formBuscar" class="relative" onsubmit="event.preventDefault();">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="buscar" id="inputBuscar" 
                                   value="<?= esc($busqueda ?? '') ?>" 
                                   placeholder="Buscar repartidor..." 
                                   oninput="buscarDinamico()"
                                   autocomplete="off"
                                   class="pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm w-64 shadow-sm">
                        </form>

                        <button onclick="abrirModalNuevo()" class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 shadow-lg shadow-rose-100 transition-all active:scale-95">
                            <i class="fas fa-plus text-sm"></i> Nuevo Repartidor
                        </button>
                    </div>
                </div>

                <?php $credentialNotice = session()->getFlashdata('credential'); ?>
                <?php $formError = session()->getFlashdata('error'); ?>
                <?php if ($formError): ?>
                    <div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= esc($formError) ?></div>
                <?php endif; ?>
                <?php if ($credentialNotice): ?>
                    <section role="status" class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                        <h2 class="font-bold"><?= esc($credentialNotice['tipo']) ?> creado y correo enviado a <?= esc($credentialNotice['email']) ?>.</h2>
                        <p class="mt-1">Contraseña generada (se muestra solo esta vez): <strong class="font-mono select-all"><?= esc($credentialNotice['password']) ?></strong></p>
                    </section>
                <?php endif; ?>

                <div id="contenedor-dinamico">
<?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-8">
                        <?php if(!empty($repartidores)): ?>
                            <?php foreach($repartidores as $r): ?>
                            <div class="repartidor-card bg-white p-6 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col transition-all hover:shadow-md">
                                <div class="flex justify-between items-start mb-6">
                                    <div class="icon-box w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-500">
                                        <i class="fas fa-shipping-fast text-2xl"></i>
                                    </div>
                                    <span class="text-[10px] font-black bg-green-100 text-green-700 px-3 py-1 rounded-full uppercase tracking-widest">Activo</span>
                                </div>

                                <div class="mb-6">
                                    <h3 class="text-xl font-bold text-gray-800 uppercase truncate"><?= $r['nombre'] ?></h3>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter">
                                        <?= $r['ap_paterno'] ?> <?= $r['ap_materno'] ?>
                                    </p>
                                </div>

                                <div class="space-y-4 mb-8 border-t border-gray-50 pt-5 text-gray-600">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-phone text-gray-300 w-5"></i>
                                        <span class="text-sm font-medium"><?= $r['telefono'] ?></span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-envelope text-gray-300 w-5"></i>
                                        <span class="text-sm font-medium break-all"><?= esc($r['email']) ?></span>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-map-marker-alt text-gray-300 w-5 mt-1"></i>
                                        <span class="text-xs italic leading-relaxed line-clamp-2"><?= $r['direccion'] ?></span>
                                    </div>
                                </div>

                                <div class="mt-auto flex gap-3">
                                    <button onclick='abrirModalEditar(<?= htmlspecialchars(json_encode($r)) ?>)' 
                                            class="flex-1 bg-white hover:bg-gray-50 text-gray-700 py-3 rounded-2xl text-sm font-bold border border-gray-200 transition-all text-center">
                                        Editar
                                    </button>
                                    <button type="button" 
                                            onclick="confirmarEliminacion(<?= $r['id_repartidor'] ?>, '<?= esc($r['nombre']) ?>')" 
                                            class="w-12 h-12 flex items-center justify-center rounded-2xl border border-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-full bg-white rounded-3xl border-2 border-dashed border-gray-100 py-20 text-center">
                                <i class="fas fa-user-friends text-4xl text-gray-100 mb-4"></i>
                                <p class="text-gray-400 italic">No se encontraron repartidores.</p>
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
                                   class="flex items-center gap-2 px-4 py-2 rounded-2xl border border-gray-200 bg-white text-gray-600 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-300 transition-all shadow-sm">
                                    <i class="fas fa-chevron-left text-xs"></i>
                                    <span class="text-sm font-bold">Anterior</span>
                                </a>
                            <?php else: ?>
                                <span class="flex items-center gap-2 px-4 py-2 rounded-2xl border border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed shadow-sm">
                                    <i class="fas fa-chevron-left text-xs"></i>
                                    <span class="text-sm font-bold">Anterior</span>
                                </span>
                            <?php endif; ?>

                            <!-- Indicador de página actual -->
                            <span class="px-5 py-2 text-sm text-gray-600 font-medium bg-white border border-gray-200 rounded-2xl shadow-sm select-none">
                                Página <span class="text-rose-600 font-bold"><?= $paginaActual ?></span> de <?= $totalPaginas ?>
                            </span>

                            <!-- Botón Siguiente -->
                            <?php if ($urlSiguiente): ?>
                                <a href="<?= $urlSiguiente ?>"
                                   class="flex items-center gap-2 px-4 py-2 rounded-2xl border border-gray-200 bg-white text-gray-600 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-300 transition-all shadow-sm">
                                    <span class="text-sm font-bold">Siguiente</span>
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </a>
                            <?php else: ?>
                                <span class="flex items-center gap-2 px-4 py-2 rounded-2xl border border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed shadow-sm">
                                    <span class="text-sm font-bold">Siguiente</span>
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </span>
                            <?php endif; ?>

                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

<?php if(empty($isAjax)): ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ── Modal Nuevo Repartidor ── -->
    <div id="modalNuevo" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] flex items-center justify-center hidden">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg p-8 shadow-2xl m-4 overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-black text-gray-800 tracking-tight">Nuevo Repartidor</h2>
                <button onclick="cerrarModalNuevo()" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="<?= base_url('repartidores/guardar') ?>" method="post" class="space-y-5">

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Nombre</label>
                    <input type="text" name="nombre" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Apellido Paterno</label>
                        <input type="text" name="ap_paterno" required
                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Apellido Materno</label>
                        <input type="text" name="ap_materno"
                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Teléfono</label>
                    <input type="text" name="telefono" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Correo electrónico</label>
                    <input type="email" name="email" maxlength="50" autocomplete="email" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                    <p class="mt-1 text-xs text-gray-500">Se generará una contraseña segura al guardar y se enviará a este correo.</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Dirección</label>
                    <input type="text" name="direccion"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Notas</label>
                    <textarea name="notas" rows="3"
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="cerrarModalNuevo()"
                            class="flex-1 border border-gray-200 text-gray-500 py-3 rounded-xl text-sm font-bold hover:bg-gray-50 transition-all uppercase">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 bg-rose-600 text-white py-3 rounded-xl text-sm font-bold hover:bg-rose-700 shadow-lg shadow-rose-100 transition-all uppercase">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal Editar Repartidor ── -->
    <div id="modalEditar" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] flex items-center justify-center hidden">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg p-8 shadow-2xl m-4 overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-black text-gray-800 tracking-tight">Editar Repartidor</h2>
                <button onclick="cerrarModalEditar()" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="<?= base_url('repartidores/actualizar') ?>" method="post" class="space-y-5">
                <input type="hidden" name="id_repartidor" id="edit_id">

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Nombre</label>
                    <input type="text" name="nombre" id="edit_nombre" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Apellido Paterno</label>
                        <input type="text" name="ap_paterno" id="edit_paterno" required
                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Apellido Materno</label>
                        <input type="text" name="ap_materno" id="edit_materno"
                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Teléfono</label>
                    <input type="text" name="telefono" id="edit_telefono" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Correo electrónico</label>
                    <input type="email" name="email" id="edit_email" maxlength="50" autocomplete="email" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Dirección</label>
                    <input type="text" name="direccion" id="edit_direccion"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Notas</label>
                    <textarea name="notas" id="edit_notas" rows="3"
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="cerrarModalEditar()"
                            class="flex-1 border border-gray-200 text-gray-500 py-3 rounded-xl text-sm font-bold hover:bg-gray-50 transition-all uppercase">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 bg-rose-600 text-white py-3 rounded-xl text-sm font-bold hover:bg-rose-700 shadow-lg shadow-rose-100 transition-all uppercase">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modalNuevo  = document.getElementById('modalNuevo');
        const modalEditar = document.getElementById('modalEditar');

        let timeout = null;
        function buscarDinamico() {
            clearTimeout(timeout);
            timeout = setTimeout(async function () {
                const query = document.getElementById('inputBuscar').value;
                const url   = "<?= base_url('repartidores') ?>?buscar=" + encodeURIComponent(query);
                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (response.ok) {
                        document.getElementById('contenedor-dinamico').innerHTML = await response.text();
                    }
                } catch (error) {
                    console.error("Error en búsqueda:", error);
                }
            }, 350);
        }

        function abrirModalNuevo() {
            modalNuevo.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function cerrarModalNuevo() {
            modalNuevo.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function abrirModalEditar(r) {
            document.getElementById('edit_id').value        = r.id_repartidor;
            document.getElementById('edit_nombre').value    = r.nombre;
            document.getElementById('edit_paterno').value   = r.ap_paterno;
            document.getElementById('edit_materno').value   = r.ap_materno;
            document.getElementById('edit_telefono').value  = r.telefono;
            document.getElementById('edit_email').value     = r.email;
            document.getElementById('edit_direccion').value = r.direccion;
            document.getElementById('edit_notas').value     = r.notas || '';
            modalEditar.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function cerrarModalEditar() {
            modalEditar.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        async function confirmarEliminacion(id, nombre) {
            try {
                const res  = await fetch(`<?= base_url('repartidores/verificarEliminar/') ?>` + id);
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
                console.error('Error al verificar repartidor:', e);
            }

            Swal.fire({
                title: '¿Eliminar repartidor?',
                text: `"${nombre}" será eliminado permanentemente.`,
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
                    window.location.href = "<?= base_url('repartidores/eliminar/') ?>" + id;
                }
            });
        }

        [modalNuevo, modalEditar].forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>
</body>
</html>
<?php endif; ?>
