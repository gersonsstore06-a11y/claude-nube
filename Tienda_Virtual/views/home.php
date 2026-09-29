<?php
require_once __DIR__ . '/../includes/storefront_cart.php';
require_once __DIR__ . '/../includes/storefront_ui.php';

$categories = storefrontCategories();
$visibleCategories = array_values(array_filter(
    $categories,
    static fn(array $category): bool => (int) ($category['product_count'] ?? 0) > 0
));
$promoProducts = storefrontPromoProducts(6);
$newestProducts = storefrontProducts(null, '', 4);
$categorySections = storefrontCategorySections(8);

// Producto protagonista del hero: la promoción más reciente con imagen,
// si no hay, la novedad más reciente con imagen.
$heroProduct = null;
foreach (array_merge($promoProducts, $newestProducts) as $candidate) {
    if (trim((string) ($candidate['url_imagen'] ?? '')) !== '') {
        $heroProduct = $candidate;
        break;
    }
}
$heroProduct = $heroProduct ?? ($promoProducts[0] ?? $newestProducts[0] ?? null);
$heroId = $heroProduct ? (int) $heroProduct['id_producto'] : 0;
$heroSideProducts = array_slice(array_values(array_filter(
    $promoProducts,
    static fn(array $product): bool => (int) $product['id_producto'] !== $heroId
)), 0, 2);

$promoGrid = array_values(array_filter(
    $promoProducts,
    static fn(array $product): bool => (int) $product['id_producto'] !== $heroId
));

// Evita repetir en "Más por categoría" lo que ya se mostró más arriba.
$shownIds = [$heroId => true];
foreach (array_merge($newestProducts, $promoGrid) as $product) {
    $shownIds[(int) $product['id_producto']] = true;
}
$moreSections = [];
foreach ($categorySections as $section) {
    $remaining = array_values(array_filter(
        $section['products'],
        static fn(array $product): bool => !isset($shownIds[(int) $product['id_producto']])
    ));
    if ($remaining !== []) {
        $section['products'] = array_slice($remaining, 0, 4);
        $moreSections[] = $section;
    }
}

// Imagen representativa por categoría (primer producto con imagen).
$categoryImages = [];
foreach ($categorySections as $section) {
    foreach ($section['products'] as $product) {
        if (trim((string) ($product['url_imagen'] ?? '')) !== '') {
            $categoryImages[(int) $section['id_categoria']] = storefrontImageUrl($product['url_imagen']);
            break;
        }
    }
}

$totalProducts = array_sum(array_map(static fn(array $c): int => (int) ($c['product_count'] ?? 0), $categories));
$whatsappUrl = sfWhatsappUrl('Hola, estoy buscando un producto en Gerson\'s Store.');

