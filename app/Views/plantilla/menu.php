<?php 
    $uri = service('uri'); 
    $pagina_actual = $uri->getSegment(1); 
?>

<aside class="sidebar">
    <div class="p-6 mb-2">
        <h1 class="text-xl font-bold text-white">🍓Fruver App</h1>
        <p class="text-gray-400 text-xs mt-1">
            <?= session()->get('auth_role') === 'cliente' ? 'Catálogo de productos' : 'Sistema de gestión' ?>
        </p>
    </div>

    <nav class="flex-1 px-3 space-y-1">
        <?php if (session()->get('auth_role') === 'cliente'): ?>
            <a href="<?= base_url('productos') ?>" class="nav-item active">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
                <span>Catálogo</span>
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
</style>