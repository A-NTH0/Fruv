<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifica tu correo - Fruver</title>
    <link rel="stylesheet" href="<?= base_url('css/login.css?v=' . filemtime(FCPATH . 'css/login.css')) ?>">
    <link rel="stylesheet" href="<?= base_url('css/accesibilidad.css') ?>">
</head>
<body>
<main class="glass-card">
    <section class="left-side">
        <div class="header-block">
            <h1>Verifica tu correo</h1>
            <p class="subtitle">Es un paso único para confirmar tu cuenta.</p>
        </div>

        <?php if (! empty($error)): ?>
            <p class="form-message" role="alert"><?= esc($error) ?></p>
        <?php endif; ?>
        <?php if (! empty($notice)): ?>
            <p class="form-message" role="status"><?= esc($notice) ?></p>
        <?php endif; ?>

        <p>Enviamos un código de 6 dígitos a <strong><?= esc($email) ?></strong>. El código vence en 10 minutos.</p>

        <form action="<?= base_url('login/verificar-correo') ?>" method="post">
            <?= csrf_field() ?>
            <div class="input-group">
                <label for="email-code">Código enviado a tu correo</label>
                <input id="email-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       pattern="[0-9]{6}" maxlength="6" minlength="6" required autofocus>
            </div>
            <button type="submit" class="login-btn">Verificar e iniciar sesión</button>
        </form>

        <form action="<?= base_url('login/cancelar-verificacion-correo') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="change-account-btn">Usar otra cuenta</button>
        </form>
    </section>
</main>
<style>
    .change-account-btn { display: block; margin: 1rem auto 0; padding: .5rem 1rem; color: #fff; background: transparent; border: 0; font: inherit; text-decoration: underline; cursor: pointer; }
</style>
</body>
</html>
