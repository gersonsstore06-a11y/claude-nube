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
    <?php
    require_once __DIR__ . '/storefront_ui.php';
    $currentScript = basename($normalizedRequestPath);
    $currentCategory = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: null;
    $onSaleView = ($_GET['en_oferta'] ?? '') === '1';
    $isHome = in_array($currentScript, ['', 'index.php', 'home.php', basename(BASE_URL)], true);
    $isCatalog = $currentScript === 'catalogo.php';
    $navLinks = [
        ['label' => 'Inicio', 'href' => BASE_URL . '/index.php', 'active' => $isHome],
        ['label' => 'Catálogo', 'href' => BASE_URL . '/views/catalogo.php', 'active' => $isCatalog && $currentCategory === null && !$onSaleView],
        ['label' => 'Ofertas', 'href' => BASE_URL . '/views/catalogo.php?en_oferta=1', 'active' => $isCatalog && $onSaleView && $currentCategory === null],
    ];
    $menuCategoriesWithProducts = array_values(array_filter(
        $menuCategories,
        static fn (array $category): bool => (int) ($category['product_count'] ?? 0) > 0
    ));
    ?>
    <header class="sf-header" data-header>
        <div class="sf-header-bar sf-container">
            <button class="sf-icon-button sf-header-menu" type="button" aria-label="Abrir menú" aria-controls="sf-mobile-nav" aria-expanded="false" data-nav-open>
                <?php echo sfIcon('menu'); ?>
            </button>

            <a class="sf-brand" href="<?php echo BASE_URL; ?>/index.php" aria-label="Gerson's Store, ir al inicio">
                <span class="sf-brand-mark">
                    <?php if (STORE_LOGO_URL): ?>
                        <img src="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="44" height="44">
                    <?php else: ?>
                        <span>GS</span>
                    <?php endif; ?>
                </span>
                <span class="sf-brand-copy">
                    <strong>Gerson's Store</strong>
                    <small>Joyería, perfumes y regalos</small>
                </span>
            </a>

            <form class="sf-search" id="sf-search" action="<?php echo BASE_URL; ?>/views/catalogo.php" method="get" role="search">
                <label class="sf-visually-hidden" for="sf-search-input">Buscar productos</label>
                <?php echo sfIcon('search', 'sf-icon sf-search-icon'); ?>
                <input
                    id="sf-search-input"
                    type="search"
                    name="buscar"
                    value="<?php echo htmlspecialchars($menuSearch, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Buscar productos, código o categoría"
                    autocomplete="off"
                    enterkeyhint="search"
                >
                <button class="sf-search-submit" type="submit">Buscar</button>
            </form>

            <div class="sf-header-actions">
                <button class="sf-icon-button sf-header-search-toggle" type="button" aria-label="Mostrar buscador" aria-controls="sf-search" aria-expanded="false" data-search-toggle>
                    <?php echo sfIcon('search'); ?>
                </button>
                <?php if ($adminLoggedIn): ?>
                    <a class="sf-header-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php"><?php echo sfIcon('user'); ?><span>Panel</span></a>
                <?php endif; ?>
                <a class="sf-cart-button" href="<?php echo BASE_URL; ?>/views/carrito.php" data-cart-open aria-label="Carrito, <?php echo $cartItemCount; ?> producto<?php echo $cartItemCount === 1 ? '' : 's'; ?>">
                    <?php echo sfIcon('bag'); ?>
                    <span class="sf-cart-label">Carrito</span>
                    <span class="sf-cart-count<?php echo $cartItemCount > 0 ? '' : ' is-empty'; ?>" data-cart-count><?php echo $cartItemCount; ?></span>
                </a>
            </div>
        </div>

        <nav class="sf-nav" aria-label="Navegación principal">
            <div class="sf-container sf-nav-inner">
                <?php foreach ($navLinks as $link): ?>
                    <a class="sf-nav-link<?php echo $link['active'] ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $link['active'] ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
                <?php if ($menuCategoriesWithProducts !== []): ?>
                    <span class="sf-nav-divider" aria-hidden="true"></span>
                    <?php foreach ($menuCategoriesWithProducts as $category): ?>
                        <?php $isActiveCategory = $isCatalog && $currentCategory === (int) $category['id_categoria']; ?>
                        <a class="sf-nav-link<?php echo $isActiveCategory ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars(sfCategoryUrl((int) $category['id_categoria']), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isActiveCategory ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="sf-drawer sf-drawer-left" id="sf-mobile-nav" role="dialog" aria-modal="true" aria-label="Menú" hidden data-drawer="nav">
        <div class="sf-drawer-overlay" data-drawer-close></div>
        <div class="sf-drawer-panel" tabindex="-1">
            <div class="sf-drawer-head">
                <strong>Menú</strong>
                <button class="sf-icon-button" type="button" aria-label="Cerrar menú" data-drawer-close><?php echo sfIcon('close'); ?></button>
            </div>
            <nav class="sf-drawer-body sf-mobile-nav" aria-label="Navegación móvil">
                <?php foreach ($navLinks as $link): ?>
                    <a class="sf-mobile-link<?php echo $link['active'] ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $link['active'] ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?><?php echo sfIcon('chevron-right'); ?></a>
                <?php endforeach; ?>
                <?php if ($menuCategoriesWithProducts !== []): ?>
                    <span class="sf-mobile-label">Categorías</span>
                    <?php foreach ($menuCategoriesWithProducts as $category): ?>
                        <a class="sf-mobile-link" href="<?php echo htmlspecialchars(sfCategoryUrl((int) $category['id_categoria']), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            <small><?php echo (int) $category['product_count']; ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <span class="sf-mobile-label">Tu compra</span>
                <a class="sf-mobile-link" href="<?php echo BASE_URL; ?>/views/carrito.php">Carrito <small data-cart-count-text><?php echo $cartItemCount; ?></small></a>
                <?php if ($adminLoggedIn): ?>
                    <a class="sf-mobile-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Panel admin</a>
                    <a class="sf-mobile-link" href="<?php echo BASE_URL; ?>/views/login.php?action=logout">Cerrar sesión</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <main id="contenido" class="sf-main" tabindex="-1">
<?php endif; ?>
