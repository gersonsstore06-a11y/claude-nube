<?php
$scriptsPath = __DIR__ . '/../public/js/app.js';
$scriptsVersion = is_file($scriptsPath) ? (string) filemtime($scriptsPath) : '1';
$isAdminFooter = defined('STORE_IS_ADMIN_AREA') && STORE_IS_ADMIN_AREA;
?>
<?php if ($isAdminFooter): ?>
</div>
<?php else: ?>
    <?php
    require_once __DIR__ . '/storefront_cart.php';
    require_once __DIR__ . '/storefront_ui.php';
    $footerCategories = array_values(array_filter(
        $menuCategoriesWithProducts ?? storefrontCategories(),
        static fn (array $category): bool => (int) ($category['product_count'] ?? 0) > 0
    ));
    $footerWhatsappUrl = sfWhatsappUrl('Hola, tengo una consulta sobre un producto de Gerson\'s Store.');
    $storeScriptPath = __DIR__ . '/../public/js/store.js';
    $storeScriptVersion = is_file($storeScriptPath) ? (string) filemtime($storeScriptPath) : '1';
    ?>
    </main>

    <footer class="sf-footer">
        <div class="sf-container sf-footer-grid">
            <div class="sf-footer-brand">
                <strong>Gerson's Store</strong>
                <p>Joyería, perfumes y regalos. Elige tus productos, arma tu carrito y confirma tu pedido por WhatsApp.</p>
                <?php if ($footerWhatsappUrl): ?>
                    <a class="sf-button sf-button-ghost-light" href="<?php echo sfEscape($footerWhatsappUrl); ?>" target="_blank" rel="noopener"><?php echo sfIcon('chat'); ?>Escríbenos por WhatsApp</a>
                <?php endif; ?>
            </div>
            <nav class="sf-footer-col" aria-label="Tienda">
                <span class="sf-footer-title">Tienda</span>
                <a href="<?php echo BASE_URL; ?>/index.php">Inicio</a>
                <a href="<?php echo BASE_URL; ?>/views/catalogo.php">Catálogo completo</a>
                <a href="<?php echo BASE_URL; ?>/views/catalogo.php?en_oferta=1">Ofertas</a>
                <a href="<?php echo BASE_URL; ?>/views/carrito.php">Carrito</a>
            </nav>
            <?php if ($footerCategories !== []): ?>
                <nav class="sf-footer-col" aria-label="Categorías">
                    <span class="sf-footer-title">Categorías</span>
                    <?php foreach ($footerCategories as $category): ?>
                        <a href="<?php echo sfEscape(sfCategoryUrl((int) $category['id_categoria'])); ?>"><?php echo sfEscape($category['nombre']); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="sf-footer-col">
                <span class="sf-footer-title">Entregas</span>
                <?php foreach (storefrontCartDeliveryMethods() as $methodLabel): ?>
                    <span><?php echo sfEscape($methodLabel); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sf-container sf-footer-bottom">
            <span>&copy; <span data-year><?php echo date('Y'); ?></span> Gerson's Store</span>
            <a href="<?php echo BASE_URL; ?>/views/login.php">Acceso administrativo</a>
        </div>
    </footer>

    <div class="sf-drawer sf-drawer-right" id="sf-cart-drawer" role="dialog" aria-modal="true" aria-labelledby="sf-cart-title" hidden data-drawer="cart">
        <div class="sf-drawer-overlay" data-drawer-close></div>
        <div class="sf-drawer-panel" tabindex="-1">
            <div class="sf-drawer-head">
                <strong id="sf-cart-title">Tu carrito <span class="sf-drawer-count" data-cart-count-text></span></strong>
                <button class="sf-icon-button" type="button" aria-label="Cerrar carrito" data-drawer-close><?php echo sfIcon('close'); ?></button>
            </div>
            <div class="sf-drawer-body" data-cart-body aria-live="polite">
                <div class="sf-cart-loading" role="status"><span class="sf-spinner" aria-hidden="true"></span>Cargando carrito…</div>
            </div>
            <div class="sf-drawer-foot" data-cart-foot hidden>
                <div class="sf-drawer-subtotal">
                    <span>Subtotal</span>
                    <strong data-cart-subtotal></strong>
                </div>
                <p class="sf-drawer-note">El pedido se confirma por WhatsApp desde el carrito.</p>
                <a class="sf-button sf-button-primary sf-button-block" href="<?php echo BASE_URL; ?>/views/carrito.php">Ver carrito y hacer pedido<?php echo sfIcon('arrow-right'); ?></a>
                <button class="sf-button sf-button-ghost sf-button-block" type="button" data-drawer-close>Seguir comprando</button>
            </div>
        </div>
    </div>

    <div class="sf-toast-region" aria-live="polite" aria-atomic="true" data-toast-region></div>

    <script>window.SF_CONFIG = <?php echo json_encode([
        'baseUrl' => BASE_URL,
        'cartApi' => BASE_URL . '/views/carrito_api.php',
        'cartUrl' => BASE_URL . '/views/carrito.php',
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
    <script src="<?php echo BASE_URL; ?>/public/js/store.js?v=<?php echo urlencode($storeScriptVersion); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo BASE_URL; ?>/public/js/app.js?v=<?php echo urlencode($scriptsVersion); ?>"></script>
</body>
</html>
