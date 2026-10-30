<?php
    $uri           = service('uri');
    $pagina_actual = $uri->getSegment(1);
    $esCliente     = session()->get('auth_role') === 'cliente';
    $esVendedor    = session()->get('auth_role') === 'vendedor';
    $esRepartidor  = session()->get('auth_role') === 'repartidor';
    $cantCarrito   = $esCliente ? count(session()->get('carrito') ?? []) : 0;
    $vistaRep      = service('request')->getGet('vista') ?? 'asignados';

    // aria-current="page" en el enlace de la página actual (RNF-01)
    $actual = static fn (bool $activo): string => $activo ? ' aria-current="page"' : '';
?>
<link rel="stylesheet" href="<?= base_url('css/accesibilidad.css') ?>">

<a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>

<aside class="sidebar" id="menuLateral">
    <a class="app-brand" href="<?= base_url($esCliente ? 'productos' : ($esVendedor ? 'clientes' : ($esRepartidor ? 'repartidores' : 'dashboard'))) ?>">
        <span class="app-brand-name">Fruver App</span>
        <span class="app-brand-role">
            <?= $esCliente ? 'Tienda en línea' : ($esVendedor ? 'Portal de vendedor' : ($esRepartidor ? 'Portal de repartidor' : 'Sistema de gestión')) ?>
        </span>
    </a>

    <nav class="flex-1 px-3 space-y-1" aria-label="Menú principal">
        <?php if ($esCliente): ?>
            <a href="<?= base_url('productos') ?>" class="nav-item <?= $pagina_actual === 'productos' ? 'active' : '' ?>"<?= $actual($pagina_actual === 'productos') ?>>
                <i data-lucide="layout-grid" class="w-4 h-4" aria-hidden="true"></i>
                <span>Catálogo</span>
            </a>
            <a href="<?= base_url('carrito') ?>" class="nav-item <?= $pagina_actual === 'carrito' ? 'active' : '' ?>"<?= $actual($pagina_actual === 'carrito') ?>>
                <i data-lucide="shopping-cart" class="w-4 h-4" aria-hidden="true"></i>
                <span>Mi carrito</span>
                <span id="badge-carrito" class="nav-badge" style="<?= $cantCarrito > 0 ? '' : 'display:none' ?>"><?= $cantCarrito ?></span>
            </a>
            <a href="<?= base_url('mis-pedidos') ?>" class="nav-item <?= $pagina_actual === 'mis-pedidos' ? 'active' : '' ?>"<?= $actual($pagina_actual === 'mis-pedidos') ?>>
                <i data-lucide="package" class="w-4 h-4" aria-hidden="true"></i>
                <span>Mis pedidos</span>
            </a>
            <a href="<?= base_url('perfil') ?>" class="nav-item <?= $pagina_actual === 'perfil' ? 'active' : '' ?>"<?= $actual($pagina_actual === 'perfil') ?>>
                <i data-lucide="user-circle" class="w-4 h-4" aria-hidden="true"></i>
                <span>Mi perfil</span>
            </a>
        <?php elseif ($esVendedor): ?>
            <a href="<?= base_url('clientes') ?>" class="nav-item <?= $pagina_actual === 'clientes' ? 'active' : '' ?>"<?= $actual($pagina_actual === 'clientes') ?>>
                <i data-lucide="users" class="w-4 h-4" aria-hidden="true"></i>
                <span>Catálogo de clientes</span>
            </a>
        <?php elseif ($esRepartidor): ?>
            <a href="<?= base_url('repartidores?vista=asignados') ?>" id="nav-rep-asignados" onclick="if(window.cambiarVistaRep){event.preventDefault();cambiarVistaRep('asignados');}" class="nav-item <?= $vistaRep === 'asignados' ? 'active' : '' ?>"<?= $actual($vistaRep === 'asignados') ?>>
                <i data-lucide="truck" class="w-4 h-4" aria-hidden="true"></i>
                <span>Mis entregas</span>
                <span id="nav-badge-asignados" class="nav-badge" style="display:none">0</span>
            </a>
            <a href="<?= base_url('repartidores?vista=disponibles') ?>" id="nav-rep-disponibles" onclick="if(window.cambiarVistaRep){event.preventDefault();cambiarVistaRep('disponibles');}" class="nav-item <?= $vistaRep === 'disponibles' ? 'active' : '' ?>"<?= $actual($vistaRep === 'disponibles') ?>>
                <i data-lucide="bell-ring" class="w-4 h-4" aria-hidden="true"></i>
                <span>Nuevos pedidos</span>
                <span id="nav-badge-disponibles" class="nav-badge" style="display:none">0</span>
            </a>
            <a href="<?= base_url('repartidores?vista=historial') ?>" id="nav-rep-historial" onclick="if(window.cambiarVistaRep){event.preventDefault();cambiarVistaRep('historial');}" class="nav-item <?= $vistaRep === 'historial' ? 'active' : '' ?>"<?= $actual($vistaRep === 'historial') ?>>
                <i data-lucide="check-circle-2" class="w-4 h-4" aria-hidden="true"></i>
                <span>Entregados</span>
            </a>
            <a href="<?= base_url('repartidores?vista=perfil') ?>" id="nav-rep-perfil" onclick="if(window.cambiarVistaRep){event.preventDefault();cambiarVistaRep('perfil');}" class="nav-item <?= $vistaRep === 'perfil' ? 'active' : '' ?>"<?= $actual($vistaRep === 'perfil') ?>>
                <i data-lucide="user-circle" class="w-4 h-4" aria-hidden="true"></i>
                <span>Mi perfil</span>
            </a>
        <?php else: ?>
            <a href="<?= base_url('dashboard') ?>"
               class="nav-item <?= ($pagina_actual == 'dashboard' || $pagina_actual == '') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'dashboard' || $pagina_actual == '') ?>>
                <i data-lucide="layout-dashboard" class="w-4 h-4" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= base_url('inventario') ?>"
               class="nav-item <?= ($pagina_actual == 'inventario') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'inventario') ?>>
                <i data-lucide="box" class="w-4 h-4" aria-hidden="true"></i>
                <span>Inventario</span>
            </a>

            <a href="<?= base_url('pedidos') ?>"
               class="nav-item <?= ($pagina_actual == 'pedidos') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'pedidos') ?>>
                <i data-lucide="truck" class="w-4 h-4" aria-hidden="true"></i>
                <span>Pedidos</span>
            </a>

            <a href="<?= base_url('productos') ?>"
               class="nav-item <?= ($pagina_actual == 'productos') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'productos') ?>>
                <i data-lucide="layout-grid" class="w-4 h-4" aria-hidden="true"></i>
                <span>Catálogo de Productos</span>
            </a>

            <a href="<?= base_url('clientes') ?>"
               class="nav-item <?= ($pagina_actual == 'clientes') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'clientes') ?>>
                <i data-lucide="users" class="w-4 h-4" aria-hidden="true"></i>
                <span>Clientes</span>
            </a>

            <a href="<?= base_url('repartidores') ?>"
               class="nav-item <?= ($pagina_actual == 'repartidores') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'repartidores') ?>>
                <i data-lucide="user-square" class="w-4 h-4" aria-hidden="true"></i>
                <span>Repartidores</span>
            </a>

            <a href="<?= base_url('vendedores') ?>"
               class="nav-item <?= ($pagina_actual == 'vendedores') ? 'active' : '' ?>"<?= $actual($pagina_actual == 'vendedores') ?>>
                <i data-lucide="user-round-cog" class="w-4 h-4" aria-hidden="true"></i>
                <span>Vendedores</span>
            </a>
        <?php endif; ?>
        <?php if (in_array($pagina_actual, ['pedidos', 'mis-pedidos'], true) || ($pagina_actual === 'repartidores' && $esRepartidor)): ?>
            <div class="px-3 py-2">
                <label for="selFrecuencia" class="block text-xs font-bold uppercase tracking-wider opacity-70 mb-1">Actualización automática</label>
                <select id="selFrecuencia" class="w-full rounded-lg border px-2 py-1.5 text-sm text-gray-800"
                        onchange="fruverCambiarFrecuencia(this.value)">
                    <option value="3000">Cada 3 segundos</option>
                    <option value="5000">Cada 5 segundos (recomendado)</option>
                    <option value="10000">Cada 10 segundos</option>
                    <option value="15000">Cada 15 segundos</option>
                    <option value="30000">Cada 30 segundos</option>
                    <option value="60000">Cada minuto</option>
                </select>
            </div>
        <?php endif; ?>
        <a href="<?= base_url('logout') ?>" class="nav-item mt-auto">
            <i data-lucide="log-out" class="w-4 h-4" aria-hidden="true"></i>
            <span>Cerrar sesión</span>
        </a>
    </nav>
