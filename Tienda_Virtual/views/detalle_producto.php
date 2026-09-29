<?php
require_once __DIR__ . '/../includes/storefront_cart.php';

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
$relatedProducts = $product ? storefrontRelatedProducts((int) $product['id_categoria'], (int) $product['id_producto'], 3) : [];

$pageTitle = $product ? $product['nombre'] : 'Detalle del producto';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<?php if ($product): ?>
    <?php $productImage = storefrontImageUrl($product['url_imagen'] ?? ''); ?>
    <?php $hasPromotion = storefrontProductHasPromotion($product); ?>
    <?php $discountPercent = storefrontProductDiscountPercent($product); ?>
    <?php $hasStock = (int) ($product['stock_actual'] ?? 0) > 0; ?>
    <section class="product-detail-layout">
        <div class="product-gallery-panel">
            <div class="thumb-list">
                <?php if ($product['galeria'] !== []): ?>
                    <?php foreach ($product['galeria'] as $index => $image): ?>
                        <?php $galleryImage = storefrontImageUrl($image['url_imagen'] ?? ''); ?>
                        <?php $isActiveThumb = $index === 0; ?>
                        <button
                            class="thumb-item <?php echo $isActiveThumb ? 'is-active' : ''; ?>"
                            type="button"
                            data-product-thumb
                            data-gallery-src="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>"
                            data-gallery-alt="Vista de <?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="Ver imagen <?php echo $index + 1; ?> de <?php echo count($product['galeria']); ?>"
                            aria-pressed="<?php echo $isActiveThumb ? 'true' : 'false'; ?>"
                        >
                            <?php if ($galleryImage !== ''): ?>
                                <img src="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Vista de <?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <span>Img</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="thumb-item thumb-item-empty"><span>Sin</span></div>
                <?php endif; ?>
            </div>
            <div class="product-main-image">
                <?php if ($productImage !== ''): ?>
                    <img
                        src="<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-product-main-image
                    >
                <?php else: ?>
                    <div class="shop-card-placeholder large-placeholder">
                        <span><?php echo htmlspecialchars(storefrontInitial($product['categoria_nombre']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="product-info-panel">
            <h1 class="product-detail-title"><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="product-detail-meta">
                <span><?php echo htmlspecialchars($product['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span>SKU: <?php echo htmlspecialchars($product['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="product-price-box">
                <span><?php echo $hasPromotion ? htmlspecialchars((string) ($product['texto_promocion'] ?: 'Precio promocional'), ENT_QUOTES, 'UTF-8') : 'Precio'; ?></span>
                <strong><?php echo htmlspecialchars(appMoney((float) storefrontProductCurrentPrice($product)), ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if (!$hasStock): ?>
                    <div class="storefront-stock-badge">Proximamente</div>
                <?php endif; ?>
                <?php if ($hasPromotion): ?>
                    <?php if ($discountPercent !== null): ?>
                        <div class="storefront-discount-badge">-<?php echo $discountPercent; ?>% de descuento</div>
                    <?php endif; ?>
                    <div class="shop-price-original">Antes: <?php echo htmlspecialchars(appMoney((float) $product['precio_venta']), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <form method="post" action="" class="product-purchase-form">
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id_producto']; ?>">
                <div class="quantity-block">
                    <span>Cantidad:</span>
                    <div class="quantity-selector" data-quantity-selector>
                        <button type="button" data-quantity-decrease <?php echo !$hasStock ? 'disabled' : ''; ?>>-</button>
                        <input
                            type="number"
                            name="quantity"
                            value="1"
                            min="1"
                            max="<?php echo max(1, (int) $product['stock_actual']); ?>"
                            data-quantity-input
                            <?php echo !$hasStock ? 'disabled' : ''; ?>
                        >
                        <button type="button" data-quantity-increase <?php echo !$hasStock ? 'disabled' : ''; ?>>+</button>
                    </div>
                </div>
                <div class="product-note-box">
                    <strong><?php echo $hasStock ? 'Disponible para compra' : 'Disponible proximamente'; ?></strong>
                    <p><?php echo $hasStock ? 'Puedes añadir este producto al carrito y continuar con tu pedido.' : 'Este producto se mantiene visible para futuras ventas y volverá a estar disponible pronto.'; ?></p>
                </div>
                <div class="detail-actions">
                    <button class="button button-primary" type="submit" <?php echo !$hasStock ? 'disabled' : ''; ?>><?php echo $hasStock ? 'Añadir al carrito' : 'Proximamente'; ?></button>
                    <a class="button button-secondary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Seguir comprando</a>
                </div>
            </form>
        </aside>
    </section>

    <section class="detail-lower-grid">
        <div class="page-section product-description-panel">
            <h2 class="section-title">Descripcion</h2>
            <p><?php echo nl2br(htmlspecialchars((string) ($product['descripcion'] ?: 'Agrega una descripcion comercial completa para este producto.'), ENT_QUOTES, 'UTF-8')); ?></p>
        </div>
        <div class="page-section product-extra-panel">
            <h2 class="section-title">Informacion del producto</h2>
            <ul class="detail-list">
                <li>Categoria: <?php echo htmlspecialchars($product['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Codigo: <?php echo htmlspecialchars($product['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Disponibilidad: <?php echo $hasStock ? 'Disponible' : 'Proximamente'; ?></li>
            </ul>
        </div>
    </section>

    <?php if ($relatedProducts !== []): ?>
        <section class="store-collection compact-top">
            <div class="section-heading section-heading-inline">
                <div>
                    <h2 class="section-title">Te recomendamos estos productos</h2>
                </div>
            </div>
            <div class="mobile-scroll-hint">Desliza para ver más</div>
            <div class="collection-grid catalog-grid">
                <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <?php $relatedImage = storefrontImageUrl($relatedProduct['url_imagen'] ?? ''); ?>
                    <?php $relatedHasPromotion = storefrontProductHasPromotion($relatedProduct); ?>
                    <?php $relatedDiscountPercent = storefrontProductDiscountPercent($relatedProduct); ?>
                    <article class="shop-card">
                        <a class="shop-card-media" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $relatedProduct['id_producto']; ?>">
                            <?php if ($relatedImage !== ''): ?>
                                <img src="<?php echo htmlspecialchars($relatedImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($relatedProduct['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <div class="shop-card-placeholder">
                                    <span><?php echo htmlspecialchars(storefrontInitial($relatedProduct['categoria_nombre']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="shop-card-body">
                            <span class="shop-card-sku">SKU: <?php echo htmlspecialchars($relatedProduct['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($relatedHasPromotion): ?>
                                <div class="storefront-promo-headline">
                                    <span class="storefront-promo-tag"><?php echo htmlspecialchars((string) ($relatedProduct['texto_promocion'] ?: 'Oferta'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($relatedDiscountPercent !== null): ?>
                                        <span class="storefront-discount-badge">-<?php echo $relatedDiscountPercent; ?>%</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <h3><a href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $relatedProduct['id_producto']; ?>"><?php echo htmlspecialchars($relatedProduct['nombre'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                            <div class="shop-card-footer">
                                <div class="storefront-price-stack">
                                    <span class="shop-price"><?php echo htmlspecialchars(appMoney((float) storefrontProductCurrentPrice($relatedProduct)), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($relatedHasPromotion): ?>
                                        <span class="shop-price-original"><?php echo htmlspecialchars(appMoney((float) $relatedProduct['precio_venta']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="inline-link" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $relatedProduct['id_producto']; ?>">Ver detalle</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php else: ?>
    <section class="page-section empty-state">
        <span class="eyebrow eyebrow-dark">Sin producto activo</span>
        <strong>No hay un producto disponible para renderizar el detalle todavía.</strong>
        <p>Cuando registres al menos un producto activo, esta página tomará los datos reales, incluyendo categoría, precio, stock e imágenes.</p>
        <div class="action-row">
            <a class="button button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ir al catalogo</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Ver modulo productos</a>
        </div>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
