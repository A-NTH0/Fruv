<?= $this->include('plantilla/menu') ?>
<main class="mfa-security">
    <section class="mfa-security-card">
        <h1>Seguridad de la cuenta</h1>
        <p>La autenticación en dos pasos está activa. Para iniciar sesión necesitas tu aplicación autenticadora.</p>
        <p>Los códigos de recuperación permiten acceder una sola vez si pierdes tu dispositivo.</p>

        <?php if (! empty($success)): ?>
            <p class="mfa-notice mfa-notice-success" role="status"><?= esc($success) ?></p>
        <?php endif; ?>
        <?php if (! empty($warning)): ?>
            <p class="mfa-notice" role="status"><?= esc($warning) ?></p>
        <?php endif; ?>
        <?php if (! empty($error)): ?>
            <p class="mfa-notice mfa-notice-error" role="alert"><?= esc($error) ?></p>
        <?php endif; ?>

        <?php if (is_array($recoveryCodes) && $recoveryCodes !== []): ?>
            <div class="mfa-recovery-panel">
                <h2>Guarda tus nuevos códigos</h2>
                <p>Se muestran una sola vez. Cada código se invalida después de utilizarlo.</p>
                <ul>
                    <?php foreach ($recoveryCodes as $code): ?>
                        <li><code><?= esc($code) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('seguridad/codigos-recuperacion') ?>" method="post" class="mfa-regenerate-form">
            <?= csrf_field() ?>
            <label for="mfa-current-code">Código actual de tu aplicación</label>
            <input id="mfa-current-code" name="code" type="text" inputmode="numeric"
                   autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
            <button type="submit">Regenerar códigos de recuperación</button>
        </form>
    </section>
</main>
<style>
    .mfa-security { width: min(100% - 2rem, 760px); margin: 2rem auto; }
    .mfa-security-card { padding: clamp(1.25rem, 4vw, 2.5rem); background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 12px 30px rgba(31, 41, 55, .08); }
    .mfa-security-card h1 { margin-top: 0; }
    .mfa-regenerate-form { display: grid; gap: .8rem; max-width: 420px; margin-top: 2rem; }
    .mfa-regenerate-form input { min-height: 44px; padding: .6rem .8rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
    .mfa-regenerate-form button { min-height: 44px; padding: .6rem 1rem; color: #fff; background: #7f1d32; border: 0; border-radius: 8px; font: inherit; font-weight: 700; cursor: pointer; }
    .mfa-notice { padding: .8rem 1rem; color: #713f12; background: #fef3c7; border-radius: 8px; }
    .mfa-notice-success { color: #14532d; background: #dcfce7; }
    .mfa-notice-error { color: #991b1b; background: #fee2e2; }
    .mfa-recovery-panel { padding: 1rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; }
    .mfa-recovery-panel ul { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: .5rem; padding: 0; list-style: none; }
    .mfa-recovery-panel li { padding: .5rem; background: white; border-radius: 6px; font-family: monospace; text-align: center; }
    @media (max-width: 640px) { .mfa-security { width: calc(100% - 1rem); margin: 1rem auto; } }
</style>
