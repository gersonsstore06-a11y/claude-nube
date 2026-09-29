<?php
require_once __DIR__ . '/../includes/storefront_data.php';

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
    $activeFilters[] = ['label' => 'Busqueda: ' . $search, 'remove' => 'buscar'];
}
if ($selectedCategory !== null) {
    foreach ($categories as $cat) {
        if ((int) $cat['id_categoria'] === $selectedCategory) {
            $activeFilters[] = ['label' => 'Categoria: ' . $cat['nombre'], 'remove' => 'categoria'];
            break;
        }
    }
}
if ($filterPriceMin !== null) {
    $activeFilters[] = ['label' => 'Precio min: ' . appMoney($filterPriceMin, 0), 'remove' => 'precio_min'];
}
if ($filterPriceMax !== null) {
    $activeFilters[] = ['label' => 'Precio max: ' . appMoney($filterPriceMax, 0), 'remove' => 'precio_max'];
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
    'recent' => 'Mas recientes',
    'price_asc' => 'Precio: menor a mayor',
    'price_desc' => 'Precio: mayor a menor',
    'name_asc' => 'Nombre A-Z',
    'stock_desc' => 'Mas stock',
];

$pageTitle = 'Catalogo';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<section class="page-section catalog-hero">
    <div class="catalog-hero-copy">
        <span class="section-label">Catalogo</span>
        <h1 class="page-title catalog-title"><?php echo htmlspecialchars($selectedCategoryName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="lead">
            <?php if ($search !== ''): ?>
                Resultados para <strong><?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?></strong>.
            <?php else: ?>
                Explora y filtra todos nuestros productos.
            <?php endif; ?>
        </p>
    </div>
    <form class="catalog-filters" action="" method="get" data-catalog-form>
        <input type="search" name="buscar" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar producto o codigo...">
        <select name="categoria">
            <option value="">Todas las categorias</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int) $category['id_categoria']; ?>" <?php echo (int) $category['id_categoria'] === $selectedCategory ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="orden" data-catalog-auto-submit>
            <?php foreach ($sortLabels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $sort === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="por_pagina" data-catalog-auto-submit>
            <?php foreach ($allowedPerPage as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo $size; ?> por pagina</option>
            <?php endforeach; ?>
        </select>
        <button class="button button-primary" type="submit">Buscar</button>
    </form>
</section>

<div class="catalog-layout">

    <!-- =============== SIDEBAR =============== -->
    <aside class="catalog-sidebar" data-catalog-sidebar>
        <div class="sidebar-panel">
            <div class="sidebar-panel-head">
                <strong>Filtros</strong>
                <?php if ($activeFilters !== []): ?>
                    <a class="sidebar-clear-all" href="<?php echo BASE_URL; ?>/views/catalogo.php">Limpiar todo</a>
                <?php endif; ?>
            </div>

            <form class="sidebar-form" action="" method="get" data-catalog-sidebar-form>
                <?php if ($search !== ''): ?><input type="hidden" name="buscar" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <?php if ($selectedCategory !== null && $selectedCategory > 0): ?><input type="hidden" name="categoria" value="<?php echo $selectedCategory; ?>"><?php endif; ?>
                <?php if ($perPage !== 24): ?><input type="hidden" name="por_pagina" value="<?php echo $perPage; ?>"><?php endif; ?>
                <?php if ($sort !== 'recent'): ?><input type="hidden" name="orden" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>

                <!-- Categorias -->
                <div class="sidebar-group">
                    <span class="sidebar-group-label">Categoria</span>
                    <div class="sidebar-chip-list">
                        <a class="sidebar-chip <?php echo $selectedCategory === null ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars($buildCatalogUrl(1, [], ['categoria']), ENT_QUOTES, 'UTF-8'); ?>">Todo</a>
                        <?php foreach ($categories as $category): ?>
                            <a class="sidebar-chip <?php echo (int) $category['id_categoria'] === $selectedCategory ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars($buildCatalogUrl(1, ['categoria' => (int) $category['id_categoria']], []), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                <small class="sidebar-chip-count"><?php echo (int) $category['product_count']; ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Precio -->
                <div class="sidebar-group">
                    <span class="sidebar-group-label">Rango de precio</span>
                    <div class="sidebar-price-range">
                        <div class="sidebar-price-inputs">
                            <div class="sidebar-price-field">
                                <span class="sidebar-price-prefix">L/</span>
                                <input type="number" name="precio_min" min="0" step="1" placeholder="Min" value="<?php echo $filterPriceMin !== null ? number_format($filterPriceMin, 0, '.', '') : ''; ?>" data-catalog-sidebar-apply>
                            </div>
                            <span class="sidebar-price-sep">-</span>
                            <div class="sidebar-price-field">
                                <span class="sidebar-price-prefix">L/</span>
                                <input type="number" name="precio_max" min="0" step="1" placeholder="Max" value="<?php echo $filterPriceMax !== null ? number_format($filterPriceMax, 0, '.', '') : ''; ?>" data-catalog-sidebar-apply>
                            </div>
                        </div>
                        <?php if ($priceRange['min'] > 0 || $priceRange['max'] > 0): ?>
                            <small class="sidebar-price-hint">Rango disponible: <?php echo htmlspecialchars(appMoney((float) $priceRange['min'], 0), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(appMoney((float) $priceRange['max'], 0), ENT_QUOTES, 'UTF-8'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Disponibilidad -->
                <div class="sidebar-group">
                    <span class="sidebar-group-label">Disponibilidad</span>
                    <label class="sidebar-toggle-label">
                        <input type="checkbox" name="en_stock" value="1" <?php echo $filterInStock ? 'checked' : ''; ?> data-catalog-sidebar-checkbox>
                        <span class="sidebar-toggle-text">Solo productos con stock</span>
                    </label>
                    <label class="sidebar-toggle-label">
                        <input type="checkbox" name="en_oferta" value="1" <?php echo $filterOnSale ? 'checked' : ''; ?> data-catalog-sidebar-checkbox>
                        <span class="sidebar-toggle-text">Solo en oferta</span>
                    </label>
                </div>

                <button type="submit" class="button button-primary sidebar-apply-button">Aplicar filtros</button>
            </form>
        </div>
    </aside>

    <!-- =============== CONTENIDO PRINCIPAL =============== -->
    <div class="catalog-main">

        <!-- Chips de filtros activos -->
        <?php if ($activeFilters !== []): ?>
            <div class="catalog-active-filters" role="region" aria-label="Filtros activos">
                <span class="catalog-active-label">Filtros activos:</span>
                <?php foreach ($activeFilters as $af): ?>
                    <a class="catalog-filter-chip" href="<?php echo htmlspecialchars($buildCatalogUrl(1, [], [$af['remove'], 'pagina']), ENT_QUOTES, 'UTF-8'); ?>" title="Quitar filtro">
                        <?php echo htmlspecialchars($af['label'], ENT_QUOTES, 'UTF-8'); ?> &times;
                    </a>
                <?php endforeach; ?>
                <a class="catalog-clear-link" href="<?php echo BASE_URL; ?>/views/catalogo.php">Limpiar todo</a>
            </div>
        <?php endif; ?>

        <?php if ($products !== []): ?>
            <div class="catalog-results-head">
                <h2 class="catalog-results-title">
                    <?php if ($search !== ''): ?>
                        Resultados para "<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php else: ?>
                        Productos encontrados
                    <?php endif; ?>
                </h2>
                <div class="catalog-results-meta">
                    <span class="catalog-summary">Mostrando <?php echo $showFrom; ?>-<?php echo $showTo; ?> de <?php echo $totalProducts; ?></span>
                </div>
            </div>
            <div class="mobile-scroll-hint">Desliza para ver más</div>
            <div class="collection-grid catalog-grid" data-catalog-grid>
                <?php foreach ($products as $product): ?>
                    <?php $productImage = storefrontImageUrl($product['url_imagen'] ?? ''); ?>
                    <?php $hasPromotion = storefrontProductHasPromotion($product); ?>
                    <?php $discountPercent = storefrontProductDiscountPercent($product); ?>
                    <?php $hasStock = (int) ($product['stock_actual'] ?? 0) > 0; ?>
                    <article class="shop-card" data-reveal-card>
                        <a class="shop-card-media" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>">
                            <?php if ($productImage !== ''): ?>
                                <img src="<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <div class="shop-card-placeholder">
                                    <span><?php echo htmlspecialchars(storefrontInitial($product['categoria_nombre']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="shop-card-body">
                            <span class="shop-card-sku">SKU: <?php echo htmlspecialchars($product['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($hasPromotion): ?>
                                <div class="storefront-promo-headline">
                                    <span class="storefront-promo-tag"><?php echo htmlspecialchars((string) ($product['texto_promocion'] ?: 'Oferta'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($discountPercent !== null): ?>
                                        <span class="storefront-discount-badge">-<?php echo $discountPercent; ?>%</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$hasStock): ?>
                                <div class="storefront-stock-badge storefront-stock-badge-inline">Proximamente</div>
                            <?php endif; ?>
                            <h3><a href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>" title="<?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                            <p class="shop-card-description" title="<?php echo htmlspecialchars((string) ($product['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(storefrontExcerpt($product['descripcion'], 96), ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="shop-card-footer">
                                <div class="storefront-price-stack">
                                    <span class="shop-price"><?php echo htmlspecialchars(appMoney((float) storefrontProductCurrentPrice($product)), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($hasPromotion): ?>
                                        <span class="shop-price-original"><?php echo htmlspecialchars(appMoney((float) $product['precio_venta']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="inline-link" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>">Ver detalle</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ===== PAGINACION MEJORADA ===== -->
            <?php if ($totalPages > 1): ?>
                <nav class="catalog-pagination" aria-label="Paginacion del catalogo">
                    <div class="catalog-pagination-controls">
                        <a class="catalog-page-link <?php echo $page <= 1 ? 'is-disabled' : ''; ?>"
                           href="<?php echo $page <= 1 ? '#' : htmlspecialchars($buildCatalogUrl($page - 1), ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>&#8592; Anterior</a>

                        <?php
                        $windowSize = 2;
                        $startPage = max(1, $page - $windowSize);
                        $endPage = min($totalPages, $page + $windowSize);
                        ?>
                        <?php if ($startPage > 1): ?>
                            <a class="catalog-page-link" href="<?php echo htmlspecialchars($buildCatalogUrl(1), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                            <?php if ($startPage > 2): ?><span class="catalog-page-dots">...</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a class="catalog-page-link <?php echo $i === $page ? 'is-active' : ''; ?>"
                               href="<?php echo htmlspecialchars($buildCatalogUrl($i), ENT_QUOTES, 'UTF-8'); ?>"
                               <?php echo $i === $page ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><span class="catalog-page-dots">...</span><?php endif; ?>
                            <a class="catalog-page-link" href="<?php echo htmlspecialchars($buildCatalogUrl($totalPages), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $totalPages; ?></a>
                        <?php endif; ?>

                        <a class="catalog-page-link <?php echo $page >= $totalPages ? 'is-disabled' : ''; ?>"
                           href="<?php echo $page >= $totalPages ? '#' : htmlspecialchars($buildCatalogUrl($page + 1), ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Siguiente &#8594;</a>
                    </div>

                    <div class="catalog-pagination-jump">
                        <span class="catalog-page-label">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                        <form class="catalog-jump-form" action="" method="get" data-catalog-jump-form>
                            <?php if ($search !== ''): ?><input type="hidden" name="buscar" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                            <?php if ($selectedCategory !== null && $selectedCategory > 0): ?><input type="hidden" name="categoria" value="<?php echo $selectedCategory; ?>"><?php endif; ?>
                            <?php if ($perPage !== 24): ?><input type="hidden" name="por_pagina" value="<?php echo $perPage; ?>"><?php endif; ?>
                            <?php if ($sort !== 'recent'): ?><input type="hidden" name="orden" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                            <?php if ($filterPriceMin !== null): ?><input type="hidden" name="precio_min" value="<?php echo $filterPriceMin; ?>"><?php endif; ?>
                            <?php if ($filterPriceMax !== null): ?><input type="hidden" name="precio_max" value="<?php echo $filterPriceMax; ?>"><?php endif; ?>
                            <?php if ($filterInStock): ?><input type="hidden" name="en_stock" value="1"><?php endif; ?>
                            <?php if ($filterOnSale): ?><input type="hidden" name="en_oferta" value="1"><?php endif; ?>
                            <input class="catalog-jump-input" type="number" name="pagina" min="1" max="<?php echo $totalPages; ?>" value="<?php echo $page; ?>" aria-label="Ir a pagina">
                            <button class="catalog-jump-button" type="submit">Ir</button>
                        </form>
                    </div>
                </nav>
            <?php else: ?>
                <p class="catalog-page-label" style="text-align:center;margin-top:14px;">Pagina 1 de 1</p>
            <?php endif; ?>

        <?php else: ?>
            <section class="store-collection compact-top">
                <div class="collection-empty">
                    <strong>No hay productos para los filtros seleccionados.</strong>
                    <p>Prueba ampliando el rango de precio, quita filtros activos o busca con otro término.</p>
                    <?php if ($activeFilters !== []): ?>
                        <a class="button button-secondary" href="<?php echo BASE_URL; ?>/views/catalogo.php" style="margin-top:14px;display:inline-flex;">Quitar todos los filtros</a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

    </div><!-- /.catalog-main -->
</div><!-- /.catalog-layout -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
