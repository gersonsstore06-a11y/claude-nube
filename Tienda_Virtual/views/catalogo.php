<?php
require_once __DIR__ . '/../includes/storefront_cart.php';
require_once __DIR__ . '/../includes/storefront_ui.php';

$selectedCategory = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
$selectedCategory = $selectedCategory ?: null;
$search = trim((string) ($_GET['buscar'] ?? ''));
$page = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$requestedPerPage = filter_input(INPUT_GET, 'por_pagina', FILTER_VALIDATE_INT);
$allowedPerPage = [12, 24, 36, 48];
$perPage = in_array($requestedPerPage, $allowedPerPage, true) ? $requestedPerPage : 24;
$sort = trim((string) ($_GET['orden'] ?? 'recent'));
$allowedSort = ['recent', 'price_asc', 'price_desc', 'name_asc', 'stock_desc'];
$sort = in_array($sort, $allowedSort, true) ? $sort : 'recent';
$filterPriceMin = filter_input(INPUT_GET, 'precio_min', FILTER_VALIDATE_FLOAT);
$filterPriceMin = $filterPriceMin !== false && $filterPriceMin !== null && $filterPriceMin > 0 ? (float) $filterPriceMin : null;
$filterPriceMax = filter_input(INPUT_GET, 'precio_max', FILTER_VALIDATE_FLOAT);
$filterPriceMax = $filterPriceMax !== false && $filterPriceMax !== null && $filterPriceMax > 0 ? (float) $filterPriceMax : null;
$filterInStock = isset($_GET['en_stock']) && $_GET['en_stock'] === '1';
$filterOnSale = isset($_GET['en_oferta']) && $_GET['en_oferta'] === '1';

$categories = storefrontCategories();
$priceRange = storefrontPriceRange($selectedCategory, $search);
$totalProducts = storefrontProductsCount($selectedCategory, $search, $filterPriceMin, $filterPriceMax, $filterInStock, $filterOnSale);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$products = storefrontProducts($selectedCategory, $search, $perPage, $offset, $sort, $filterPriceMin, $filterPriceMax, $filterInStock, $filterOnSale);
$selectedCategoryName = 'Todos los productos';
$showFrom = $totalProducts > 0 ? $offset + 1 : 0;
$showTo = min($offset + $perPage, $totalProducts);

$activeFilters = [];
if ($search !== '') {
    $activeFilters[] = ['label' => 'Búsqueda: ' . $search, 'remove' => 'buscar'];
}
if ($selectedCategory !== null) {
    foreach ($categories as $cat) {
        if ((int) $cat['id_categoria'] === $selectedCategory) {
            $activeFilters[] = ['label' => 'Categoría: ' . $cat['nombre'], 'remove' => 'categoria'];
            break;
        }
    }
}
if ($filterPriceMin !== null) {
    $activeFilters[] = ['label' => 'Precio mín: ' . appMoney($filterPriceMin, 0), 'remove' => 'precio_min'];
}
if ($filterPriceMax !== null) {
    $activeFilters[] = ['label' => 'Precio máx: ' . appMoney($filterPriceMax, 0), 'remove' => 'precio_max'];
}
if ($filterInStock) {
    $activeFilters[] = ['label' => 'Solo con stock', 'remove' => 'en_stock'];
}
if ($filterOnSale) {
    $activeFilters[] = ['label' => 'Solo en oferta', 'remove' => 'en_oferta'];
}

$buildCatalogUrl = static function (int $targetPage, array $overrides = [], array $remove = []) use ($selectedCategory, $search, $perPage, $sort, $filterPriceMin, $filterPriceMax, $filterInStock, $filterOnSale): string {
    $params = [];

    if ($search !== '') {
        $params['buscar'] = $search;
    }
    if ($selectedCategory !== null && $selectedCategory > 0) {
        $params['categoria'] = $selectedCategory;
    }
    if ($perPage !== 24) {
        $params['por_pagina'] = $perPage;
    }
    if ($sort !== 'recent') {
        $params['orden'] = $sort;
    }
    if ($filterPriceMin !== null) {
        $params['precio_min'] = $filterPriceMin;
    }
    if ($filterPriceMax !== null) {
        $params['precio_max'] = $filterPriceMax;
    }
    if ($filterInStock) {
        $params['en_stock'] = '1';
    }
    if ($filterOnSale) {
        $params['en_oferta'] = '1';
    }
    if ($targetPage > 1) {
        $params['pagina'] = $targetPage;
    }

    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }

    foreach ($remove as $k) {
        unset($params[$k]);
    }

    return BASE_URL . '/views/catalogo.php' . ($params !== [] ? '?' . http_build_query($params) : '');
};

