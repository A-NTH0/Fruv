<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación MFA - Fruver</title>
    <link rel="stylesheet" href="<?= base_url('css/login.css?v=' . filemtime(FCPATH . 'css/login.css')) ?>">
    <link rel="stylesheet" href="<?= base_url('css/accesibilidad.css') ?>">
</head>
<body>
<main class="glass-card">
    <section class="left-side">
        <div class="header-block">
            <h1><?= $mode === 'setup' ? 'Configura tu segundo factor' : 'Verificación en dos pasos' ?></h1>
            <p class="subtitle">
                <?= $mode === 'setup'
                    ? 'Agrega esta cuenta a una aplicación autenticadora para proteger tu acceso.'
                    : 'Ingresa el código de tu aplicación autenticadora o un código de recuperación.' ?>
            </p>
        </div>

        <?php if (! empty($error)): ?>
            <p class="form-message" role="alert"><?= esc($error) ?></p>
        <?php endif; ?>
        <?php if (! empty($notice)): ?>
            <p class="form-message" role="status"><?= esc($notice) ?></p>
        <?php endif; ?>

        <?php if ($mode === 'setup'): ?>
            <div class="mfa-instructions">
                <p>Cuenta: <strong><?= esc($email) ?></strong></p>
                <p>En tu aplicación autenticadora, agrega una cuenta manual con esta clave:</p>
                <p><code><?= esc($secret) ?></code></p>
                <details>
                    <summary>Mostrar URI de configuración</summary>
                    <code><?= esc($provisioningUri) ?></code>
                </details>
                <p>Ingresa el código de 6 dígitos que aparece en la aplicación para confirmar la configuración.</p>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('login/mfa') ?>" method="post">
            <?= csrf_field() ?>
            <div class="input-group">
                <label for="mfa-code">Código de autenticación o recuperación</label>
                <input id="mfa-code" name="code" type="text" inputmode="text" autocomplete="one-time-code"
                       pattern="[0-9A-Fa-f-]{6,12}" maxlength="12" required autofocus>
            </div>
            <button type="submit" class="login-btn">
                <?= $mode === 'setup' ? 'Activar MFA' : 'Verificar e iniciar sesión' ?>
            </button>
        </form>
    </section>
</main>
<style>
    .mfa-instructions { margin: 1.25rem 0; padding: 1rem; overflow-wrap: anywhere; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; line-height: 1.6; }
    .mfa-instructions code { display: inline-block; padding: .4rem .55rem; color: #7f1d32; background: #fff; border-radius: 6px; font-size: .95rem; }
    .mfa-instructions details { margin: .75rem 0; }
    .mfa-instructions details code { max-width: 100%; overflow-wrap: anywhere; }
</style>
</body>
</html>
