<?php
require_once __DIR__ . '/../includes/storefront_data.php';

$categories = storefrontCategories();
$categorySections = storefrontCategorySections(6);
$promoProducts = storefrontPromoProducts(3);
$visibleCategories = array_values(array_filter(
    $categories,
    static fn(array $category): bool => (int) ($category['product_count'] ?? 0) > 0
));

$pageTitle = 'Inicio';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<?php if ($promoProducts !== []): ?>
    <section class="page-section storefront-promo-banner">
        <div class="storefront-promo-copy">
            <span class="section-label">Ofertas y promociones</span>
            <h1 class="page-title">Descubre productos destacados de la tienda.</h1>
            <p class="lead">Explora promociones del momento, novedades y productos recomendados para tu próxima compra.</p>
            <div class="storefront-promo-actions">
                <a class="button button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver catalogo</a>
                <a class="button button-secondary" href="<?php echo BASE_URL; ?>/views/carrito.php">Ir al carrito</a>
            </div>
        </div>
        <div class="storefront-promo-products">
            <?php foreach ($promoProducts as $promoProduct): ?>
                <?php $promoDiscount = storefrontProductDiscountPercent($promoProduct); ?>
                <a class="storefront-promo-card" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $promoProduct['id_producto']; ?>">
                    <div class="storefront-promo-headline">
                        <span class="storefront-promo-tag"><?php echo htmlspecialchars((string) ($promoProduct['texto_promocion'] ?: 'Promocion destacada'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($promoDiscount !== null): ?>
                            <span class="storefront-discount-badge">-<?php echo $promoDiscount; ?>%</span>
                        <?php endif; ?>
                    </div>
                    <strong><?php echo htmlspecialchars($promoProduct['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($promoProduct['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="storefront-price-stack">
                        <span class="shop-price"><?php echo htmlspecialchars(appMoney((float) storefrontProductCurrentPrice($promoProduct)), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="shop-price-original"><?php echo htmlspecialchars(appMoney((float) $promoProduct['precio_venta']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="page-section storefront-promo-banner">
        <div class="storefront-promo-copy storefront-promo-copy-full">
            <span class="section-label">Ofertas y promociones</span>
            <h1 class="page-title">Pronto verás descuentos y promociones especiales.</h1>
            <p class="lead">Activa promociones reales desde el panel administrativo de productos para destacarlas automáticamente en esta portada.</p>
            <div class="storefront-promo-actions">
                <a class="button button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver catalogo</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($categories !== []): ?>
    <section class="page-section quick-categories">
        <div class="section-head">
            <h2 class="section-title"><?php echo $visibleCategories !== [] ? 'Categorias con productos' : 'Categorias destacadas'; ?></h2>
            <a class="inline-link" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver catalogo completo</a>
        </div>
        <div class="category-chip-grid">
            <?php foreach (($visibleCategories !== [] ? $visibleCategories : $categories) as $category): ?>
                <a class="category-box" href="<?php echo BASE_URL; ?>/views/catalogo.php?categoria=<?php echo (int) $category['id_categoria']; ?>">
                    <strong><?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo (int) ($category['product_count'] ?? 0); ?> productos</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($categorySections !== []): ?>
    <?php foreach ($categorySections as $section): ?>
        <section class="store-collection">
            <div class="section-head">
                <div>
                    <h2 class="section-title"><?php echo htmlspecialchars($section['nombre'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="section-copy"><?php echo htmlspecialchars(storefrontExcerpt($section['descripcion'], 120), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a class="inline-link" href="<?php echo BASE_URL; ?>/views/catalogo.php?categoria=<?php echo (int) $section['id_categoria']; ?>">Ver todo</a>
            </div>

            <div class="mobile-scroll-hint">Desliza para ver más</div>
            <div class="collection-grid">
                <?php foreach ($section['products'] as $product): ?>
                    <?php $productImage = storefrontImageUrl($product['url_imagen'] ?? ''); ?>
                    <?php $hasPromotion = storefrontProductHasPromotion($product); ?>
                    <?php $discountPercent = storefrontProductDiscountPercent($product); ?>
                    <?php $hasStock = (int) ($product['stock_actual'] ?? 0) > 0; ?>
                    <article class="shop-card">
                        <a class="shop-card-media" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>">
                            <?php if ($productImage !== ''): ?>
                                <img src="<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <div class="shop-card-placeholder">
                                    <span><?php echo htmlspecialchars(storefrontInitial($section['nombre']), ENT_QUOTES, 'UTF-8'); ?></span>
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
                            <h3><a href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>"><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                            <div class="storefront-price-stack">
                                <span class="shop-price"><?php echo htmlspecialchars(appMoney((float) storefrontProductCurrentPrice($product)), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($hasPromotion): ?>
                                    <span class="shop-price-original"><?php echo htmlspecialchars(appMoney((float) $product['precio_venta']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php else: ?>
    <section class="store-collection">
        <div class="collection-empty">
            <strong>No hay productos activos publicados para mostrar en la portada.</strong>
            <p>Activa productos desde el panel de administración y así la tienda los mostrará agrupados por categoría apenas ingreses.</p>
        </div>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
