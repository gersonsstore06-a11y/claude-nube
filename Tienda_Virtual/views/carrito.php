<?php
require_once __DIR__ . '/../includes/storefront_cart.php';
require_once __DIR__ . '/../includes/storefront_ui.php';

$customerData = storefrontCartCustomerData();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: 0;
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $result = [
        'ok' => true,
        'message' => '',
    ];

    if ($action === 'update_quantity') {
        $result = storefrontCartUpdateQuantity($productId, max(0, (int) $quantity));
    } elseif ($action === 'remove_item') {
        storefrontCartRemove($productId);
        $result['message'] = 'Producto eliminado del carrito.';
    } elseif ($action === 'clear_cart') {
        storefrontCartClear();
        $result['message'] = 'Carrito vaciado.';
    } elseif ($action === 'send_whatsapp_order') {
        $customerData = storefrontCartStoreCustomerData([
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'customer_address' => $_POST['customer_address'] ?? '',
            'delivery_method' => $_POST['delivery_method'] ?? 'delivery_local',
            'customer_note' => $_POST['customer_note'] ?? '',
        ]);
        $summary = storefrontCartSummary();

        if (($summary['items'] ?? []) === []) {
            $result = [
                'ok' => false,
                'message' => 'Agrega al menos un producto antes de enviar tu pedido.',
            ];
        } elseif ($customerData['customer_name'] === '') {
            $result = [
                'ok' => false,
                'message' => 'Ingresa tu nombre para enviar el pedido por WhatsApp.',
            ];
        } elseif ($customerData['customer_phone'] === '') {
            $result = [
                'ok' => false,
                'message' => 'Ingresa tu número de teléfono para identificar el pedido.',
            ];
        } elseif ($customerData['delivery_method'] !== 'retiro_tienda' && $customerData['customer_address'] === '') {
            $result = [
                'ok' => false,
                'message' => 'Ingresa una dirección para los pedidos con entrega o envío.',
            ];
        } else {
            $persistedOrder = storefrontCartPersistOrder($summary, $customerData);

            if (!$persistedOrder['ok']) {
                $result = [
                    'ok' => false,
                    'message' => (string) ($persistedOrder['message'] ?? 'No se pudo guardar el pedido.'),
                ];
            } else {
                $customerData['order_number'] = (string) ($persistedOrder['order_number'] ?? '');
                storefrontCartStoreCustomerData($customerData);
                $whatsappUrl = storefrontCartWhatsappUrl($summary, $customerData);

                if ($whatsappUrl === null) {
                    $result = [
                        'ok' => false,
                        'message' => 'No se pudo preparar el pedido para WhatsApp.',
                    ];
                } else {
                    storefrontCartClear();
                    storefrontCartSetFlash('success', 'Pedido ' . $customerData['order_number'] . ' registrado y enviado a WhatsApp.');
                    storefrontCartRedirect($whatsappUrl);
                }
            }
        }
    } else {
        $result = [
            'ok' => false,
            'message' => 'Acción de carrito no válida.',
        ];
    }

    storefrontCartSetFlash($result['ok'] ? 'success' : 'error', $result['message']);
    storefrontCartRedirect(adminAuthBaseUrl() . '/views/carrito.php');
}

$cartSummary = storefrontCartSummary();
$cartItems = $cartSummary['items'];
$customerData = storefrontCartCustomerData();
$deliveryMethods = storefrontCartDeliveryMethods();
$flashMessage = storefrontCartConsumeFlash();
$pageTitle = 'Carrito';
$pageDescription = 'Revisa tu carrito en Gerson\'s Store y envía tu pedido por WhatsApp.';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<div class="sf-page-head">
    <nav class="sf-breadcrumb" aria-label="Ruta de navegación">
        <ol>
            <li><a href="<?php echo BASE_URL; ?>/index.php">Inicio</a></li>
            <li aria-current="page">Carrito</li>
        </ol>
    </nav>
    <div class="sf-page-head-row">
        <div>
            <h1 class="sf-page-title">Tu carrito</h1>
            <?php if ($cartItems !== []): ?>
                <p class="sf-page-lead"><span data-cart-count-text><?php echo (int) $cartSummary['count']; ?></span> producto(s) · revisa cantidades y envía tu pedido.</p>
            <?php endif; ?>
        </div>
        <?php if ($cartItems !== []): ?>
            <ol class="sf-steps" aria-label="Pasos del pedido">
                <li class="is-done"><span>1</span>Elegir productos</li>
                <li class="is-current" aria-current="step"><span>2</span>Revisar carrito</li>
                <li><span>3</span>Enviar por WhatsApp</li>
            </ol>
        <?php endif; ?>
    </div>