$pageTitle = "Gerson's Store";
$pageDescription = 'Joyería, perfumes y regalos en Gerson\'s Store. Descubre novedades y ofertas, arma tu carrito y confirma tu pedido por WhatsApp.';
if ($heroProduct && trim((string) ($heroProduct['url_imagen'] ?? '')) !== '') {
    $pageImage = storefrontImageUrl($heroProduct['url_imagen']);
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<section class="sf-hero sf-container" aria-labelledby="sf-hero-title">
    <div class="sf-hero-copy">
        <span class="sf-eyebrow sf-intro" style="--d: 0">Joyería · Perfumes · Regalos</span>
        <h1 class="sf-hero-title sf-intro" id="sf-hero-title" style="--d: 1">Detalles que <em>se recuerdan</em>, elegidos para ti.</h1>
        <p class="sf-hero-lead sf-intro" style="--d: 2">Encuentra el regalo ideal o date un gusto. Arma tu carrito y confirma tu pedido directamente con la tienda por WhatsApp.</p>
        <div class="sf-hero-actions sf-intro" style="--d: 3">
            <a class="sf-button sf-button-primary sf-button-lg" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver catálogo<?php echo sfIcon('arrow-right'); ?></a>
            <?php if ($promoProducts !== []): ?>
                <a class="sf-button sf-button-ghost sf-button-lg" href="<?php echo BASE_URL; ?>/views/catalogo.php?en_oferta=1">Ver ofertas</a>
            <?php endif; ?>
        </div>
        <?php if ($totalProducts > 0): ?>
            <dl class="sf-hero-stats sf-intro" style="--d: 4">
                <div><dt>Productos</dt><dd><?php echo $totalProducts; ?></dd></div>
                <div><dt>Categorías</dt><dd><?php echo count($visibleCategories); ?></dd></div>
                <?php if ($promoProducts !== []): ?>
                    <div><dt>En oferta</dt><dd><?php echo count($promoProducts); ?><?php echo count($promoProducts) >= 6 ? '+' : ''; ?></dd></div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
    </div>

    <?php if ($heroProduct): ?>
        <?php
        $heroImage = storefrontImageUrl($heroProduct['url_imagen'] ?? '');
        $heroDiscount = storefrontProductDiscountPercent($heroProduct);
        $heroHasPromo = storefrontProductHasPromotion($heroProduct);
        ?>
        <div class="sf-hero-visual sf-intro" style="--d: 2">
            <a class="sf-hero-feature" href="<?php echo sfEscape(sfProductUrl($heroProduct)); ?>">
                <span class="sf-hero-feature-media" data-parallax>
                    <?php if ($heroImage !== ''): ?>
                        <img src="<?php echo sfEscape($heroImage); ?>" alt="<?php echo sfEscape($heroProduct['nombre']); ?>" fetchpriority="high" decoding="async" width="800" height="800">
                    <?php else: ?>
                        <span class="sf-card-placeholder"><?php echo sfEscape(storefrontInitial($heroProduct['nombre'])); ?></span>
                    <?php endif; ?>
                </span>
                <span class="sf-hero-feature-info">
                    <span class="sf-hero-feature-tag">
                        <?php echo $heroHasPromo ? sfEscape((string) ($heroProduct['texto_promocion'] ?: 'Oferta destacada')) : 'Novedad'; ?>
                        <?php if ($heroDiscount !== null): ?><b>-<?php echo $heroDiscount; ?>%</b><?php endif; ?>
                    </span>
                    <strong><?php echo sfEscape($heroProduct['nombre']); ?></strong>
                    <span class="sf-price">
                        <span class="sf-price-current<?php echo $heroHasPromo ? ' is-sale' : ''; ?>"><?php echo sfEscape(appMoney(storefrontProductCurrentPrice($heroProduct))); ?></span>
                        <?php if ($heroHasPromo): ?>
                            <s class="sf-price-old"><span class="sf-visually-hidden">Antes: </span><?php echo sfEscape(appMoney((float) $heroProduct['precio_venta'])); ?></s>
                        <?php endif; ?>
                    </span>
                </span>
            </a>
            <?php if ($heroSideProducts !== []): ?>
                <ul class="sf-hero-mini" aria-label="Otras ofertas">
                    <?php foreach ($heroSideProducts as $sideProduct): ?>
                        <?php $sideImage = storefrontImageUrl($sideProduct['url_imagen'] ?? ''); ?>
                        <li>
                            <a href="<?php echo sfEscape(sfProductUrl($sideProduct)); ?>">
                                <span class="sf-hero-mini-media">
                                    <?php if ($sideImage !== ''): ?>
                                        <img src="<?php echo sfEscape($sideImage); ?>" alt="" decoding="async" width="96" height="96">
                                    <?php else: ?>
                                        <span class="sf-card-placeholder"><?php echo sfEscape(storefrontInitial($sideProduct['nombre'])); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="sf-hero-mini-copy">
                                    <strong><?php echo sfEscape($sideProduct['nombre']); ?></strong>
                                    <span><?php echo sfEscape(appMoney(storefrontProductCurrentPrice($sideProduct))); ?>
                                        <?php $sideDiscount = storefrontProductDiscountPercent($sideProduct); ?>
                                        <?php if ($sideDiscount !== null): ?><b>-<?php echo $sideDiscount; ?>%</b><?php endif; ?>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($visibleCategories !== []): ?>
    <section class="sf-section sf-container" aria-labelledby="sf-cats-title">
        <div class="sf-section-head" data-reveal>
            <div>
                <span class="sf-eyebrow">Explora</span>
                <h2 class="sf-section-title" id="sf-cats-title">Compra por categoría</h2>
            </div>
            <a class="sf-link-arrow" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver todo el catálogo<?php echo sfIcon('arrow-right'); ?></a>
        </div>
        <div class="sf-category-grid">
            <?php foreach ($visibleCategories as $index => $category): ?>
                <?php $categoryImage = $categoryImages[(int) $category['id_categoria']] ?? ''; ?>
                <a class="sf-category-card" href="<?php echo sfEscape(sfCategoryUrl((int) $category['id_categoria'])); ?>" data-reveal style="--i: <?php echo min($index, 7); ?>">
                    <span class="sf-category-media">
                        <?php if ($categoryImage !== ''): ?>
                            <img src="<?php echo sfEscape($categoryImage); ?>" alt="" loading="lazy" decoding="async" width="400" height="400">
                        <?php else: ?>
                            <span class="sf-card-placeholder"><?php echo sfEscape(storefrontInitial($category['nombre'])); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="sf-category-copy">
                        <strong><?php echo sfEscape($category['nombre']); ?></strong>
                        <span><?php echo (int) $category['product_count']; ?> producto<?php echo (int) $category['product_count'] === 1 ? '' : 's'; ?></span>
                    </span>
                    <?php echo sfIcon('arrow-right', 'sf-icon sf-category-arrow'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($newestProducts !== []): ?>
    <section class="sf-section sf-container" aria-labelledby="sf-new-title">
        <div class="sf-section-head" data-reveal>
            <div>
                <span class="sf-eyebrow">Recién llegados</span>
                <h2 class="sf-section-title" id="sf-new-title">Novedades</h2>
            </div>
            <a class="sf-link-arrow" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver más<?php echo sfIcon('arrow-right'); ?></a>
        </div>
        <div class="sf-product-grid">
            <?php foreach ($newestProducts as $index => $product): ?>
                <?php echo sfRenderProductCard($product, ['index' => $index]); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($promoGrid !== []): ?>
    <section class="sf-section sf-section-band" aria-labelledby="sf-promo-title">
        <div class="sf-container">
            <div class="sf-section-head" data-reveal>
                <div>
                    <span class="sf-eyebrow sf-eyebrow-light">Por tiempo limitado</span>
                    <h2 class="sf-section-title" id="sf-promo-title">Ofertas del momento</h2>
                </div>
                <a class="sf-link-arrow sf-link-light" href="<?php echo BASE_URL; ?>/views/catalogo.php?en_oferta=1">Ver todas las ofertas<?php echo sfIcon('arrow-right'); ?></a>
            </div>
            <div class="sf-product-grid">
                <?php foreach (array_slice($promoGrid, 0, 4) as $index => $product): ?>
                    <?php echo sfRenderProductCard($product, ['index' => $index]); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="sf-section sf-container" aria-labelledby="sf-trust-title">
    <h2 class="sf-visually-hidden" id="sf-trust-title">Cómo comprar</h2>
    <ul class="sf-trust">
        <li data-reveal style="--i: 0">
            <span class="sf-trust-icon"><?php echo sfIcon('chat'); ?></span>
            <strong>Pedido por WhatsApp</strong>
            <p>Sin pagos en línea: envías tu carrito y confirmas los detalles directamente con la tienda.</p>
        </li>
        <li data-reveal style="--i: 1">
            <span class="sf-trust-icon"><?php echo sfIcon('truck'); ?></span>
            <strong>Entrega a tu medida</strong>
            <p><?php echo sfEscape(implode(', ', storefrontCartDeliveryMethods())); ?>.</p>
        </li>
        <li data-reveal style="--i: 2">
            <span class="sf-trust-icon"><?php echo sfIcon('tag'); ?></span>
            <strong>Precios claros</strong>
            <p>Precios en Lempiras con el descuento visible antes de agregar al carrito.</p>
        </li>
    </ul>
</section>

<?php foreach ($moreSections as $section): ?>
    <section class="sf-section sf-container" aria-labelledby="sf-cat-<?php echo (int) $section['id_categoria']; ?>">
        <div class="sf-section-head" data-reveal>
            <div>
                <span class="sf-eyebrow">Más en la tienda</span>
                <h2 class="sf-section-title" id="sf-cat-<?php echo (int) $section['id_categoria']; ?>"><?php echo sfEscape($section['nombre']); ?></h2>
                <?php if (trim((string) ($section['descripcion'] ?? '')) !== ''): ?>
                    <p class="sf-section-copy"><?php echo sfEscape(storefrontExcerpt($section['descripcion'], 120)); ?></p>
                <?php endif; ?>
            </div>
            <a class="sf-link-arrow" href="<?php echo sfEscape(sfCategoryUrl((int) $section['id_categoria'])); ?>">Ver <?php echo sfEscape($section['nombre']); ?><?php echo sfIcon('arrow-right'); ?></a>
        </div>
        <div class="sf-product-grid">
            <?php foreach ($section['products'] as $index => $product): ?>
                <?php echo sfRenderProductCard($product, ['index' => $index]); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php if ($totalProducts === 0): ?>
    <section class="sf-section sf-container">
        <div class="sf-empty">
            <strong>Aún no hay productos publicados.</strong>
            <p>Cuando se activen productos desde el panel de administración aparecerán aquí automáticamente.</p>
        </div>
    </section>
<?php endif; ?>

<section class="sf-section sf-container">
    <div class="sf-cta" data-reveal>
        <div>
            <h2 class="sf-cta-title">¿Buscas algo en particular?</h2>
            <p>Revisa el catálogo completo con filtros por precio y disponibilidad, o escríbenos y te ayudamos a elegir.</p>
        </div>
        <div class="sf-cta-actions">
            <a class="sf-button sf-button-light sf-button-lg" href="<?php echo BASE_URL; ?>/views/catalogo.php">Explorar catálogo</a>
            <?php if ($whatsappUrl): ?>
                <a class="sf-button sf-button-ghost-light sf-button-lg" href="<?php echo sfEscape($whatsappUrl); ?>" target="_blank" rel="noopener"><?php echo sfIcon('chat'); ?>WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
