<?php
    $uri           = service('uri');
    $pagina_actual = $uri->getSegment(1);
    $esCliente     = session()->get('auth_role') === 'cliente';
    $cantCarrito   = $esCliente ? count(session()->get('carrito') ?? []) : 0;
?>

<button type="button" class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Abrir menú">
    <i data-lucide="menu" class="w-5 h-5"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar">
    <div class="p-6 mb-2">
        <h1 class="text-xl font-bold text-white">🍓Fruver App</h1>
        <p class="text-gray-400 text-xs mt-1">
            <?= $esCliente ? 'Tienda en línea' : 'Sistema de gestión' ?>
        </p>
    </div>

    <nav class="flex-1 px-3 space-y-1">
        <?php if ($esCliente): ?>
            <a href="<?= base_url('productos') ?>" class="nav-item <?= $pagina_actual === 'productos' ? 'active' : '' ?>">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
                <span>Catálogo</span>
            </a>
            <a href="<?= base_url('carrito') ?>" class="nav-item <?= $pagina_actual === 'carrito' ? 'active' : '' ?>">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                <span>Mi carrito</span>
                <span id="badge-carrito" class="nav-badge" style="<?= $cantCarrito > 0 ? '' : 'display:none' ?>"><?= $cantCarrito ?></span>
            </a>
            <a href="<?= base_url('mis-pedidos') ?>" class="nav-item <?= $pagina_actual === 'mis-pedidos' ? 'active' : '' ?>">
                <i data-lucide="package" class="w-4 h-4"></i>
                <span>Mis pedidos</span>
            </a>
            <a href="<?= base_url('perfil') ?>" class="nav-item <?= $pagina_actual === 'perfil' ? 'active' : '' ?>">
                <i data-lucide="user-circle" class="w-4 h-4"></i>
                <span>Mi perfil</span>
            </a>
        <?php else: ?>
            <a href="<?= base_url('dashboard') ?>"
               class="nav-item <?= ($pagina_actual == 'dashboard' || $pagina_actual == '') ? 'active' : '' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= base_url('inventario') ?>"
               class="nav-item <?= ($pagina_actual == 'inventario') ? 'active' : '' ?>">
                <i data-lucide="box" class="w-4 h-4"></i>
                <span>Inventario</span>
            </a>

            <a href="<?= base_url('pedidos') ?>"
               class="nav-item <?= ($pagina_actual == 'pedidos') ? 'active' : '' ?>">
                <i data-lucide="truck" class="w-4 h-4"></i>
                <span>Pedidos</span>
            </a>

            <a href="<?= base_url('productos') ?>"
               class="nav-item <?= ($pagina_actual == 'productos') ? 'active' : '' ?>">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
                <span>Catálogo de Productos</span>
            </a>

            <a href="<?= base_url('clientes') ?>"
               class="nav-item <?= ($pagina_actual == 'clientes') ? 'active' : '' ?>">
                <i data-lucide="users" class="w-4 h-4"></i>
                <span>Clientes</span>
            </a>

            <a href="<?= base_url('repartidores') ?>"
               class="nav-item <?= ($pagina_actual == 'repartidores') ? 'active' : '' ?>">
                <i data-lucide="user-square" class="w-4 h-4"></i>
                <span>Repartidores</span>
            </a>
        <?php endif; ?>
        <a href="<?= base_url('logout') ?>" class="nav-item mt-auto">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            <span>Cerrar sesión</span>
        </a>
    </nav>
</aside>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();
  function toggleSidebar() {
      document.querySelector('.sidebar').classList.toggle('mobile-open');
      document.getElementById('sidebarOverlay').classList.toggle('active');
  }
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