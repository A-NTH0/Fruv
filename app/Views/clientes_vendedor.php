<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Catálogo de clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/clientes.css') ?>">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?= view('plantilla/menu') ?>

        <main class="main-content">
            <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Catálogo de Clientes</h1>
                    <p class="text-gray-500">Consulta un cliente para revisar sus compras anteriores.</p>
                </div>
                <form action="<?= base_url('clientes') ?>" method="get" class="relative w-full sm:w-72">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                    <label for="buscarCliente" class="sr-only">Buscar cliente</label>
                    <input id="buscarCliente" type="search" name="buscar" value="<?= esc($busqueda) ?>"
                           placeholder="Buscar cliente..." class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-rose-500">
                </form>
            </header>

            <?php if ($mensaje = session()->getFlashdata('error')): ?>
                <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= esc($mensaje) ?></div>
            <?php endif; ?>

            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php if ($clientes): ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <?php $nombre = trim($cliente['nombre'] . ' ' . $cliente['ap_paterno'] . ' ' . $cliente['ap_materno']); ?>
                        <article class="cliente-card">
                            <div class="flex items-start gap-4">
                                <div class="icon-container">
                                    <i data-lucide="user-round" class="w-6 h-6" aria-hidden="true"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-bold text-gray-800 text-lg uppercase"><?= esc($nombre) ?></h2>
                                    <p class="mt-1 text-sm text-gray-600"><?= esc($cliente['telefono']) ?></p>
                                    <p class="mt-1 text-sm text-gray-600 break-all"><?= esc($cliente['email']) ?></p>
                                    <p class="mt-3 text-sm text-gray-500">
                                        <?= ! empty($cliente['calle'])
                                            ? esc(trim($cliente['calle'] . ' #' . $cliente['numero'] . ', ' . $cliente['colonia']))
                                            : 'Sin dirección registrada' ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-2">
                                <a href="<?= base_url('clientes/pedido/nuevo/' . (int) $cliente['id_cliente']) ?>"
                                   class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-3 font-bold text-white hover:bg-rose-700">
                                    <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i>
                                    Crear pedido nuevo
                                </a>
                                <a href="<?= base_url('clientes/historial/' . (int) $cliente['id_cliente']) ?>"
                                   class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 font-bold text-rose-700 hover:bg-rose-50">
                                    <i data-lucide="history" class="w-4 h-4" aria-hidden="true"></i>
                                    Ver pedidos del cliente
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full rounded-2xl border-2 border-dashed border-gray-200 bg-white p-12 text-center text-gray-500">
                        No se encontraron clientes.
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($pager->getPageCount('group1') > 1): ?>
                <nav class="mt-8 flex justify-center items-center gap-3" aria-label="Paginación de clientes">
                    <?php if ($prev = $pager->getPreviousPageURI('group1')): ?>
                        <a href="<?= esc($prev) ?>" class="rounded-xl border bg-white px-4 py-2 text-sm font-semibold text-gray-700">Anterior</a>
                    <?php endif; ?>
                    <span class="text-sm text-gray-600">Página <?= $pager->getCurrentPage('group1') ?> de <?= $pager->getPageCount('group1') ?></span>
                    <?php if ($next = $pager->getNextPageURI('group1')): ?>
                        <a href="<?= esc($next) ?>" class="rounded-xl border bg-white px-4 py-2 text-sm font-semibold text-gray-700">Siguiente</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
