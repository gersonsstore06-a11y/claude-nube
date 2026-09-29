<?php
require_once __DIR__ . '/../includes/storefront_cart.php';
require_once __DIR__ . '/../includes/storefront_ui.php';

$requestedProductId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$requestedProductId = $requestedProductId ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedProductId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: 0;
    $requestedQuantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
    $result = storefrontCartAdd($postedProductId, $requestedQuantity);
    storefrontCartSetFlash($result['ok'] ? 'success' : 'error', $result['message']);
    storefrontCartRedirect(adminAuthBaseUrl() . '/views/carrito.php');
}

$product = storefrontProduct($requestedProductId);
$relatedProducts = $product ? storefrontRelatedProducts((int) $product['id_categoria'], (int) $product['id_producto'], 4) : [];

$pageTitle = $product ? $product['nombre'] : 'Producto no disponible';
if ($product) {
    $pageDescription = storefrontExcerpt($product['descripcion'] ?? '', 155);
    if (trim((string) ($product['descripcion'] ?? '')) === '') {
        $pageDescription = $product['nombre'] . ' en Gerson\'s Store por ' . appMoney(storefrontProductCurrentPrice($product)) . '. Pide por WhatsApp.';
    }
    if (trim((string) ($product['url_imagen'] ?? '')) !== '') {
        $pageImage = storefrontImageUrl($product['url_imagen']);
    }
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<?php if ($product): ?>
    <?php
    $productImage = storefrontImageUrl($product['url_imagen'] ?? '');
    $hasPromotion = storefrontProductHasPromotion($product);
    $discountPercent = storefrontProductDiscountPercent($product);
    $stock = max(0, (int) ($product['stock_actual'] ?? 0));
    $hasStock = $stock > 0;
    $gallery = array_values(array_filter(
        $product['galeria'],
        static fn(array $image): bool => trim((string) ($image['url_imagen'] ?? '')) !== ''
    ));
    $productWhatsappUrl = sfWhatsappUrl('Hola, me interesa el producto "' . $product['nombre'] . '" (' . $product['codigo_producto'] . ').');
    ?>
    <div class="sf-page-head sf-page-head-tight">
        <nav class="sf-breadcrumb" aria-label="Ruta de navegación">
            <ol>
                <li><a href="<?php echo BASE_URL; ?>/index.php">Inicio</a></li>
                <li><a href="<?php echo sfEscape(sfCategoryUrl((int) $product['id_categoria'])); ?>"><?php echo sfEscape($product['categoria_nombre']); ?></a></li>
                <li aria-current="page"><?php echo sfEscape($product['nombre']); ?></li>
            </ol>
        </nav>
    </div>

    <article class="sf-product">
        <div class="sf-gallery">
            <div class="sf-gallery-main">
                <?php if ($productImage !== ''): ?>
                    <img
                        src="<?php echo sfEscape($productImage); ?>"
                        alt="<?php echo sfEscape($product['nombre']); ?>"
                        fetchpriority="high"
                        decoding="async"
                        width="900"
                        height="900"
                        data-product-main-image
                    >
                <?php else: ?>
                    <span class="sf-card-placeholder"><?php echo sfEscape(storefrontInitial($product['categoria_nombre'])); ?></span>
                <?php endif; ?>
                <?php if ($discountPercent !== null): ?>
                    <span class="sf-badge sf-badge-sale sf-gallery-badge">-<?php echo $discountPercent; ?>%</span>
                <?php endif; ?>
            </div>
            <?php if (count($gallery) > 1): ?>
                <div class="sf-gallery-thumbs" role="group" aria-label="Imágenes del producto">
                    <?php foreach ($gallery as $index => $image): ?>
                        <?php $galleryImage = storefrontImageUrl($image['url_imagen'] ?? ''); ?>
                        <button
                            class="sf-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>"
                            type="button"
                            data-product-thumb
                            data-gallery-src="<?php echo sfEscape($galleryImage); ?>"
                            data-gallery-alt="<?php echo sfEscape($product['nombre']); ?>, imagen <?php echo $index + 1; ?>"
                            aria-label="Ver imagen <?php echo $index + 1; ?> de <?php echo count($gallery); ?>"
                            aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        >
                            <img src="<?php echo sfEscape($galleryImage); ?>" alt="" loading="lazy" decoding="async" width="120" height="120">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sf-product-info">
            <a class="sf-eyebrow sf-eyebrow-link" href="<?php echo sfEscape(sfCategoryUrl((int) $product['id_categoria'])); ?>"><?php echo sfEscape($product['categoria_nombre']); ?></a>
            <h1 class="sf-product-title"><?php echo sfEscape($product['nombre']); ?></h1>
            <span class="sf-product-sku">SKU: <?php echo sfEscape($product['codigo_producto']); ?></span>

            <div class="sf-product-price">
                <div class="sf-price sf-price-lg">
                    <span class="sf-price-current<?php echo $hasPromotion ? ' is-sale' : ''; ?>"><?php echo sfEscape(appMoney(storefrontProductCurrentPrice($product))); ?></span>
                    <?php if ($hasPromotion): ?>
                        <s class="sf-price-old"><span class="sf-visually-hidden">Antes: </span><?php echo sfEscape(appMoney((float) $product['precio_venta'])); ?></s>
                    <?php endif; ?>
                </div>
                <?php if ($hasPromotion): ?>
                    <span class="sf-product-promo"><?php echo sfIcon('tag', 'sf-icon sf-icon-xs'); ?><?php echo sfEscape((string) ($product['texto_promocion'] ?: 'Precio promocional')); ?><?php if ($discountPercent !== null): ?> · Ahorras <?php echo $discountPercent; ?>%<?php endif; ?></span>
                <?php endif; ?>
            </div>

            <p class="sf-stock sf-stock-lg <?php echo $hasStock ? ($stock <= 3 ? 'is-low' : 'is-ok') : 'is-out'; ?>">
                <?php if (!$hasStock): ?>
                    Sin stock por ahora · volverá pronto
                <?php elseif ($stock <= 3): ?>
                    ¡Últimas unidades! Quedan <?php echo $stock; ?>
                <?php else: ?>
                    Disponible para pedido
                <?php endif; ?>
            </p>

            <form method="post" action="" class="sf-buy" data-add-to-cart data-product-name="<?php echo sfEscape($product['nombre']); ?>">
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id_producto']; ?>">
                <div class="sf-qty" data-quantity-selector>
                    <button type="button" data-quantity-decrease aria-label="Restar una unidad" <?php echo !$hasStock ? 'disabled' : ''; ?>><?php echo sfIcon('minus'); ?></button>
                    <input
                        type="number"
                        name="quantity"
                        value="1"
                        min="1"
                        max="<?php echo max(1, $stock); ?>"
                        inputmode="numeric"
                        aria-label="Cantidad"
                        data-quantity-input
                        <?php echo !$hasStock ? 'disabled' : ''; ?>
                    >
                    <button type="button" data-quantity-increase aria-label="Sumar una unidad" <?php echo !$hasStock ? 'disabled' : ''; ?>><?php echo sfIcon('plus'); ?></button>
                </div>
                <button class="sf-button sf-button-primary sf-button-lg sf-buy-button" type="submit" <?php echo !$hasStock ? 'disabled' : ''; ?>>
                    <?php echo sfIcon('bag'); ?><span data-button-label><?php echo $hasStock ? 'Añadir al carrito' : 'Próximamente'; ?></span>
                </button>
            </form>

            <ul class="sf-product-perks">
                <li><?php echo sfIcon('chat'); ?><span><strong>Pedido por WhatsApp.</strong> Confirmas detalles y entrega directamente con la tienda.</span></li>
                <li><?php echo sfIcon('truck'); ?><span><strong>Entrega:</strong> <?php echo sfEscape(implode(', ', storefrontCartDeliveryMethods())); ?>.</span></li>
            </ul>

            <div class="sf-product-links">
                <a class="sf-link-arrow" href="<?php echo sfEscape(sfCategoryUrl((int) $product['id_categoria'])); ?>"><?php echo sfIcon('arrow-left'); ?>Seguir viendo <?php echo sfEscape($product['categoria_nombre']); ?></a>
                <?php if ($productWhatsappUrl): ?>
                    <a class="sf-link-arrow" href="<?php echo sfEscape($productWhatsappUrl); ?>" target="_blank" rel="noopener"><?php echo sfIcon('chat'); ?>Preguntar por este producto</a>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <section class="sf-section sf-product-details" aria-labelledby="sf-desc-title">
        <div class="sf-product-desc" data-reveal>
            <h2 class="sf-section-title sf-section-title-sm" id="sf-desc-title">Descripción</h2>
            <?php if (trim((string) ($product['descripcion'] ?? '')) !== ''): ?>
                <p><?php echo nl2br(sfEscape((string) $product['descripcion'])); ?></p>
            <?php else: ?>
                <p>Este producto todavía no tiene una descripción detallada. Si tienes dudas, escríbenos por WhatsApp.</p>
            <?php endif; ?>
        </div>
        <dl class="sf-spec" data-reveal style="--i: 1">
            <div><dt>Categoría</dt><dd><?php echo sfEscape($product['categoria_nombre']); ?></dd></div>
            <div><dt>Código</dt><dd><?php echo sfEscape($product['codigo_producto']); ?></dd></div>
            <div><dt>Disponibilidad</dt><dd><?php echo $hasStock ? 'Disponible' : 'Próximamente'; ?></dd></div>
        </dl>
    </section>

    <?php if ($relatedProducts !== []): ?>
        <section class="sf-section" aria-labelledby="sf-related-title">
            <div class="sf-section-head" data-reveal>
                <div>
                    <span class="sf-eyebrow">También en <?php echo sfEscape($product['categoria_nombre']); ?></span>
                    <h2 class="sf-section-title" id="sf-related-title">Te puede interesar</h2>
                </div>
                <a class="sf-link-arrow" href="<?php echo sfEscape(sfCategoryUrl((int) $product['id_categoria'])); ?>">Ver todo<?php echo sfIcon('arrow-right'); ?></a>
            </div>
            <div class="sf-product-grid">
                <?php foreach ($relatedProducts as $index => $relatedProduct): ?>
                    <?php echo sfRenderProductCard($relatedProduct, ['index' => $index]); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php else: ?>
    <section class="sf-section">
        <div class="sf-empty">
            <strong>Este producto no está disponible.</strong>
            <p>Puede que se haya retirado del catálogo o que el enlace no sea correcto.</p>
            <a class="sf-button sf-button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ir al catálogo</a>
        </div>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
