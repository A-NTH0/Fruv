<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Portal de Repartidor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/repartidor-entregas.css') ?>">
</head>
<body class="bg-gray-50">
    <?= view('plantilla/menu') ?>

    <main id="contenido-principal" class="main-content">
        <div class="max-w-7xl mx-auto">

            <!-- Encabezado y estado del radar de pedidos (Funcionalidad 8) -->
            <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Panel de Entregas y Rutas</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Hola, <strong><?= esc(trim(($repartidor['nombre'] ?? '') . ' ' . ($repartidor['ap_paterno'] ?? ''))) ?></strong>. Gestiona tus pedidos asignados y avista nuevas órdenes en tiempo real.
                    </p>
                </div>

                <div class="flex items-center gap-3 self-start sm:self-auto">
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3.5 py-1.5 text-xs font-bold text-emerald-800">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>Avistamiento activo · <span id="sync-hora"><?= date('H:i:s') ?></span></span>
                    </div>
                    <button type="button" onclick="sincronizarPedidos(true)" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 hover:border-rose-300 hover:text-rose-600 shadow-sm">
                        <i data-lucide="refresh-cw" class="w-4 h-4" aria-hidden="true"></i>
                        <span>Actualizar</span>
                    </button>
                </div>
            </header>

            <!-- Tarjetas KPI estilo Dashboard -->
            <section class="rep-kpi-grid" aria-label="Resumen de actividad del repartidor">
                <div class="rep-kpi-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Entregas Activas</span>
                        <div class="rep-kpi-num" id="kpi-activos"><?= (int) $stats['activos'] ?></div>
                    </div>
                    <div class="rep-kpi-icon"><i data-lucide="truck" class="w-5 h-5"></i></div>
                </div>

                <div class="rep-kpi-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Nuevos Disponibles</span>
                        <div class="rep-kpi-num" id="kpi-disponibles"><?= (int) $stats['disponibles'] ?></div>
                    </div>
                    <div class="rep-kpi-icon"><i data-lucide="bell-ring" class="w-5 h-5"></i></div>
                </div>

                <div class="rep-kpi-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Completados</span>
                        <div class="rep-kpi-num" id="kpi-entregados"><?= (int) $stats['entregados'] ?></div>
                    </div>
                    <div class="rep-kpi-icon"><i data-lucide="check-circle-2" class="w-5 h-5"></i></div>
                </div>

                <div class="rep-kpi-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total a Cobrar en Ruta</span>
                        <div class="rep-kpi-num text-emerald-700" id="kpi-monto">$<?= number_format((float) $stats['monto_ruta'], 2) ?></div>
                    </div>
                    <div class="rep-kpi-icon rep-kpi-icon--emerald"><i data-lucide="dollar-sign" class="w-5 h-5"></i></div>
                </div>
            </section>

            <!-- Buscador rápido (la navegación por secciones está en el menú superior) -->
            <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
                <div class="relative w-full lg:w-80" id="buscador-wrapper">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <label for="inputFiltroRep" class="sr-only">Buscar por folio, cliente o dirección</label>
                    <input
                        type="search"
                        id="inputFiltroRep"
                        oninput="renderizarVistas()"
                        placeholder="Buscar #pedido, cliente o colonia..."
                        class="w-full rounded-xl border border-gray-300 bg-white pl-10 pr-4 py-2.5 text-sm outline-none focus:border-rose-600 focus:ring-2 focus:ring-rose-200"
                    >
                </div>
            </div>

            <!-- Contenedores dinámicos de cada sección -->
            <section id="sec-asignados" class="space-y-4" aria-live="polite"></section>
            <section id="sec-disponibles" class="space-y-4 hidden" aria-live="polite"></section>
            <section id="sec-historial" class="space-y-4 hidden" aria-live="polite"></section>

            <!-- Sección Mi Perfil del Repartidor -->
            <section id="sec-perfil" class="hidden">
                <div class="perfil-card">
                    <div class="perfil-avatar">
                        <?= esc(strtoupper(substr($repartidor['nombre'] ?? 'R', 0, 1) . substr($repartidor['ap_paterno'] ?? 'P', 0, 1))) ?>
                    </div>
                    <div class="perfil-nombre">
                        <h2><?= esc(trim(($repartidor['nombre'] ?? '') . ' ' . ($repartidor['ap_paterno'] ?? '') . ' ' . ($repartidor['ap_materno'] ?? ''))) ?></h2>
                        <span class="badge badge-rose">Repartidor Oficial Fruver</span>
                    </div>
                </div>

                <div class="perfil-grid">
                    <div class="info-card">
                        <h3>Datos de contacto</h3>
                        <dl>
                            <div><dt>Nombre</dt><dd><?= esc($repartidor['nombre'] ?? '-') ?></dd></div>
                            <div><dt>Apellidos</dt><dd><?= esc(trim(($repartidor['ap_paterno'] ?? '') . ' ' . ($repartidor['ap_materno'] ?? ''))) ?></dd></div>
                            <div><dt>Teléfono</dt><dd><?= esc($repartidor['telefono'] ?? '-') ?></dd></div>
                            <div><dt>Correo electrónico</dt><dd><?= esc($repartidor['email'] ?? '-') ?></dd></div>
                        </dl>
                    </div>

                    <div class="info-card">
                        <h3>Información operativa y notas</h3>
                        <dl>
                            <div><dt>Dirección base</dt><dd><?= (! empty($repartidor['direccion']) && $repartidor['direccion'] !== 'null') ? esc($repartidor['direccion']) : 'Sin registrar' ?></dd></div>
                            <div><dt>Observaciones / Vehículo</dt><dd><?= (! empty($repartidor['notas']) && $repartidor['notas'] !== 'null') ? esc($repartidor['notas']) : 'Sin observaciones' ?></dd></div>
                        </dl>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <script>
        const BASE_URL = '<?= rtrim(base_url(), '/') ?>/';
        const REPARTIDOR_INICIAL = <?= json_encode([
            'vistaInicial' => $vistaInicial,
            'firmaEstado'  => $firmaEstado,
            'asignados'    => $asignados,
            'disponibles'  => $disponibles,
            'entregados'   => $entregados,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    </script>
    <script src="<?= base_url('js/repartidor-entregas.js') ?>"></script>
</body>
</html>