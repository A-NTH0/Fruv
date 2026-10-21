<?php
use App\Models\ClienteModel;

$limpio = static function ($v): string {
    $v = trim((string) $v);

    return ($v === '' || strtolower($v) === 'null') ? '' : $v;
};

$nombreCompleto = trim($limpio($cliente['nombre']) . ' ' . $limpio($cliente['ap_paterno']) . ' ' . $limpio($cliente['ap_materno']));
$iniciales      = strtoupper(mb_substr($limpio($cliente['nombre']), 0, 1) . mb_substr($limpio($cliente['ap_paterno']), 0, 1));
$compras        = (int) ($cliente['compras'] ?? 0);
$esPreferencial = ClienteModel::esPreferencial($compras, $cliente['preferencial_manual'] ?? 0);
$faltan         = max(0, ClienteModel::UMBRAL_PREFERENCIAL - $compras);

$partesDir = [];
if ($direccion) {
    $calle = $limpio($direccion['calle']);
    $num   = (int) $direccion['numero'] > 0 ? (int) $direccion['numero'] : '';
    if ($calle !== '') { $partesDir[] = trim($calle . ' ' . $num); }
    foreach (['colonia', 'municipio', 'estado'] as $campo) {
        if ($limpio($direccion[$campo]) !== '') { $partesDir[] = $limpio($direccion[$campo]); }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruver - Mi perfil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/cliente.css') ?>">
</head>
<body>
    <?= view('plantilla/menu') ?>

    <main class="cli-main">
        <header class="cli-head">
            <div>
                <h1>Mi perfil</h1>
                <p>Tu información personal registrada en Fruver.</p>
            </div>
        </header>

        <section class="perfil-card">
            <div class="perfil-avatar" aria-hidden="true"><?= esc($iniciales ?: '?') ?></div>
            <div class="perfil-nombre">
                <h2><?= esc($nombreCompleto ?: 'Cliente') ?></h2>
                <?php if ($esPreferencial): ?>
                    <span class="badge badge-amber">⭐ Cliente preferencial</span>
                <?php else: ?>
                    <span class="badge badge-slate">Cliente</span>
                <?php endif; ?>
            </div>
        </section>

        <div class="perfil-grid">
            <section class="info-card">
                <h3>Datos personales</h3>
                <dl>
                    <div><dt>Nombre</dt><dd><?= esc($limpio($cliente['nombre']) ?: '—') ?></dd></div>
                    <div><dt>Apellido paterno</dt><dd><?= esc($limpio($cliente['ap_paterno']) ?: '—') ?></dd></div>
                    <div><dt>Apellido materno</dt><dd><?= esc($limpio($cliente['ap_materno']) ?: '—') ?></dd></div>
                    <div><dt>Teléfono</dt><dd><?= esc($limpio($cliente['telefono']) ?: '—') ?></dd></div>
                    <div><dt>Correo electrónico</dt><dd><?= esc($limpio($cliente['email']) ?: '—') ?></dd></div>
                </dl>
            </section>

            <section class="info-card">
                <h3>Dirección</h3>
                <p class="info-dir"><?= $partesDir ? esc(implode(', ', $partesDir)) : 'Aún no registras una dirección.' ?></p>
                <p class="muted" style="margin-top:.75rem">Si necesitas actualizar tus datos, solicítalo a la tienda.</p>
            </section>

            <section class="info-card">
                <h3>Mi actividad</h3>
                <div class="stat-pair">
                    <div><span class="stat-num"><?= (int) $totalPedidos ?></span><span class="muted">Pedidos realizados</span></div>
                    <div><span class="stat-num"><?= $compras ?></span><span class="muted">Pedidos de contado pagados</span></div>
                </div>
                <p class="muted" style="margin-top:.75rem">
                    <?= $esPreferencial
                        ? '¡Ya eres cliente preferencial!'
                        : 'Te faltan ' . $faltan . ' pedido(s) de contado pagados al 100 % para ser cliente preferencial. Los pedidos a crédito no suman.' ?>
                </p>
                <a href="<?= base_url('mis-pedidos') ?>" class="btn-ghost" style="margin-top:1rem;display:inline-block">Ver mis pedidos</a>
            </section>
        </div>
    </main>

    <script>const BASE_URL = '<?= rtrim(base_url(), '/') ?>/';</script>
</body>
</html>