</aside>

<script>
  /* Frecuencia de actualización automática (se guarda por dispositivo) */
  (function () {
      const CLAVE = 'fruver_frecuencia_ms';
      const PERMITIDOS = [3000, 5000, 10000, 15000, 30000, 60000];

      window.fruverFrecuenciaMs = function (porDefecto) {
          try {
              const v = parseInt(localStorage.getItem(CLAVE), 10);
              if (PERMITIDOS.includes(v)) return v;
          } catch (e) { /* sin almacenamiento: usa el valor por defecto */ }
          return porDefecto;
      };

      window.fruverCambiarFrecuencia = function (ms) {
          try { localStorage.setItem(CLAVE, String(ms)); } catch (e) {}
          window.dispatchEvent(new CustomEvent('fruver:frecuencia'));
      };

      // fetch con tiempo límite: una respuesta colgada ya no acumula conexiones en Apache
      window.fruverFetch = function (url, opciones = {}, limiteMs = 8000) {
          const ctl = new AbortController();
          const t = setTimeout(() => ctl.abort(), limiteMs);
          return fetch(url, { ...opciones, signal: ctl.signal }).finally(() => clearTimeout(t));
      };

      document.addEventListener('DOMContentLoaded', () => {
          const sel = document.getElementById('selFrecuencia');
          if (sel) sel.value = String(window.fruverFrecuenciaMs(5000));
      });
  })();
