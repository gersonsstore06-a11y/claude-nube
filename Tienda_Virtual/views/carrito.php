<?php
require_once __DIR__ . '/../includes/storefront_cart.php';

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
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<section class="page-section">
    <div class="section-head">
        <h1 class="page-title">Carrito de compras</h1>
        <?php if ($cartItems !== []): ?>
            <form method="post" action="">
                <button class="button button-secondary" type="submit" name="action" value="clear_cart">Vaciar carrito</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($flashMessage && $flashMessage['message'] !== ''): ?>
        <div class="cart-feedback cart-feedback-<?php echo htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($cartItems !== []): ?>
        <div class="cart-items">
            <?php foreach ($cartItems as $cartItem): ?>
                <?php
                $product = $cartItem['product'];
                $imageUrl = storefrontImageUrl($product['url_imagen'] ?? '');
                $lineFormId = 'cart-line-' . (int) $product['id_producto'];
                ?>
                <article class="cart-item-card">
                    <div class="cart-item-top">
                        <div class="cart-product-cell">
                            <a class="cart-product-thumb" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>">
                                <?php if ($imageUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php else: ?>
                                    <span class="shop-card-placeholder cart-thumb-placeholder"><?php echo htmlspecialchars(storefrontInitial($product['categoria_nombre']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="cart-product-copy">
                                <strong><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span>SKU: <?php echo htmlspecialchars($product['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <a class="inline-link" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>">Ver producto</a>
                            </div>
                        </div>
                    </div>
                    <div class="cart-item-metrics">
                        <div class="cart-item-metric">
                            <span class="cart-item-label">Precio</span>
                            <strong class="cart-item-value"><?php echo htmlspecialchars(appMoney((float) $cartItem['price']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="cart-item-metric cart-item-metric-quantity">
                            <span class="cart-item-label">Cantidad</span>
                            <div class="quantity-selector cart-quantity-selector" data-quantity-selector>
                                <button type="button" data-quantity-decrease>-</button>
                                <input
                                    type="number"
                                    name="quantity"
                                    value="<?php echo (int) $cartItem['quantity']; ?>"
                                    min="1"
                                    max="<?php echo max(1, (int) $product['stock_actual']); ?>"
                                    data-quantity-input
                                    form="<?php echo $lineFormId; ?>"
                                >
                                <button type="button" data-quantity-increase>+</button>
                            </div>
                        </div>
                        <div class="cart-item-metric">
                            <span class="cart-item-label">Total</span>
                            <strong class="cart-item-value"><?php echo htmlspecialchars(appMoney((float) $cartItem['line_total']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                    <form class="cart-line-form cart-item-form" id="<?php echo $lineFormId; ?>" method="post" action="">
                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id_producto']; ?>">
                        <div class="cart-line-actions cart-item-actions">
                            <button class="button button-secondary admin-small-button" type="submit" name="action" value="update_quantity">Actualizar</button>
                            <button class="button button-danger admin-small-button" type="submit" name="action" value="remove_item">Eliminar</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="notice">Subtotal estimado: <?php echo htmlspecialchars(appMoney((float) $cartSummary['subtotal']), ENT_QUOTES, 'UTF-8'); ?>. Desde aquí ya puedes actualizar cantidades o eliminar productos del carrito.</p>

        <section class="cart-checkout-box">
            <div class="section-head cart-checkout-head">
                <div>
                    <h2 class="section-title section-title-small">Enviar pedido por WhatsApp</h2>
                    <p class="admin-help-text">Como no hay pago integrado, completa tus datos y el carrito te abrirá un mensaje con todos los productos para enviarlo directo al número de la tienda.</p>
                </div>
            </div>
            <form class="cart-checkout-form" method="post" action="">
                <input type="hidden" name="action" value="send_whatsapp_order">

                <label>
                    Tu nombre
                    <input type="text" name="customer_name" value="<?php echo htmlspecialchars($customerData['customer_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Escribe tu nombre" required>
                </label>

                <label>
                    Tu telefono
                    <input type="tel" name="customer_phone" value="<?php echo htmlspecialchars($customerData['customer_phone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ejemplo: 987654321" required>
                </label>

                <label>
                    Metodo de entrega
                    <select name="delivery_method" required>
                        <?php foreach ($deliveryMethods as $methodValue => $methodLabel): ?>
                            <option value="<?php echo htmlspecialchars($methodValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $customerData['delivery_method'] === $methodValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="cart-checkout-notes">
                    Direccion de entrega
                    <textarea name="customer_address" rows="3" placeholder="Barrio, calle, casa y referencia para la entrega"><?php echo htmlspecialchars($customerData['customer_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </label>

                <label class="cart-checkout-notes">
                    Detalles adicionales
                    <textarea name="customer_note" rows="4" placeholder="Dirección, referencia, horario u otra indicación opcional"><?php echo htmlspecialchars($customerData['customer_note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </label>

                <div class="cart-checkout-summary">
                    <strong>Se enviará a WhatsApp: <?php echo htmlspecialchars(STOREFRONT_ORDER_WHATSAPP_PHONE, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Metodo elegido: <?php echo htmlspecialchars(storefrontCartDeliveryMethodLabel($customerData['delivery_method']), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>Total actual del pedido: <?php echo htmlspecialchars(appMoney((float) $cartSummary['subtotal']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <button class="button button-primary" type="submit">Enviar pedido por WhatsApp</button>
            </form>
        </section>
    <?php else: ?>
        <div class="collection-empty">
            <strong>Tu carrito está vacío.</strong>
            <p>Añade productos desde el detalle para que aparezcan aquí con cantidad, total y acciones de edición.</p>
            <a class="button button-primary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ir al catálogo</a>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
