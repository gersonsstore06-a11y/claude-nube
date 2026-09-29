<?php
require_once __DIR__ . '/storefront_data.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', appBaseUrl());
}

/**
 * Componentes de interfaz compartidos por las vistas de la tienda.
 * Solo presentan datos: no contienen lógica de negocio.
 */

function sfEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sfIcon(string $name, string $class = 'sf-icon'): string
{
    static $paths = [
        'bag' => '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'store' => '<path d="M4 9 5.5 4h13L20 9M4 9v11h16V9M4 9h16M9 20v-6h6v6"/>',
        'chat' => '<path d="M4 19.5 5.3 16A8 8 0 1 1 8 18.7L4 19.5Z"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
    ];

    $svg = $paths[$name] ?? '';

    return '<svg class="' . sfEscape($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

function sfProductUrl(array $product): string
{
    return BASE_URL . '/views/detalle_producto.php?id=' . (int) ($product['id_producto'] ?? 0);
}

function sfCategoryUrl(int $categoryId): string
{
    return BASE_URL . '/views/catalogo.php?categoria=' . $categoryId;
}

function sfWhatsappUrl(string $message = ''): ?string
{
    if (!function_exists('storefrontCartWhatsappPhone')) {
        return null;
    }

    $phone = storefrontCartWhatsappPhone();

    if ($phone === '') {
        return null;
    }

    return 'https://wa.me/' . rawurlencode($phone) . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

/**
 * Tarjeta de producto única para portada, catálogo y relacionados.
 *
 * Opciones:
 *  - eager (bool): carga inmediata de la imagen (solo para lo visible al entrar).
 *  - heading (string): etiqueta del título (h2/h3) para mantener la jerarquía.
 *  - index (int): posición, usada para escalonar la animación de entrada.
 */
function sfRenderProductCard(array $product, array $options = []): string
{
    $eager = (bool) ($options['eager'] ?? false);
    $heading = (string) ($options['heading'] ?? 'h3');
    $heading = in_array($heading, ['h2', 'h3', 'h4'], true) ? $heading : 'h3';
    $index = (int) ($options['index'] ?? 0);

    $productId = (int) ($product['id_producto'] ?? 0);
    $name = (string) ($product['nombre'] ?? '');
    $url = sfProductUrl($product);
    $image = storefrontImageUrl($product['url_imagen'] ?? '');
    $hasPromotion = storefrontProductHasPromotion($product);
    $discount = storefrontProductDiscountPercent($product);
    $stock = max(0, (int) ($product['stock_actual'] ?? 0));
    $hasStock = $stock > 0;
    $promoText = trim((string) ($product['texto_promocion'] ?? ''));

    ob_start();
    ?>
    <article class="sf-card<?php echo $hasStock ? '' : ' is-soldout'; ?>" data-reveal style="--i: <?php echo min($index, 7); ?>">
        <a class="sf-card-media" href="<?php echo sfEscape($url); ?>" tabindex="-1" aria-hidden="true">
            <?php if ($image !== ''): ?>
                <img src="<?php echo sfEscape($image); ?>" alt="<?php echo sfEscape($name); ?>" <?php echo $eager ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async" width="600" height="600">
            <?php else: ?>
                <span class="sf-card-placeholder"><?php echo sfEscape(storefrontInitial($product['categoria_nombre'] ?? $name)); ?></span>
            <?php endif; ?>
            <span class="sf-card-badges">
                <?php if ($discount !== null): ?>
                    <span class="sf-badge sf-badge-sale">-<?php echo $discount; ?>%</span>
                <?php endif; ?>
                <?php if (!$hasStock): ?>
                    <span class="sf-badge sf-badge-muted">Próximamente</span>
                <?php endif; ?>
            </span>
        </a>
        <div class="sf-card-body">
            <?php if (!empty($product['categoria_nombre'])): ?>
                <span class="sf-card-eyebrow"><?php echo sfEscape($product['categoria_nombre']); ?></span>
            <?php endif; ?>
            <<?php echo $heading; ?> class="sf-card-title">
                <a href="<?php echo sfEscape($url); ?>"><?php echo sfEscape($name); ?></a>
            </<?php echo $heading; ?>>
            <?php if ($hasPromotion && $promoText !== ''): ?>
                <span class="sf-card-promo"><?php echo sfIcon('tag', 'sf-icon sf-icon-xs'); ?><?php echo sfEscape($promoText); ?></span>
            <?php endif; ?>
            <div class="sf-card-footer">
                <div class="sf-price">
                    <span class="sf-price-current<?php echo $hasPromotion ? ' is-sale' : ''; ?>"><?php echo sfEscape(appMoney(storefrontProductCurrentPrice($product))); ?></span>
                    <?php if ($hasPromotion): ?>
                        <s class="sf-price-old"><span class="sf-visually-hidden">Antes: </span><?php echo sfEscape(appMoney((float) $product['precio_venta'])); ?></s>
                    <?php endif; ?>
                </div>
                <?php if ($hasStock): ?>
                    <form class="sf-card-cart" method="post" action="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo $productId; ?>" data-add-to-cart>
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button class="sf-icon-button sf-card-add" type="submit" aria-label="Añadir <?php echo sfEscape($name); ?> al carrito">
                            <?php echo sfIcon('plus'); ?>
                            <span class="sf-card-add-label">Añadir</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <span class="sf-stock <?php echo $hasStock ? ($stock <= 3 ? 'is-low' : 'is-ok') : 'is-out'; ?>">
                <?php if (!$hasStock): ?>
                    Sin stock por ahora
                <?php elseif ($stock <= 3): ?>
                    Quedan <?php echo $stock; ?> unidad<?php echo $stock === 1 ? '' : 'es'; ?>
                <?php else: ?>
                    Disponible
                <?php endif; ?>
            </span>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}