</script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  if (window.lucide) {
      window.lucide.createIcons();
  }
  const principal = document.querySelector('main');
  const salto = document.querySelector('.skip-link');
  if (principal && salto) {
      if (!principal.id) principal.id = 'contenido-principal';
      principal.tabIndex = -1;
      salto.setAttribute('href', '#' + principal.id);
  } else if (salto) {
      salto.remove();
  }

  const inactividadMaxima = 10 * 60 * 1000;
  let ultimaActividad = Date.now();
  let redireccionandoPorInactividad = false;
  const registrarActividad = () => {
      ultimaActividad = Date.now();
  };
  const revisarInactividad = () => {
      if (!redireccionandoPorInactividad && Date.now() - ultimaActividad >= inactividadMaxima) {
          redireccionandoPorInactividad = true;
          window.location.assign('<?= esc(base_url('logout'), 'js') ?>');
      }
  };

  ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach((evento) => {
      document.addEventListener(evento, registrarActividad, { passive: true });
  });
  window.setInterval(revisarInactividad, 1000);
</script>

<style>
    :root { --app-nav-height: 76px; }
    html { scroll-padding-top: calc(var(--app-nav-height) + 1rem); }
    body { padding-top: var(--app-nav-height) !important; }
    .sidebar {
        position: fixed !important;
        inset: 0 0 auto 0 !important;
        z-index: 1000 !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 1.5rem !important;
        width: 100% !important;
        height: var(--app-nav-height) !important;
        min-height: var(--app-nav-height) !important;
        padding: 0 1.5rem !important;
        overflow: visible !important;
        color: white !important;
        background: linear-gradient(100deg, #1a0b0b 0%, #2b1117 60%, #35131c 100%) !important;
        box-shadow: 0 4px 18px rgba(20, 8, 10, .16) !important;
        box-sizing: border-box !important;
        transform: none !important;
    }
    .app-brand {
        display: flex !important;
        flex: 0 0 auto !important;
        flex-direction: column !important;
        justify-content: center !important;
        gap: .12rem !important;
        min-width: 190px !important;
        color: white !important;
        text-decoration: none !important;
    }
    .app-brand-name { font-size: 1.1rem !important; font-weight: 800 !important; letter-spacing: -.02em !important; }
    .app-brand-role { color: #b9aab0 !important; font-size: .68rem !important; }
    .sidebar nav {
        display: flex !important;
        flex: 1 1 auto !important;
        align-items: center !important;
        gap: .35rem !important;
        min-width: 0 !important;
        height: 100% !important;
        padding: 0 !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        scrollbar-width: none !important;
    }
    .sidebar nav::-webkit-scrollbar { display: none !important; }
    .sidebar nav > * { margin: 0 !important; }
    .sidebar .nav-item {
        display: inline-flex !important;
        flex: 0 0 auto !important;
        align-items: center !important;
        justify-content: center !important;
        gap: .55rem !important;
        min-height: 42px !important;
        padding: .65rem .85rem !important;
        margin: 0 !important;
        border-radius: .7rem !important;
        color: #d1c7cb !important;
        font-size: .82rem !important;
        font-weight: 600 !important;
        line-height: 1.1 !important;
        text-decoration: none !important;
        white-space: nowrap !important;
        transition: background .18s ease, color .18s ease !important;
    }
    .sidebar .nav-item i, .sidebar .nav-item svg { width: 17px !important; height: 17px !important; color: #c2b3b8 !important; }
    .sidebar .nav-item:hover { color: white !important; background: rgba(255,255,255,.09) !important; }
    .sidebar .nav-item.active { color: white !important; background: #e91e4d !important; box-shadow: 0 4px 12px rgba(233,30,77,.25) !important; }
    .sidebar .nav-item.active i, .sidebar .nav-item.active svg { color: white !important; }
    .sidebar .nav-badge {
        min-width: 1.25rem !important;
        padding: 0 .35rem !important;
        margin-left: .1rem !important;
        border-radius: 999px !important;
        color: #e11d48 !important;
        background: white !important;
        font-size: .68rem !important;
        font-weight: 800 !important;
        line-height: 1.25rem !important;
        text-align: center !important;
    }
    .sidebar .nav-item[href$="/logout"] { margin-left: auto !important; }
    .mobile-menu-btn, .sidebar-overlay { display: none !important; }
    .main-content, .cli-main, body > main, .sidebar ~ main {
        width: 100% !important;
        max-width: none !important;
        min-height: calc(100vh - var(--app-nav-height)) !important;
        margin: 0 !important;
        padding: 1.75rem clamp(1rem, 2.5vw, 2.75rem) 2.5rem !important;
        box-sizing: border-box !important;
    }
    .main-content > .max-w-7xl, .main-content > div.mx-auto, main > .max-w-7xl,
    main > div.mx-auto { width: 100% !important; max-width: none !important; }
    .skip-link:focus { top: calc(var(--app-nav-height) + .5rem) !important; }
    @media (max-width: 980px) {
        :root { --app-nav-height: 68px; }
        .sidebar { gap: .8rem !important; padding-inline: 1rem !important; }
        .app-brand { min-width: 150px !important; }
        .app-brand-name { font-size: 1rem !important; }
        .sidebar .nav-item { min-height: 40px !important; padding: .55rem .65rem !important; font-size: .76rem !important; }
    }
    @media (max-width: 640px) {
        :root { --app-nav-height: 62px; }
        .sidebar { gap: .65rem !important; padding-inline: .7rem !important; }
        .app-brand { min-width: 112px !important; max-width: 112px !important; }
        .app-brand-name { font-size: .87rem !important; }
        .app-brand-role { font-size: .58rem !important; }
        .sidebar nav { gap: .15rem !important; }
        .sidebar .nav-item { min-height: 38px !important; padding: .5rem .55rem !important; font-size: .7rem !important; }
        .sidebar .nav-item i, .sidebar .nav-item svg { width: 15px !important; height: 15px !important; }
        .main-content, .cli-main, body > main, .sidebar ~ main {
            padding: 1.25rem .85rem 2rem !important;
        }
    }
</style>