<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Catálogo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/productos.css') ?>">
</head>
<body class="bg-gray-50">
    <?= view('plantilla/menu') ?>

    <main class="main-content">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Catálogo de productos</h1>
                    <p class="mt-1 text-gray-500">Hola, <?= esc(session()->get('cliente_nombre')) ?>. Explora nuestros productos disponibles.</p>
                </div>
                <form action="<?= base_url('productos') ?>" method="get" class="flex gap-2">
                    <label for="catalogoBuscar" class="sr-only">Buscar productos</label>
                    <input
                        type="search"
                        name="buscar"
                        id="catalogoBuscar"
                        value="<?= esc($busqueda) ?>"
                        placeholder="Buscar productos..."
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-200 sm:w-72"
                    >
                    <button type="submit" class="rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white hover:bg-rose-700">Buscar</button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php if (! empty($productos)): ?>
                    <?php foreach ($productos as $producto): ?>
                        <article class="producto-card">
                            <div class="img-container">
                                <img
                                    src="<?= base_url('uploads/' . rawurlencode($producto['imagen'] ?: 'default.jpg')) ?>"
                                    alt="<?= esc($producto['nombre']) ?>"
                                    onerror="this.src='https://placehold.co/400x400?text=Fruta'"
                                >
                            </div>
                            <div class="mb-3">
                                <span class="inline-block rounded-full bg-rose-100 px-3 py-1 text-xs font-bold uppercase text-rose-700">
                                    <?= esc($producto['categoria'] ?? 'Producto') ?>
                                </span>
                                <h2 class="mt-3 text-xl font-extrabold uppercase text-gray-800"><?= esc($producto['nombre']) ?></h2>
                            </div>
                            <p class="mb-5 text-sm text-gray-500"><?= esc($producto['descripcion'] ?: 'Producto fresco disponible en Fruver.') ?></p>
                            <div class="mt-auto flex items-end justify-between border-t border-gray-100 pt-4">
                                <div>
                                    <span class="block text-xs font-bold uppercase text-gray-400">Precio</span>
                                    <span class="text-lg font-extrabold text-emerald-600">$<?= number_format((float) $producto['precio'], 2) ?></span>
                                    <span class="text-sm text-gray-500">/ <?= esc($producto['unidad_venta'] ?: 'unidad') ?></span>
                                </div>
                                <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700">En catálogo</span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
                        No encontramos productos disponibles<?= $busqueda !== '' ? ' con esa búsqueda' : '' ?>.
                    </p>
                <?php endif; ?>
            </div>

            <div class="mt-8"><?= $pager->links('group1', 'default_full') ?></div>
        </div>
    </main>
</body>
</html>
