<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Iniciar sesión - Fruver</title>
    <link rel="stylesheet" href="<?= base_url('css/login.css?v=' . filemtime(FCPATH . 'css/login.css')) ?>">
    <link rel="stylesheet" href="<?= base_url('css/accesibilidad.css') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
  </head>

  <body>
    <?php $hayErrores = isset($error) || isset($validation); ?>

    <main class="glass-card">
        <div class="left-side">
            <div class="header-block">
                <h2 role="heading" aria-level="1">Bienvenido</h2>
                <p class="subtitle">Inicia sesión para continuar</p>
            </div>

            <?php if ($hayErrores): ?>
                <div id="login-errores" role="alert">
                    <?php if (isset($error)): ?>
                        <p class="form-message"><?= esc($error) ?></p>
                    <?php endif; ?>
                    <?php if (isset($validation)): ?>
                        <div class="form-message">
                            <?php foreach ($validation->getErrors() as $message): ?>
                                <p><?= esc($message) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="<?= base_url('login') ?>" method="post">

                <div class="input-group">
                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                    <label for="login-email" class="sr-only">Correo electrónico</label>
                    <input type="email" id="login-email" name="email" placeholder="Correo electrónico"
                           value="<?= esc($email ?? '') ?>" autocomplete="email" required
                           <?= $hayErrores ? 'aria-invalid="true" aria-describedby="login-errores"' : '' ?>>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <label for="login-contrasena" class="sr-only">Contraseña</label>
                    <input type="password" id="login-contrasena" name="contrasena" placeholder="Contraseña"
                           autocomplete="current-password" required
                           <?= $hayErrores ? 'aria-invalid="true" aria-describedby="login-errores"' : '' ?>>
                </div>

                <button type="submit" class="login-btn" data-loading-label="Iniciando sesión...">Iniciar sesión</button>
            </form>
        </div>
    </main>
  </body>
</html>