</div>

<?php if ($flashMessage && $flashMessage['message'] !== ''): ?>
    <div class="sf-alert sf-alert-<?php echo $flashMessage['type'] === 'success' ? 'success' : 'error'; ?>" role="<?php echo $flashMessage['type'] === 'success' ? 'status' : 'alert'; ?>">
        <?php echo sfIcon($flashMessage['type'] === 'success' ? 'check' : 'close'); ?>
        <span><?php echo sfEscape($flashMessage['message']); ?></span>
    </div>
<?php endif; ?>

<?php if ($cartItems !== []): ?>
    <div class="sf-cart-layout" data-cart-page>
        <section class="sf-cart-lines" aria-label="Productos en el carrito">
            <?php foreach ($cartItems as $cartItem): ?>
                <?php
                $product = $cartItem['product'];
                $imageUrl = storefrontImageUrl($product['url_imagen'] ?? '');
                $lineFormId = 'cart-line-' . (int) $product['id_producto'];
                $productUrl = sfProductUrl($product);
                ?>
                <article class="sf-cart-line" data-cart-line data-product-id="<?php echo (int) $product['id_producto']; ?>">
                    <a class="sf-cart-line-media" href="<?php echo sfEscape($productUrl); ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($imageUrl !== ''): ?>
                            <img src="<?php echo sfEscape($imageUrl); ?>" alt="" loading="lazy" decoding="async" width="160" height="160">
                        <?php else: ?>
                            <span class="sf-card-placeholder"><?php echo sfEscape(storefrontInitial($product['categoria_nombre'])); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="sf-cart-line-info">
                        <span class="sf-card-eyebrow"><?php echo sfEscape($product['categoria_nombre']); ?></span>
                        <h2 class="sf-cart-line-title"><a href="<?php echo sfEscape($productUrl); ?>"><?php echo sfEscape($product['nombre']); ?></a></h2>
                        <span class="sf-cart-line-meta">SKU: <?php echo sfEscape($product['codigo_producto']); ?> · <?php echo sfEscape(appMoney((float) $cartItem['price'])); ?> c/u</span>
                    </div>
                    <div class="sf-cart-line-qty">
                        <div class="sf-qty sf-qty-sm" data-quantity-selector>
                            <button type="button" data-quantity-decrease aria-label="Restar una unidad de <?php echo sfEscape($product['nombre']); ?>"><?php echo sfIcon('minus'); ?></button>
                            <input
                                type="number"
                                name="quantity"
                                value="<?php echo (int) $cartItem['quantity']; ?>"
                                min="1"
                                max="<?php echo max(1, (int) $product['stock_actual']); ?>"
                                inputmode="numeric"
                                aria-label="Cantidad de <?php echo sfEscape($product['nombre']); ?>"
                                data-quantity-input
                                data-cart-line-qty
                                form="<?php echo $lineFormId; ?>"
                            >
                            <button type="button" data-quantity-increase aria-label="Sumar una unidad de <?php echo sfEscape($product['nombre']); ?>"><?php echo sfIcon('plus'); ?></button>
                        </div>
                    </div>
                    <strong class="sf-cart-line-total" data-cart-line-total><?php echo sfEscape(appMoney((float) $cartItem['line_total'])); ?></strong>
                    <form class="sf-cart-line-actions" id="<?php echo $lineFormId; ?>" method="post" action="">
                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id_producto']; ?>">
                        <button class="sf-button sf-button-ghost sf-button-sm" type="submit" name="action" value="update_quantity" data-cart-line-update>Actualizar</button>
                        <button class="sf-icon-button sf-icon-button-danger" type="submit" name="action" value="remove_item" aria-label="Eliminar <?php echo sfEscape($product['nombre']); ?> del carrito" data-cart-line-remove><?php echo sfIcon('trash'); ?></button>
                    </form>
                </article>
            <?php endforeach; ?>

            <div class="sf-cart-lines-foot">
                <a class="sf-link-arrow" href="<?php echo BASE_URL; ?>/views/catalogo.php"><?php echo sfIcon('arrow-left'); ?>Seguir comprando</a>
                <form method="post" action="" onsubmit="return confirm('¿Vaciar el carrito? Se quitarán todos los productos.');">
                    <button class="sf-link-plain sf-link-danger" type="submit" name="action" value="clear_cart">Vaciar carrito</button>
                </form>
            </div>
        </section>

        <aside class="sf-checkout" aria-labelledby="sf-checkout-title">
            <div class="sf-checkout-summary">
                <div class="sf-summary-row"><span>Productos</span><span data-cart-count-text><?php echo (int) $cartSummary['count']; ?></span></div>
                <div class="sf-summary-row sf-summary-total"><span>Subtotal</span><strong data-cart-subtotal><?php echo sfEscape(appMoney((float) $cartSummary['subtotal'])); ?></strong></div>
                <p class="sf-summary-note">El costo de entrega, si aplica, se confirma por WhatsApp.</p>
            </div>

            <form class="sf-checkout-form" method="post" action="" data-checkout-form>
                <input type="hidden" name="action" value="send_whatsapp_order">
                <h2 class="sf-checkout-title" id="sf-checkout-title">Datos para tu pedido</h2>

                <label class="sf-form-field">
                    <span>Tu nombre</span>
                    <input type="text" name="customer_name" value="<?php echo sfEscape($customerData['customer_name']); ?>" placeholder="Nombre y apellido" autocomplete="name" required>
                </label>

                <label class="sf-form-field">
                    <span>Tu teléfono</span>
                    <input type="tel" name="customer_phone" value="<?php echo sfEscape($customerData['customer_phone']); ?>" placeholder="Ejemplo: 9876 5432" autocomplete="tel" inputmode="tel" required>
                </label>

                <fieldset class="sf-form-field sf-delivery">
                    <legend>Método de entrega</legend>
                    <?php foreach ($deliveryMethods as $methodValue => $methodLabel): ?>
                        <label class="sf-radio-card">
                            <input type="radio" name="delivery_method" value="<?php echo sfEscape($methodValue); ?>" <?php echo $customerData['delivery_method'] === $methodValue ? 'checked' : ''; ?> required data-delivery-option>
                            <span><?php echo sfIcon($methodValue === 'retiro_tienda' ? 'store' : 'truck'); ?><?php echo sfEscape($methodLabel); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <label class="sf-form-field" data-address-field>
                    <span>Dirección de entrega <small data-address-hint>(obligatoria para delivery o envío)</small></span>
                    <textarea name="customer_address" rows="3" placeholder="Barrio, calle, casa y referencia" autocomplete="street-address"><?php echo sfEscape($customerData['customer_address']); ?></textarea>
                </label>

                <label class="sf-form-field">
                    <span>Detalles adicionales <small>(opcional)</small></span>
                    <textarea name="customer_note" rows="2" placeholder="Horario, referencia u otra indicación"><?php echo sfEscape($customerData['customer_note']); ?></textarea>
                </label>

                <button class="sf-button sf-button-whatsapp sf-button-lg sf-button-block" type="submit" data-checkout-submit>
                    <?php echo sfIcon('chat'); ?><span data-button-label>Enviar pedido por WhatsApp</span>
                </button>
                <p class="sf-checkout-note">Guardaremos tu pedido y se abrirá WhatsApp con el detalle para enviarlo a la tienda (<?php echo sfEscape(STOREFRONT_ORDER_WHATSAPP_PHONE); ?>).</p>
            </form>
        </aside>
    </div>
<?php else: ?>
    <section class="sf-section">
        <div class="sf-empty sf-empty-cart">
            <span class="sf-empty-icon"><?php echo sfIcon('bag'); ?></span>
            <strong>Tu carrito está vacío.</strong>
            <p>Explora el catálogo y añade los productos que te gusten; aparecerán aquí para que envíes tu pedido.</p>
            <a class="sf-button sf-button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ir al catálogo<?php echo sfIcon('arrow-right'); ?></a>
        </div>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
