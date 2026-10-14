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
    <link rel="stylesheet" href="<?= base_url('css/productos.css') ?>">
</head>
<body class="bg-gray-50">

    <?= view('plantilla/menu') ?>

    <main class="main-content">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex justify-between items-center mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 tracking-tight"><?= $title ?></h1>
                    <p class="text-gray-500">Gestión completa de productos</p>
                </div>
                <div class="flex items-center gap-4">
                    <form action="<?= base_url('productos') ?>" method="get" id="formBuscar" class="relative" onsubmit="event.preventDefault();">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="buscar" id="inputBuscar" 
                               value="<?= esc($busqueda ?? '') ?>" 
                               placeholder="Buscar productos..." 
                               oninput="buscarDinamico()"
                               autocomplete="off"
                               class="pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm w-64 shadow-sm">
                    </form>

                    <button onclick="abrirModalNuevo()" class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 shadow-lg shadow-rose-100 transition-all active:scale-95">
                        <i class="fas fa-plus text-sm"></i> Nuevo Producto
                    </button>
                </div>
            </div>

            <div id="contenedor-dinamico">
<?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php if(!empty($productos)): ?>
                        <?php foreach($productos as $p): ?>
                        <div class="producto-card">
                            <div class="img-container">
                                <img src="<?= base_url('uploads/'.$p['imagen']) ?>" 
                                     alt="<?= $p['nombre'] ?>"
                                     onerror="this.src='https://placehold.co/400x400?text=Fruta'">
                            </div>

                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500">
                                    <i class="fas fa-leaf text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-extrabold text-gray-800 leading-tight uppercase"><?= $p['nombre'] ?></h3>
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-widest 
                                        <?= ($p['categoria'] == 'frutas') ? 'bg-rose-100 text-rose-600' : (($p['categoria'] == 'verduras') ? 'bg-emerald-100 text-emerald-600' : 'bg-green-100 text-green-700') ?>">
                                        <?= $p['categoria'] ?>
                                    </span>
                                </div>
                            </div>

                            <p class="text-gray-400 text-xs mb-6 line-clamp-2 italic"><?= $p['descripcion'] ?></p>

                            <div class="mt-auto border-t border-gray-50 pt-4">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Unidad de venta:</span>
                                    <span class="font-black text-rose-600 italic bg-rose-50 px-3 py-1 rounded-lg text-sm">
                                        <?= !empty($p['unidad_venta']) ? $p['unidad_venta'] : 'Sin definir' ?>
                                    </span>
                                </div>
                                
                                <div class="flex gap-2">
                                    <button onclick='abrirModalEditar(<?= json_encode($p) ?>)' 
                                            class="flex-1 bg-gray-50 hover:bg-gray-100 text-gray-600 py-3 rounded-2xl font-bold text-xs transition-all border border-gray-100 uppercase">
                                        Editar
                                    </button>
                                    <button onclick="confirmarEliminar(<?= $p['id_producto'] ?>)" 
                                            class="w-12 h-12 flex items-center justify-center rounded-2xl bg-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full py-20 text-center bg-white rounded-[3rem] border-2 border-dashed border-gray-100">
                            <p class="text-gray-300 italic font-bold uppercase tracking-widest">Inventario Vacío</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (isset($pager) && $pager !== null): ?>
                    <?php
                        $paginaActual = $pager->getCurrentPage('group1');
                        $totalPaginas = $pager->getPageCount('group1');
                        $urlAnterior  = $pager->getPreviousPageURI('group1');
                        $urlSiguiente = $pager->getNextPageURI('group1');
                    ?>
                    <?php if ($totalPaginas > 1): ?>
                    <div class="mt-10 flex justify-center items-center gap-3">

       
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

                        <span class="px-5 py-2 text-sm text-gray-600 font-medium bg-white border border-gray-200 rounded-2xl shadow-sm select-none">
                            Página <span class="text-rose-600 font-bold"><?= $paginaActual ?></span> de <?= $totalPaginas ?>
                        </span>

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

    <div id="modalProducto" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg p-8 shadow-2xl m-4 overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitulo" class="text-2xl font-black text-gray-800 tracking-tight">Nuevo Producto</h2>
                <button onclick="cerrarModal()" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="formProducto" action="<?= base_url('productos/guardar') ?>" method="post" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="id_producto" id="edit_id">

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Nombre del Producto</label>
                    <input type="text" name="nombre" id="edit_nombre" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Categoría</label>
                        <select name="categoria" id="edit_categoria" required
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium bg-white">
                            <option value="frutas">Frutas</option>
                            <option value="verduras">Verduras</option>
                            <option value="hiervas">Hierbas</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Unidad de Venta</label>
                        <select name="unidad_venta" id="edit_unidad" required
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium bg-white">
                            <option value="kilos">Kilos</option>
                            <option value="domos">Domos</option>
                            <option value="ramos">Ramos</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Descripción</label>
                    <textarea name="descripcion" id="edit_descripcion" rows="3"
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-medium resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Imagen del Producto</label>
                    <div class="relative w-full border border-gray-200 rounded-xl p-4 flex items-center justify-between bg-gray-50">
                        <span class="text-xs text-gray-400 truncate pr-4" id="fileName">Selecciona un archivo...</span>
                        <input type="file" name="imagen_file" id="fileInput" onchange="updateFileName()" class="hidden" accept="image/*">
                        <button type="button" onclick="document.getElementById('fileInput').click()"
                                class="bg-white border border-gray-200 px-4 py-2 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 transition-all">
                            Examinar
                        </button>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="cerrarModal()"
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

    <script>
        function updateFileName() {
            const input = document.getElementById('fileInput');
            const label = document.getElementById('fileName');
            label.textContent = input.files.length > 0 ? input.files[0].name : "Selecciona un archivo...";
        }

        let timeout = null;

        function buscarDinamico() {
            clearTimeout(timeout);
            timeout = setTimeout(async function () {
                const query = document.getElementById('inputBuscar').value;
                const url   = "<?= base_url('productos') ?>?buscar=" + encodeURIComponent(query);
                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (response.ok) {
                        document.getElementById('contenedor-dinamico').innerHTML = await response.text();
                        sincronizarPaginacion();
                    }
                } catch (error) {
                    console.error("Error en búsqueda dinámica:", error);
                }
            }, 350);
        }

        function sincronizarPaginacion() {
            const query = document.getElementById('inputBuscar').value;
            if (!query) return;
            const links = document.querySelectorAll('#contenedor-dinamico a[href]');
            links.forEach(link => {
                const url = new URL(link.href);
                url.searchParams.set('buscar', query);
                link.href = url.toString();
            });
        }

        function abrirModalNuevo() {
            document.getElementById('modalTitulo').textContent = "Nuevo Producto";
            document.getElementById('formProducto').action     = "<?= base_url('productos/guardar') ?>";
            document.getElementById('formProducto').reset();
            document.getElementById('edit_id').value           = "";
            document.getElementById('fileName').textContent    = "Selecciona un archivo...";
            document.getElementById('modalProducto').classList.remove('hidden');
        }

        function abrirModalEditar(producto) {
            document.getElementById('modalTitulo').textContent     = "Editar Producto";
            document.getElementById('formProducto').action         = "<?= base_url('productos/actualizar') ?>";
            document.getElementById('edit_id').value               = producto.id_producto;
            document.getElementById('edit_nombre').value           = producto.nombre;
            document.getElementById('edit_categoria').value        = producto.categoria;
            document.getElementById('edit_unidad').value           = producto.unidad_venta;
            document.getElementById('edit_descripcion').value      = producto.descripcion;
            document.getElementById('fileName').textContent        = producto.imagen || "Selecciona un archivo...";
            document.getElementById('modalProducto').classList.remove('hidden');
        }

        function cerrarModal() {
            document.getElementById('modalProducto').classList.add('hidden');
        }

        async function confirmarEliminar(id) {
            try {
                const res  = await fetch(`<?= base_url('productos/verificarEliminar/') ?>` + id);
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
                console.error('Error al verificar producto:', e);
            }

            Swal.fire({
                title: '¿Eliminar producto?',
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
                    window.location.href = "<?= base_url('productos/eliminar/') ?>" + id;
                }
            });
        }

        document.addEventListener('DOMContentLoaded', sincronizarPaginacion);
    </script>
</body>
</html>
<?php endif; ?>
