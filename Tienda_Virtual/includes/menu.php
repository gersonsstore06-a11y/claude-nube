<?php
require_once __DIR__ . '/storefront_cart.php';

$menuCategories = storefrontCategories();
$menuSearch = trim((string) ($_GET['buscar'] ?? ''));
$adminLoggedIn = adminAuthIsLoggedIn();
$cartItemCount = storefrontCartCount();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$normalizedRequestPath = str_replace('\\', '/', $requestPath);
$isAdminArea = strpos($normalizedRequestPath, '/admin/') !== false;

function adminMenuLinkClass(string $targetPath, string $currentPath): string
{
    $isActive = substr($currentPath, -strlen($targetPath)) === $targetPath;

    return 'top-category-link' . ($isActive ? ' is-active' : '');
}
?>
<?php if ($isAdminArea): ?>
    <header class="admin-shell-header">
        <div class="admin-shell-top">
            <a class="logo-lockup" href="<?php echo BASE_URL; ?>/admin/dashboard.php" aria-label="Ir al dashboard administrativo">
                <span class="logo-badge">
                    <?php if (STORE_LOGO_URL): ?>
                        <img class="logo-badge-image" src="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo de Gerson's Store">
                    <?php else: ?>
                        <span class="logo-badge-inner">GS</span>
                    <?php endif; ?>
                </span>
                <span class="logo-copy">
                    <strong>Gerson's Store</strong>
                    <small>Panel administrativo</small>
                </span>
            </a>

            <div class="admin-shell-actions">
                <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/index.php">Ver tienda</a>
                <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/views/login.php?action=logout">Salir</a>
            </div>
        </div>

        <nav class="admin-shell-nav" aria-label="Navegacion administrativa">
            <a class="<?php echo adminMenuLinkClass('/admin/dashboard.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a>
            <a class="<?php echo adminMenuLinkClass('/admin/productos/index.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Productos</a>
            <a class="<?php echo adminMenuLinkClass('/admin/categorias/index.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/categorias/index.php">Categorias</a>
            <a class="<?php echo adminMenuLinkClass('/admin/pedidos/index.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/pedidos/index.php">Pedidos</a>
            <a class="<?php echo adminMenuLinkClass('/admin/compras/index.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/compras/index.php">Compras</a>
            <a class="<?php echo adminMenuLinkClass('/admin/gastos/index.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/gastos/index.php">Gastos</a>
            <a class="<?php echo adminMenuLinkClass('/admin/reportes/index.php', $normalizedRequestPath); ?>" href="<?php echo BASE_URL; ?>/admin/reportes/index.php">Reportes</a>
        </nav>
    </header>
<?php else: ?>
    <header class="store-header">
        <a class="logo-lockup" href="<?php echo BASE_URL; ?>/index.php" aria-label="Ir al inicio de la tienda">
            <span class="logo-badge">
                <?php if (STORE_LOGO_URL): ?>
                    <img class="logo-badge-image" src="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo de Gerson's Store">
                <?php else: ?>
                    <span class="logo-badge-inner">GS</span>
                <?php endif; ?>
            </span>
            <span class="logo-copy">
                <strong>Gerson's Store</strong>
                <small>Joyeria, perfumes y regalos</small>
            </span>
        </a>

        <form class="store-search" action="<?php echo BASE_URL; ?>/views/catalogo.php" method="get">
            <input
                type="search"
                name="buscar"
                value="<?php echo htmlspecialchars($menuSearch, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Buscar productos, codigo o categoria"
            >
            <button type="submit">Buscar</button>
        </form>

        <div class="header-actions">
            <?php if ($adminLoggedIn): ?>
                <a class="header-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Panel admin</a>
                <a class="header-link" href="<?php echo BASE_URL; ?>/views/login.php?action=logout">Salir</a>
            <?php else: ?>
                <a class="header-link" href="<?php echo BASE_URL; ?>/views/login.php">Acceso admin</a>
            <?php endif; ?>
            <a class="header-link header-link-cart" href="<?php echo BASE_URL; ?>/views/carrito.php">
                <span class="cart-count"><?php echo $cartItemCount; ?></span>
                Carrito
            </a>
        </div>
    </header>

    <?php if ($menuCategories !== []): ?>
        <nav class="top-categories" aria-label="Categorias principales">
            <a class="top-category-link" href="<?php echo BASE_URL; ?>/views/catalogo.php">Todo</a>
            <?php foreach ($menuCategories as $category): ?>
                <a class="top-category-link" href="<?php echo BASE_URL; ?>/views/catalogo.php?categoria=<?php echo (int) $category['id_categoria']; ?>">
                    <?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