foreach ($categories as $category) {
    if ((int) $category['id_categoria'] === $selectedCategory) {
        $selectedCategoryName = $category['nombre'];
        break;
    }
}

$sortLabels = [
    'recent' => 'Más recientes',
    'price_asc' => 'Precio: menor a mayor',
    'price_desc' => 'Precio: mayor a menor',
    'name_asc' => 'Nombre A-Z',
    'stock_desc' => 'Más stock',
];

$catalogHeading = $filterOnSale && $selectedCategory === null && $search === '' ? 'Ofertas' : $selectedCategoryName;
$pageTitle = $search !== '' ? 'Resultados para "' . $search . '"' : $catalogHeading;
$pageDescription = $selectedCategory !== null
    ? 'Compra ' . $selectedCategoryName . ' en Gerson\'s Store. Precios en Lempiras, ofertas y pedido por WhatsApp.'
    : 'Catálogo completo de Gerson\'s Store: joyería, perfumes y regalos con filtros por precio, disponibilidad y ofertas.';
$sidebarFilterCount = ($filterPriceMin !== null ? 1 : 0) + ($filterPriceMax !== null ? 1 : 0) + ($filterInStock ? 1 : 0) + ($filterOnSale ? 1 : 0);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<div class="sf-page-head">
    <nav class="sf-breadcrumb" aria-label="Ruta de navegación">
        <ol>
            <li><a href="<?php echo BASE_URL; ?>/index.php">Inicio</a></li>
            <?php if ($selectedCategory !== null || $filterOnSale || $search !== ''): ?>
                <li><a href="<?php echo BASE_URL; ?>/views/catalogo.php">Catálogo</a></li>
                <li aria-current="page"><?php echo sfEscape($search !== '' ? 'Búsqueda' : $catalogHeading); ?></li>
            <?php else: ?>
                <li aria-current="page">Catálogo</li>
            <?php endif; ?>
        </ol>
    </nav>
    <div class="sf-page-head-row">
        <div>
            <h1 class="sf-page-title"><?php echo sfEscape($catalogHeading); ?></h1>
            <p class="sf-page-lead">
                <?php if ($search !== ''): ?>
                    <?php echo $totalProducts; ?> resultado<?php echo $totalProducts === 1 ? '' : 's'; ?> para <strong>“<?php echo sfEscape($search); ?>”</strong>
                <?php else: ?>
                    <?php echo $totalProducts; ?> producto<?php echo $totalProducts === 1 ? '' : 's'; ?> disponibles para explorar
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<form class="sf-toolbar" action="" method="get" data-catalog-form>
    <label class="sf-field sf-field-search">
        <span class="sf-visually-hidden">Buscar en el catálogo</span>
        <?php echo sfIcon('search', 'sf-icon sf-field-icon'); ?>
        <input type="search" name="buscar" value="<?php echo sfEscape($search); ?>" placeholder="Buscar producto o código">
    </label>
    <label class="sf-field">
        <span class="sf-visually-hidden">Categoría</span>
        <select name="categoria" data-catalog-auto-submit>
            <option value="">Todas las categorías</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int) $category['id_categoria']; ?>" <?php echo (int) $category['id_categoria'] === $selectedCategory ? 'selected' : ''; ?>><?php echo sfEscape($category['nombre']); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="sf-field">
        <span class="sf-visually-hidden">Ordenar por</span>
        <select name="orden" data-catalog-auto-submit>
            <?php foreach ($sortLabels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $sort === $key ? 'selected' : ''; ?>><?php echo sfEscape($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="sf-field sf-field-compact">
        <span class="sf-visually-hidden">Productos por página</span>
        <select name="por_pagina" data-catalog-auto-submit>
            <?php foreach ($allowedPerPage as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo $size; ?> por página</option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($filterPriceMin !== null): ?><input type="hidden" name="precio_min" value="<?php echo $filterPriceMin; ?>"><?php endif; ?>
    <?php if ($filterPriceMax !== null): ?><input type="hidden" name="precio_max" value="<?php echo $filterPriceMax; ?>"><?php endif; ?>
    <?php if ($filterInStock): ?><input type="hidden" name="en_stock" value="1"><?php endif; ?>
    <?php if ($filterOnSale): ?><input type="hidden" name="en_oferta" value="1"><?php endif; ?>
    <button class="sf-button sf-button-primary" type="submit">Buscar</button>
</form>

<div class="sf-catalog">
    <aside class="sf-filters" aria-label="Filtros">
        <details class="sf-filters-panel" data-filters-panel open>
            <summary class="sf-filters-summary">
                <span><?php echo sfIcon('filter'); ?>Filtros<?php if ($sidebarFilterCount > 0): ?> <b class="sf-filters-count"><?php echo $sidebarFilterCount; ?></b><?php endif; ?></span>
                <?php echo sfIcon('chevron-right', 'sf-icon sf-filters-chevron'); ?>
            </summary>
            <form class="sf-filters-form" action="" method="get" data-catalog-sidebar-form>
                <?php if ($search !== ''): ?><input type="hidden" name="buscar" value="<?php echo sfEscape($search); ?>"><?php endif; ?>
                <?php if ($selectedCategory !== null && $selectedCategory > 0): ?><input type="hidden" name="categoria" value="<?php echo $selectedCategory; ?>"><?php endif; ?>
                <?php if ($perPage !== 24): ?><input type="hidden" name="por_pagina" value="<?php echo $perPage; ?>"><?php endif; ?>
                <?php if ($sort !== 'recent'): ?><input type="hidden" name="orden" value="<?php echo sfEscape($sort); ?>"><?php endif; ?>

                <div class="sf-filter-group">
                    <span class="sf-filter-label">Categoría</span>
                    <div class="sf-chip-list">
                        <a class="sf-chip<?php echo $selectedCategory === null ? ' is-active' : ''; ?>" href="<?php echo sfEscape($buildCatalogUrl(1, [], ['categoria'])); ?>"<?php echo $selectedCategory === null ? ' aria-current="true"' : ''; ?>>Todo</a>
                        <?php foreach ($categories as $category): ?>
                            <?php $isActiveChip = (int) $category['id_categoria'] === $selectedCategory; ?>
                            <a class="sf-chip<?php echo $isActiveChip ? ' is-active' : ''; ?>" href="<?php echo sfEscape($buildCatalogUrl(1, ['categoria' => (int) $category['id_categoria']], [])); ?>"<?php echo $isActiveChip ? ' aria-current="true"' : ''; ?>>
                                <?php echo sfEscape($category['nombre']); ?>
                                <small><?php echo (int) $category['product_count']; ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <fieldset class="sf-filter-group">
                    <legend class="sf-filter-label">Rango de precio</legend>
                    <div class="sf-price-inputs">
                        <label class="sf-field sf-field-prefix">
                            <span class="sf-field-prefix-text"><?php echo sfEscape(appCurrencySymbol()); ?></span>
                            <span class="sf-visually-hidden">Precio mínimo</span>
                            <input type="number" name="precio_min" min="0" step="1" inputmode="numeric" placeholder="Mín" value="<?php echo $filterPriceMin !== null ? number_format($filterPriceMin, 0, '.', '') : ''; ?>">
                        </label>
                        <span aria-hidden="true">–</span>
                        <label class="sf-field sf-field-prefix">
                            <span class="sf-field-prefix-text"><?php echo sfEscape(appCurrencySymbol()); ?></span>
                            <span class="sf-visually-hidden">Precio máximo</span>
                            <input type="number" name="precio_max" min="0" step="1" inputmode="numeric" placeholder="Máx" value="<?php echo $filterPriceMax !== null ? number_format($filterPriceMax, 0, '.', '') : ''; ?>">
                        </label>
                    </div>
                    <?php if ($priceRange['min'] > 0 || $priceRange['max'] > 0): ?>
                        <small class="sf-filter-hint">Disponible: <?php echo sfEscape(appMoney((float) $priceRange['min'], 0)); ?> – <?php echo sfEscape(appMoney((float) $priceRange['max'], 0)); ?></small>
                    <?php endif; ?>
                </fieldset>

                <fieldset class="sf-filter-group">
                    <legend class="sf-filter-label">Disponibilidad</legend>
                    <label class="sf-check">
                        <input type="checkbox" name="en_stock" value="1" <?php echo $filterInStock ? 'checked' : ''; ?> data-catalog-sidebar-checkbox>
                        <span>Solo con stock</span>
                    </label>
                    <label class="sf-check">
                        <input type="checkbox" name="en_oferta" value="1" <?php echo $filterOnSale ? 'checked' : ''; ?> data-catalog-sidebar-checkbox>
                        <span>Solo en oferta</span>
                    </label>
                </fieldset>

                <button type="submit" class="sf-button sf-button-primary sf-button-block">Aplicar filtros</button>
                <?php if ($activeFilters !== []): ?>
                    <a class="sf-button sf-button-ghost sf-button-block" href="<?php echo BASE_URL; ?>/views/catalogo.php">Limpiar todo</a>
                <?php endif; ?>
            </form>
        </details>
    </aside>

    <div class="sf-catalog-main">
        <?php if ($activeFilters !== []): ?>
            <div class="sf-active-filters" role="region" aria-label="Filtros activos">
                <?php foreach ($activeFilters as $af): ?>
                    <a class="sf-chip sf-chip-removable" href="<?php echo sfEscape($buildCatalogUrl(1, [], [$af['remove'], 'pagina'])); ?>">
                        <?php echo sfEscape($af['label']); ?>
                        <span aria-hidden="true"><?php echo sfIcon('close', 'sf-icon sf-icon-xs'); ?></span>
                        <span class="sf-visually-hidden">(quitar filtro)</span>
                    </a>
                <?php endforeach; ?>
                <a class="sf-link-plain" href="<?php echo BASE_URL; ?>/views/catalogo.php">Limpiar todo</a>
            </div>
        <?php endif; ?>

        <?php if ($products !== []): ?>
            <div class="sf-results-meta">
                <h2 class="sf-visually-hidden">Productos</h2>
                <span>Mostrando <?php echo $showFrom; ?>–<?php echo $showTo; ?> de <?php echo $totalProducts; ?></span>
            </div>
            <div class="sf-product-grid sf-product-grid-catalog">
                <?php foreach ($products as $index => $product): ?>
                    <?php echo sfRenderProductCard($product, ['index' => $index, 'eager' => $index < 4]); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="sf-pagination" aria-label="Paginación del catálogo">
                    <?php if ($page > 1): ?>
                        <a class="sf-page-link sf-page-step" href="<?php echo sfEscape($buildCatalogUrl($page - 1)); ?>" rel="prev"><?php echo sfIcon('arrow-left'); ?><span>Anterior</span></a>
                    <?php else: ?>
                        <span class="sf-page-link sf-page-step is-disabled" aria-disabled="true"><?php echo sfIcon('arrow-left'); ?><span>Anterior</span></span>
                    <?php endif; ?>

                    <?php
                    $windowSize = 1;
                    $startPage = max(1, $page - $windowSize);
                    $endPage = min($totalPages, $page + $windowSize);
                    ?>
                    <div class="sf-page-numbers">
                        <?php if ($startPage > 1): ?>
                            <a class="sf-page-link" href="<?php echo sfEscape($buildCatalogUrl(1)); ?>" aria-label="Página 1">1</a>
                            <?php if ($startPage > 2): ?><span class="sf-page-dots" aria-hidden="true">…</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a class="sf-page-link<?php echo $i === $page ? ' is-active' : ''; ?>" href="<?php echo sfEscape($buildCatalogUrl($i)); ?>" aria-label="Página <?php echo $i; ?>"<?php echo $i === $page ? ' aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><span class="sf-page-dots" aria-hidden="true">…</span><?php endif; ?>
                            <a class="sf-page-link" href="<?php echo sfEscape($buildCatalogUrl($totalPages)); ?>" aria-label="Página <?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                        <?php endif; ?>
                    </div>

                    <?php if ($page < $totalPages): ?>
                        <a class="sf-page-link sf-page-step" href="<?php echo sfEscape($buildCatalogUrl($page + 1)); ?>" rel="next"><span>Siguiente</span><?php echo sfIcon('arrow-right'); ?></a>
                    <?php else: ?>
                        <span class="sf-page-link sf-page-step is-disabled" aria-disabled="true"><span>Siguiente</span><?php echo sfIcon('arrow-right'); ?></span>
                    <?php endif; ?>
                </nav>
                <p class="sf-page-status">Página <?php echo $page; ?> de <?php echo $totalPages; ?></p>
            <?php endif; ?>
        <?php else: ?>
            <div class="sf-empty">
                <strong>No encontramos productos con estos filtros.</strong>
                <p>Prueba ampliando el rango de precio, quitando filtros o buscando con otra palabra.</p>
                <?php if ($activeFilters !== []): ?>
                    <a class="sf-button sf-button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Quitar todos los filtros</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
