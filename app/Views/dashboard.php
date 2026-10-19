<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
</head>
<body class="bg-[#F8FAFC] flex min-h-screen">
    <?= view('plantilla/menu') ?>

    <main class="flex-1 ml-64 p-8">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
            <p class="text-gray-500 text-sm">Resumen general del sistema de gestión</p>
        </header>

        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="card-kpi">
                <div>
                    <p class="label">Total Productos</p>
                    <h2 class="value"><?= $stats['total_productos'] ?? 0 ?></h2>
                </div>
                <div class="icon-box bg-[#FFF5F7] text-[#D31A38]">
                    <i data-lucide="package" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="card-kpi">
                <div>
                    <p class="label">Clientes Activos</p>
                    <h2 class="value"><?= $stats['total_clientes'] ?? 0 ?></h2>
                </div>
                <div class="icon-box bg-[#FFF5F7] text-[#D31A38]">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="card-kpi">
                <div>
                    <p class="label">Pedidos Pendientes</p>
                    <h2 class="value"><?= $stats['pedidos_pendientes'] ?? 0 ?></h2>
                </div>
                <div class="icon-box bg-[#FFF5F7] text-[#D31A38]">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="card-kpi">
                <div>
                    <p class="label">En Tránsito</p>
                    <h2 class="value"><?= $stats['en_transito'] ?? 0 ?></h2>
                </div>
                <div class="icon-box bg-[#FFF5F7] text-[#D31A38]">
                    <i data-lucide="truck" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-8">
            <!-- Tarjeta de Alertas Dinámica -->
            <div class="card-main flex flex-col max-h-[300px]">
                <div class="flex items-center gap-2 mb-6">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-red-500"></i>
                    <h3 class="font-bold text-gray-700">Alertas de Stock</h3>
                </div>
                
                <div class="flex-1 overflow-y-auto pr-2 space-y-3">
                    <?php if (empty($alertasStock)): ?>
                        <div class="flex items-center gap-4 p-4 bg-emerald-50 border border-emerald-100 rounded-xl">
                            <i data-lucide="check-circle" class="text-emerald-500"></i>
                            <p class="text-sm text-emerald-700 font-medium">¡Todo en orden! Ningún producto activo tiene stock bajo.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($alertasStock as $alerta): ?>
                        <div class="alert-item flex items-start gap-3 p-3 bg-rose-50 rounded-xl border border-rose-100">
                            <div class="alert-icon shrink-0 mt-0.5">
                                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-500"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 text-sm">Agostándose: <?= esc($alerta['nombre']) ?></p>
                                <p class="text-rose-600 text-xs font-semibold mt-0.5">
                                    Quedan <?= (int)$alerta['stock'] ?> unidades disponibles.
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-main">
                <div class="flex items-center gap-2 mb-8">
                    <i data-lucide="trending-up" class="w-4 h-4 text-red-500"></i>
                    <h3 class="font-bold text-gray-700">Resumen de Ventas</h3>
                </div>
                <div class="flex flex-col">
                    <div class="mb-8">
                        <span class="text-4xl font-extrabold text-[#D31A38]">$<?= number_format($stats['total_ventas'] ?? 0, 2) ?></span>
                        <span class="text-gray-500 text-xs ml-2">Total ventas confirmadas</span>
                    </div>
                    <div class="grid grid-cols-2 border-t border-gray-100 pt-4">
                        <div>
                            <p class="text-gray-400 text-[10px] uppercase font-bold">Pedidos Totales</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['total_pedidos'] ?? 0 ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-[10px] uppercase font-bold">Repartidores</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['repartidores_activos'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-main min-h-[250px]">
            <h3 class="font-bold text-gray-700 mb-6">Últimas Entradas de Inventario</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100 uppercase text-[10px] font-bold">
                            <th class="pb-3 text-center">ID Entrada</th>
                            <th class="pb-3">Producto</th>
                            <th class="pb-3 text-center">Categoría</th>
                            <th class="pb-3 text-center">Fecha</th>
                            <th class="pb-3 text-center">Cantidad</th>
                            <th class="pb-3 text-right">Precio Compra</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600">
                        <?php if(!empty($entradas)): ?>
                            <?php foreach($entradas as $entrada): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                <td class="py-3 text-center font-mono text-xs text-gray-400">#<?= $entrada['id_entrada'] ?></td>
                                <td class="py-3 font-medium text-gray-800"><?= esc($entrada['nombre']) ?></td>
                                <td class="py-3 text-center uppercase text-[10px] font-bold"><?= esc($entrada['categoria']) ?></td>
                                <td class="py-3 text-center">
                                    <?= !empty($entrada['fecha']) ? date('d/m/Y', strtotime($entrada['fecha'])) : date('d/m/Y') ?>
                                </td>
                                <td class="py-3 text-center font-semibold"><?= esc($entrada['cantidad']) ?></td>
                                <td class="py-3 text-right font-bold text-gray-700">$<?= number_format($entrada['precio_compra'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="py-10 text-center text-gray-400 italic">No hay datos en la base de datos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>