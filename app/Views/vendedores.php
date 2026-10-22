<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - <?= esc($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/vendedores.css') ?>">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?= view('plantilla/menu') ?>

        <main class="main-content flex-1">
            <div class="max-w-7xl mx-auto p-8">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 tracking-tight"><?= esc($title) ?></h1>
                        <p class="text-gray-500">Administración de vendedores registrados</p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-stretch gap-3 w-full md:w-auto">
                        <form action="<?= base_url('vendedores') ?>" method="get" class="relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                            <input type="search" name="buscar" value="<?= esc($busqueda) ?>"
                                   placeholder="Buscar vendedor..." autocomplete="off"
                                   class="pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm w-full sm:w-64 shadow-sm">
                        </form>
                        <button type="button" onclick="abrirModalNuevo()"
                                class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-3 rounded-2xl font-bold flex items-center justify-center gap-2 shadow-lg shadow-rose-100 transition-all">
                            <i class="fas fa-plus text-sm" aria-hidden="true"></i> Nuevo vendedor
                        </button>
                    </div>
                </div>

                <?php if ($mensaje = session()->getFlashdata('error')): ?>
                    <div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= esc($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($mensaje = session()->getFlashdata('success')): ?>
                    <div role="status" class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-800"><?= esc($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($credencial = session()->getFlashdata('credential')): ?>
                    <section role="status" class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                        <h2 class="font-bold"><?= esc($credencial['tipo']) ?> creado y correo enviado a <?= esc($credencial['email']) ?>.</h2>
                        <p class="mt-1">Contraseña temporal enviada (se muestra solo esta vez): <strong class="font-mono select-all"><?= esc($credencial['password']) ?></strong></p>
                    </section>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php if ($vendedores): ?>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <article class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col">
                                <div class="flex justify-between items-start mb-5">
                                    <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-500">
                                        <i class="fas fa-user-tie text-xl" aria-hidden="true"></i>
                                    </div>
                                    <span class="text-xs font-bold bg-green-100 text-green-700 px-3 py-1 rounded-full">Vendedor</span>
                                </div>
                                <h2 class="text-xl font-bold text-gray-800 uppercase"><?= esc($vendedor['nombre']) ?></h2>
                                <p class="text-sm font-semibold text-gray-400 uppercase mb-5">
                                    <?= esc($vendedor['apellido_p']) ?> <?= esc($vendedor['apellido_m']) ?>
                                </p>
                                <dl class="space-y-3 mb-6 border-t border-gray-100 pt-5 text-gray-600">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-phone text-gray-300 w-5" aria-hidden="true"></i>
                                        <dd class="text-sm"><?= esc($vendedor['telefono']) ?></dd>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-envelope text-gray-300 w-5" aria-hidden="true"></i>
                                        <dd class="text-sm break-all"><?= esc($vendedor['email']) ?></dd>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-map-marker-alt text-gray-300 w-5 mt-1" aria-hidden="true"></i>
                                        <dd class="text-sm">Dirección: <?= esc($vendedor['direccion']) ?></dd>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-sticky-note text-gray-300 w-5 mt-1" aria-hidden="true"></i>
                                        <dd class="text-sm">Notas: <?= esc($vendedor['notas']) ?></dd>
                                    </div>
                                </dl>
                                <div class="mt-auto flex gap-3">
                                    <button type="button"
                                            data-vendedor="<?= esc(json_encode($vendedor, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), 'attr') ?>"
                                            onclick="abrirModalEditar(JSON.parse(this.dataset.vendedor))"
                                            class="flex-1 bg-white hover:bg-gray-50 text-gray-700 py-3 rounded-2xl text-sm font-bold border border-gray-200 transition-all">
                                        Editar
                                    </button>
                                    <form action="<?= base_url('vendedores/eliminar') ?>" method="post"
                                          onsubmit="return confirm('¿Eliminar a <?= esc($vendedor['nombre'], 'js') ?> de forma permanente?')">
                                        <input type="hidden" name="id" value="<?= esc($vendedor['id']) ?>">
                                        <button type="submit" aria-label="Eliminar vendedor"
                                                class="w-12 h-12 flex items-center justify-center rounded-2xl border border-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all">
                                            <i class="fas fa-trash-alt text-sm" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full bg-white rounded-3xl border-2 border-dashed border-gray-100 py-20 text-center">
                            <i class="fas fa-user-tie text-4xl text-gray-200 mb-4" aria-hidden="true"></i>
                            <p class="text-gray-500">No se encontraron vendedores.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($pager->getPageCount('vendedores') > 1): ?>
                    <nav class="mt-8 flex justify-center" aria-label="Paginación de vendedores">
                        <?= $pager->links('vendedores') ?>
                    </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="modalVendedor" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] flex items-center justify-center hidden">
        <section class="bg-white rounded-[2rem] w-full max-w-lg p-8 shadow-2xl m-4 overflow-y-auto max-h-[90vh]" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
            <div class="flex justify-between items-center mb-6">
                <h2 id="tituloModal" class="text-2xl font-black text-gray-800">Nuevo vendedor</h2>
                <button type="button" onclick="cerrarModal()" aria-label="Cerrar"
                        class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:text-rose-500">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form id="formVendedor" action="<?= base_url('vendedores/guardar') ?>" method="post" class="space-y-4">
                <input type="hidden" name="id" id="vendedor_id">
                <div>
                    <label for="vendedor_nombre" class="block text-xs font-bold text-gray-500 uppercase mb-2">Nombre</label>
                    <input id="vendedor_nombre" name="nombre" maxlength="20" required
                           value="<?= esc(old('nombre', '')) ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="vendedor_apellido_p" class="block text-xs font-bold text-gray-500 uppercase mb-2">Apellido paterno</label>
                        <input id="vendedor_apellido_p" name="apellido_p" maxlength="20" required
                               value="<?= esc(old('apellido_p', '')) ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                    <div>
                        <label for="vendedor_apellido_m" class="block text-xs font-bold text-gray-500 uppercase mb-2">Apellido materno</label>
                        <input id="vendedor_apellido_m" name="apellido_m" maxlength="20" required
                               value="<?= esc(old('apellido_m', '')) ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                </div>
                <div>
                    <label for="vendedor_telefono" class="block text-xs font-bold text-gray-500 uppercase mb-2">Teléfono</label>
                    <input id="vendedor_telefono" name="telefono" type="tel" maxlength="20" required
                           value="<?= esc(old('telefono', '')) ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div>
                    <label for="vendedor_email" class="block text-xs font-bold text-gray-500 uppercase mb-2">Correo electrónico</label>
                    <input id="vendedor_email" name="email" type="email" maxlength="50" required
                           value="<?= esc(old('email', '')) ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div>
                    <label for="vendedor_direccion" class="block text-xs font-bold text-gray-500 uppercase mb-2">Dirección</label>
                    <input id="vendedor_direccion" name="direccion" type="text" maxlength="50"
                           value="<?= esc(old('direccion', '')) ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div>
                    <label for="vendedor_notas" class="block text-xs font-bold text-gray-500 uppercase mb-2">Notas</label>
                    <textarea id="vendedor_notas" name="notas" maxlength="100" rows="3"
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500 resize-y"><?= esc(old('notas', '')) ?></textarea>
                </div>
                <div class="flex gap-3 pt-3">
                    <button type="button" onclick="cerrarModal()" class="flex-1 border border-gray-200 text-gray-500 py-3 rounded-xl font-bold">Cancelar</button>
                    <button id="guardarVendedor" type="submit" class="flex-1 bg-rose-600 text-white py-3 rounded-xl font-bold hover:bg-rose-700">Guardar</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const modalVendedor = document.getElementById('modalVendedor');
        const formVendedor = document.getElementById('formVendedor');

        function abrirModalNuevo() {
            formVendedor.reset();
            formVendedor.action = '<?= base_url('vendedores/guardar') ?>';
            document.getElementById('tituloModal').textContent = 'Nuevo vendedor';
            document.getElementById('guardarVendedor').textContent = 'Guardar';
            document.getElementById('vendedor_id').value = '';
            document.getElementById('vendedor_direccion').value = '';
            document.getElementById('vendedor_notas').value = '';
            modalVendedor.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function abrirModalEditar(vendedor) {
            formVendedor.action = '<?= base_url('vendedores/actualizar') ?>';
            document.getElementById('tituloModal').textContent = 'Editar vendedor';
            document.getElementById('guardarVendedor').textContent = 'Actualizar';
            document.getElementById('vendedor_id').value = vendedor.id;
            document.getElementById('vendedor_nombre').value = vendedor.nombre;
            document.getElementById('vendedor_apellido_p').value = vendedor.apellido_p;
            document.getElementById('vendedor_apellido_m').value = vendedor.apellido_m;
            document.getElementById('vendedor_telefono').value = vendedor.telefono;
            document.getElementById('vendedor_email').value = vendedor.email;
            document.getElementById('vendedor_direccion').value = vendedor.direccion;
            document.getElementById('vendedor_notas').value = vendedor.notas;
            modalVendedor.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal() {
            modalVendedor.classList.add('hidden');
            document.body.style.overflow = '';
        }

        modalVendedor.addEventListener('click', event => {
            if (event.target === modalVendedor) cerrarModal();
        });

        <?php if (session()->getFlashdata('modalVendedor') === 'nuevo'): ?>
            abrirModalNuevo();
        <?php elseif (session()->getFlashdata('modalVendedor') === 'editar'): ?>
            formVendedor.action = '<?= base_url('vendedores/actualizar') ?>';
            document.getElementById('tituloModal').textContent = 'Editar vendedor';
            document.getElementById('guardarVendedor').textContent = 'Actualizar';
            document.getElementById('vendedor_id').value = '<?= esc(old('id', '')) ?>';
            modalVendedor.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        <?php endif; ?>
    </script>
</body>
</html>
