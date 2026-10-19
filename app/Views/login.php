<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Glass Login</title>
    <link rel="stylesheet" href="<?= base_url('css/login.css?v=' . filemtime(FCPATH . 'css/login.css')) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
  </head>

  <body>

    <div class="glass-card">
        <div class="left-side">
            <div class="header-block">
                <h2>Bienvenido</h2>
                <p class="subtitle">Inicia sesión para continuar</p>
            </div>

            <form id="loginForm">

                <div class="input-group">
                    <i class="fa-regular fa-envelope"></i>

                    <input type="email" placeholder="Email address" required>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>

                    <input type="password" placeholder="Password" required>
                </div>

                <button type="submit" class="login-btn">Iniciar Sesion</button>

                <div class="register-row">
                    <p>Aún no estás registrado?</p>
                    <button type="button" class="register-btn">Registrarse</button>
                </div>

                <div class="divider">
                    <span>or continue with</span>
                </div>

                <div class="social-icons">
                    <button type="button" class="social google-btn" aria-label="Iniciar sesión con Google">
                        <i class="fa-brands fa-google"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
  </body>
</html>
