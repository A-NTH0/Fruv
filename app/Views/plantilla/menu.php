<?php
    $uri           = service('uri');
    $pagina_actual = $uri->getSegment(1);
    $esCliente     = session()->get('auth_role') === 'cliente';
    $cantCarrito   = $esCliente ? count(session()->get('carrito') ?? []) : 0;

    // aria-current="page" en el enlace de la página actual (RNF-01)
    $actual = static fn (bool $activo): string => $activo ? ' aria-current="page"' : '';
?>
<link rel="stylesheet" href="<?= base_url('css/accesibilidad.css') ?>">

<a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>

<button type="button" class="mobile-menu-btn" id="btnMenuMovil" onclick="toggleSidebar()"
        aria-label="Abrir menú de navegación" aria-controls="menuLateral" aria-expanded="false">
    <i data-lucide="menu" class="w-5 h-5" aria-hidden="true"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="menuLateral">
    <div class="p-6 mb-2">
        <p class="text-xl font-bold text-white">🍓Fruver App</p>
        <p class="text-gray-400 text-xs mt-1">
            <?= $esCliente ? 'Tienda en línea' : 'Sistema de gestión' ?>
        </p>
    </div>

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
        <?php endif; ?>
        <a href="<?= base_url('logout') ?>" class="nav-item mt-auto">
            <i data-lucide="log-out" class="w-4 h-4" aria-hidden="true"></i>
            <span>Cerrar sesión</span>
        </a>
    </nav>
</aside>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();

  (function () {
      const menu     = document.getElementById('menuLateral');
      const overlay  = document.getElementById('sidebarOverlay');
      const boton    = document.getElementById('btnMenuMovil');
      const esMovil  = window.matchMedia('(max-width: 768px)');

      // En móvil el menú está fuera de pantalla: cerrado no debe recibir foco con Tab (2.4.3 / 2.4.7).
      function sincronizarMenu() {
          menu.inert = esMovil.matches && !menu.classList.contains('mobile-open');
      }

      function toggleSidebar() {
          const abrir = !menu.classList.contains('mobile-open');
          menu.classList.toggle('mobile-open', abrir);
          overlay.classList.toggle('active', abrir);
          boton.setAttribute('aria-expanded', String(abrir));
          boton.setAttribute('aria-label', abrir ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
          sincronizarMenu();
          if (abrir) {
              const primero = menu.querySelector('a');
              if (primero) primero.focus();
          } else {
              boton.focus();
          }
      }
      window.toggleSidebar = toggleSidebar;

      document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && menu.classList.contains('mobile-open')) toggleSidebar();
      });
      esMovil.addEventListener('change', sincronizarMenu);
      sincronizarMenu();

      // Destino del enlace "Saltar al contenido": el <main> de cada página.
      const principal = document.querySelector('main');
      const salto     = document.querySelector('.skip-link');
      if (principal && salto) {
          if (!principal.id) principal.id = 'contenido-principal';
          principal.tabIndex = -1;
          salto.setAttribute('href', '#' + principal.id);
      } else if (salto) {
          salto.remove();
      }
  })();
</script>

<style>
    .sidebar {
        width: 260px;
        background-color: #1A0B0B;
        color: white;
        display: flex;
        flex-direction: column;
        position: fixed;
        height: 100vh;
        left: 0;
        top: 0;
        z-index: 1000;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
    }
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        color: #9ca3af;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 0.875rem;
    }
    .nav-item:hover {
        background-color: rgba(255,255,255,0.05);
        color: white;
    }
    .nav-item.active {
        background-color: #e11d48;
        color: white;
    }
    .nav-badge {
        margin-left: auto;
        min-width: 1.35rem;
        padding: 0 0.4rem;
        border-radius: 999px;
        background: #fff;
        color: #e11d48;
        font-size: 0.7rem;
        font-weight: 800;
        text-align: center;
        line-height: 1.35rem;
    }
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 12px;
        left: 12px;
        z-index: 1100;
        background: #E91E63;
        color: #fff;
        border: 0;
        border-radius: 12px;
        padding: 8px 10px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
</style>