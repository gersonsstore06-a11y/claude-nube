<?php
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/storefront_data.php';

const STOREFRONT_CART_SESSION_KEY = 'tienda_virtual_cart';
const STOREFRONT_CART_FLASH_KEY = 'tienda_virtual_cart_flash';
const STOREFRONT_CART_CONTACT_KEY = 'tienda_virtual_cart_contact';
const STOREFRONT_ORDER_WHATSAPP_PHONE = '50433574646';
const STOREFRONT_CLIENT_ROLE_NAME = 'CLIENTE';

function storefrontCartStartSession(): void
{
    adminAuthStartSession();
}

function storefrontCartRedirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function storefrontCartNormalize(array $cart): array
{
    $normalized = [];

    foreach ($cart as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;

        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        $normalized[$productId] = $quantity;
    }

    return $normalized;
}

function storefrontCartRaw(): array
{
    storefrontCartStartSession();

    $cart = $_SESSION[STOREFRONT_CART_SESSION_KEY] ?? [];

    if (!is_array($cart)) {
        return [];
    }

    $normalized = storefrontCartNormalize($cart);

    if ($normalized !== $cart) {
        storefrontCartSave($normalized);
    }

    return $normalized;
}

function storefrontCartSave(array $cart): void
{
    storefrontCartStartSession();
    $normalized = storefrontCartNormalize($cart);

    if ($normalized === []) {
        unset($_SESSION[STOREFRONT_CART_SESSION_KEY]);
        return;
    }

    $_SESSION[STOREFRONT_CART_SESSION_KEY] = $normalized;
}

function storefrontCartSetFlash(string $type, string $message): void
{
    storefrontCartStartSession();
    $_SESSION[STOREFRONT_CART_FLASH_KEY] = [
        'type' => $type,
        'message' => $message,
    ];
}

function storefrontCartConsumeFlash(): ?array
{
    storefrontCartStartSession();

    if (!isset($_SESSION[STOREFRONT_CART_FLASH_KEY]) || !is_array($_SESSION[STOREFRONT_CART_FLASH_KEY])) {
        return null;
    }

    $flash = $_SESSION[STOREFRONT_CART_FLASH_KEY];
    unset($_SESSION[STOREFRONT_CART_FLASH_KEY]);

    return [
        'type' => (string) ($flash['type'] ?? 'info'),
        'message' => (string) ($flash['message'] ?? ''),
    ];
}

function storefrontCartCount(): int
{
    return array_sum(storefrontCartRaw());
}

function storefrontCartWhatsappPhone(): string
{
    return preg_replace('/\D+/', '', STOREFRONT_ORDER_WHATSAPP_PHONE) ?: '';
}

function storefrontCartDeliveryMethods(): array
{
    return [
        'retiro_tienda' => 'Retiro en tienda',
        'delivery_local' => 'Delivery local',
        'envio_nacional' => 'Envío nacional',
    ];
}

function storefrontCartDeliveryMethodLabel(string $method): string
{
    $methods = storefrontCartDeliveryMethods();

    return $methods[$method] ?? $methods['delivery_local'];
}

function storefrontCartCustomerData(): array
{
    storefrontCartStartSession();

    $data = $_SESSION[STOREFRONT_CART_CONTACT_KEY] ?? [];

    if (!is_array($data)) {
        $data = [];
    }

    return [
        'customer_name' => trim((string) ($data['customer_name'] ?? '')),
        'customer_phone' => trim((string) ($data['customer_phone'] ?? '')),
        'customer_address' => trim((string) ($data['customer_address'] ?? '')),
        'delivery_method' => trim((string) ($data['delivery_method'] ?? 'delivery_local')),
        'customer_note' => trim((string) ($data['customer_note'] ?? '')),
    ];
}

function storefrontCartStoreCustomerData(array $data): array
{
    storefrontCartStartSession();

    $normalized = [
        'customer_name' => trim((string) ($data['customer_name'] ?? '')),
        'customer_phone' => trim((string) ($data['customer_phone'] ?? '')),
        'customer_address' => trim((string) ($data['customer_address'] ?? '')),
        'delivery_method' => trim((string) ($data['delivery_method'] ?? 'delivery_local')),
        'customer_note' => trim((string) ($data['customer_note'] ?? '')),
    ];

    $_SESSION[STOREFRONT_CART_CONTACT_KEY] = $normalized;

    return $normalized;
}

function storefrontCartBuildWhatsappMessage(array $summary, array $customerData): string
{
    $lines = [
        'Hola, quiero realizar este pedido en Gerson\'s Store:',
        '',
        'Metodo de entrega: ' . storefrontCartDeliveryMethodLabel((string) ($customerData['delivery_method'] ?? '')),
        'Cliente: ' . (string) ($customerData['customer_name'] ?? ''),
        'Telefono: ' . (string) ($customerData['customer_phone'] ?? ''),
    ];

    if (($customerData['customer_address'] ?? '') !== '') {
        $lines[] = 'Direccion: ' . $customerData['customer_address'];
    }

    if (($customerData['order_number'] ?? '') !== '') {
        $lines[] = 'Pedido registrado: ' . $customerData['order_number'];
    }

    $lines[] = '';
    $lines[] = 'Productos:';

    foreach ($summary['items'] as $index => $item) {
        $product = $item['product'];
        $lines[] = sprintf(
            '%d. %s | Cantidad: %d | Precio: %s | Total: %s',
            $index + 1,
            (string) ($product['nombre'] ?? 'Producto'),
            (int) $item['quantity'],
            appMoney((float) $item['price']),
            appMoney((float) $item['line_total'])
        );
    }

    $lines[] = '';
    $lines[] = 'Subtotal: ' . appMoney((float) ($summary['subtotal'] ?? 0));

    if (($customerData['customer_note'] ?? '') !== '') {
        $lines[] = 'Notas: ' . $customerData['customer_note'];
    }

    $lines[] = '';
    $lines[] = 'Quedo atento a la confirmacion del pedido.';

    return implode("\n", $lines);
}

function storefrontCartWhatsappUrl(array $summary, array $customerData): ?string
{
    $phone = storefrontCartWhatsappPhone();

    if ($phone === '' || ($summary['items'] ?? []) === []) {
        return null;
    }

    return 'https://wa.me/' . rawurlencode($phone) . '?text=' . rawurlencode(
        storefrontCartBuildWhatsappMessage($summary, $customerData)
    );
}

function storefrontCartConnection(): ?PDO
{
    return storefrontConnection();
}

function storefrontCartFindProduct(int $productId): ?array
{
    if ($productId <= 0) {
        return null;
    }

    return storefrontProduct($productId);
}

function storefrontCartAdd(int $productId, int $quantity = 1): array
{
    $product = storefrontCartFindProduct($productId);

    if (!$product) {
        return [
            'ok' => false,
            'message' => 'El producto ya no está disponible para compra.',
        ];
    }

    $availableStock = max(0, (int) ($product['stock_actual'] ?? 0));

    if ($availableStock <= 0) {
        return [
            'ok' => false,
            'message' => 'Este producto no tiene stock disponible en este momento.',
        ];
    }

    $quantity = max(1, $quantity);
    $cart = storefrontCartRaw();
    $currentQuantity = (int) ($cart[$productId] ?? 0);
    $nextQuantity = min($availableStock, $currentQuantity + $quantity);
    $cart[$productId] = $nextQuantity;
    storefrontCartSave($cart);

    $message = $nextQuantity === $currentQuantity + $quantity
        ? 'Producto añadido al carrito.'
        : 'Se añadió la cantidad disponible en stock al carrito.';

    return [
        'ok' => true,
        'message' => $message,
    ];
}

function storefrontCartUpdateQuantity(int $productId, int $quantity): array
{
    $cart = storefrontCartRaw();

    if (!isset($cart[$productId])) {
        return [
            'ok' => false,
            'message' => 'El producto no está en el carrito.',
        ];
    }

    if ($quantity <= 0) {
        unset($cart[$productId]);
        storefrontCartSave($cart);

        return [
            'ok' => true,
            'message' => 'Producto eliminado del carrito.',
        ];
    }

    $product = storefrontCartFindProduct($productId);

    if (!$product) {
        unset($cart[$productId]);
        storefrontCartSave($cart);

        return [
            'ok' => false,
            'message' => 'El producto ya no está disponible y se quitó del carrito.',
        ];
    }

    $availableStock = max(0, (int) ($product['stock_actual'] ?? 0));

    if ($availableStock <= 0) {
        unset($cart[$productId]);
        storefrontCartSave($cart);

        return [
            'ok' => false,
            'message' => 'El producto se quitó del carrito porque ya no tiene stock.',
        ];
    }

    $cart[$productId] = min($availableStock, $quantity);
    storefrontCartSave($cart);

    return [
        'ok' => true,
        'message' => $cart[$productId] === $quantity
            ? 'Cantidad actualizada.'
            : 'La cantidad se ajustó al stock disponible.',
    ];
}

function storefrontCartRemove(int $productId): void
{
    $cart = storefrontCartRaw();
    unset($cart[$productId]);
    storefrontCartSave($cart);
}

function storefrontCartClear(): void
{
    storefrontCartSave([]);
}

function storefrontCartSummary(): array
{
    $cart = storefrontCartRaw();
    $items = [];
    $subtotal = 0.0;
    $count = 0;
    $cleanedCart = $cart;
    $hasChanges = false;

    foreach ($cart as $productId => $quantity) {
        $product = storefrontCartFindProduct((int) $productId);

        if (!$product) {
            unset($cleanedCart[$productId]);
            $hasChanges = true;
            continue;
        }

        $availableStock = max(0, (int) ($product['stock_actual'] ?? 0));

        if ($availableStock <= 0) {
            unset($cleanedCart[$productId]);
            $hasChanges = true;
            continue;
        }

        $quantity = min((int) $quantity, $availableStock);

        if ($quantity !== (int) $cart[$productId]) {
            $cleanedCart[$productId] = $quantity;
            $hasChanges = true;
        }

        // Mismo precio que ve el cliente en tarjeta y detalle (promocional si aplica).
        $price = storefrontProductCurrentPrice($product);
        $lineTotal = $price * $quantity;
        $subtotal += $lineTotal;
        $count += $quantity;

        $items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'price' => $price,
            'line_total' => $lineTotal,
        ];
    }

    if ($hasChanges) {
        storefrontCartSave($cleanedCart);
    }

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'count' => $count,
    ];
}

function storefrontCartSplitCustomerName(string $fullName): array
{
    $fullName = preg_replace('/\s+/', ' ', trim($fullName)) ?? '';

    if ($fullName === '') {
        return ['Cliente', 'Web'];
    }

    $parts = explode(' ', $fullName, 2);

    return [
        $parts[0],
        $parts[1] ?? 'Web',
    ];
}

function storefrontCartGeneratedEmail(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?: (string) time();

    return 'pedido-' . $digits . '@gersons-store.local';
}

function storefrontCartClientRoleId(PDO $connection): int
{
    static $roleId = null;

    if ($roleId !== null) {
        return $roleId;
    }

    $statement = $connection->prepare('SELECT id_rol FROM roles WHERE nombre = :nombre LIMIT 1');
    $statement->execute(['nombre' => STOREFRONT_CLIENT_ROLE_NAME]);
    $roleId = (int) $statement->fetchColumn();

    if ($roleId <= 0) {
        throw new RuntimeException('No se encontró el rol de cliente en la base de datos.');
    }

    return $roleId;
}

function storefrontCartFindOrCreateCustomer(PDO $connection, array $customerData): int
{
    $generatedEmail = storefrontCartGeneratedEmail((string) ($customerData['customer_phone'] ?? ''));
    $statement = $connection->prepare(
        'SELECT c.id_cliente, u.id_usuario
         FROM clientes c
         INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
         WHERE u.correo = :correo
         LIMIT 1'
    );
    $statement->execute(['correo' => $generatedEmail]);
    $existing = $statement->fetch();
    [$firstName, $lastName] = storefrontCartSplitCustomerName((string) ($customerData['customer_name'] ?? ''));

    if ($existing) {
        $updateUser = $connection->prepare(
            'UPDATE usuarios
             SET nombre = :nombre,
                 apellido = :apellido,
                 telefono = :telefono,
                 estado = :estado
             WHERE id_usuario = :id_usuario'
        );
        $updateUser->execute([
            'nombre' => $firstName,
            'apellido' => $lastName,
            'telefono' => (string) ($customerData['customer_phone'] ?? ''),
            'estado' => 'ACTIVO',
            'id_usuario' => (int) $existing['id_usuario'],
        ]);

        return (int) $existing['id_cliente'];
    }

    $insertUser = $connection->prepare(
        'INSERT INTO usuarios (id_rol, nombre, apellido, correo, clave_hash, telefono, estado)
         VALUES (:id_rol, :nombre, :apellido, :correo, :clave_hash, :telefono, :estado)'
    );
    $insertUser->execute([
        'id_rol' => storefrontCartClientRoleId($connection),
        'nombre' => $firstName,
        'apellido' => $lastName,
        'correo' => $generatedEmail,
        'clave_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
        'telefono' => (string) ($customerData['customer_phone'] ?? ''),
        'estado' => 'ACTIVO',
    ]);

    $userId = (int) $connection->lastInsertId();
    $insertClient = $connection->prepare('INSERT INTO clientes (id_usuario) VALUES (:id_usuario)');
    $insertClient->execute(['id_usuario' => $userId]);

    return (int) $connection->lastInsertId();
}

function storefrontCartCreateAddress(PDO $connection, int $customerId, array $customerData): ?int
{
    $address = trim((string) ($customerData['customer_address'] ?? ''));

    if ($address === '') {
        return null;
    }

    $insertAddress = $connection->prepare(
        'INSERT INTO direcciones (id_cliente, direccion, referencia, principal)
         VALUES (:id_cliente, :direccion, :referencia, :principal)'
    );
    $insertAddress->execute([
        'id_cliente' => $customerId,
        'direccion' => $address,
        'referencia' => trim((string) ($customerData['customer_note'] ?? '')) ?: null,
        'principal' => 0,
    ]);

    return (int) $connection->lastInsertId();
}

function storefrontCartGenerateOrderNumber(): string
{
    return 'GS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function storefrontCartCreateOrder(PDO $connection, int $customerId, ?int $addressId, array $summary): array
{
    $orderNumber = storefrontCartGenerateOrderNumber();
    $insertOrder = $connection->prepare(
        'INSERT INTO pedidos (
            id_cliente,
            id_direccion,
            numero_pedido,
            estado,
            subtotal,
            impuesto,
            envio,
            descuento,
            total
         ) VALUES (
            :id_cliente,
            :id_direccion,
            :numero_pedido,
            :estado,
            :subtotal,
            :impuesto,
            :envio,
            :descuento,
            :total
         )'
    );
    $insertOrder->execute([
        'id_cliente' => $customerId,
        'id_direccion' => $addressId,
        'numero_pedido' => $orderNumber,
        'estado' => 'PENDIENTE',
        'subtotal' => (float) ($summary['subtotal'] ?? 0),
        'impuesto' => 0,
        'envio' => 0,
        'descuento' => 0,
        'total' => (float) ($summary['subtotal'] ?? 0),
    ]);

    return [
        'id' => (int) $connection->lastInsertId(),
        'number' => $orderNumber,
    ];
}

function storefrontCartInsertOrderDetails(PDO $connection, int $orderId, array $summary): void
{
    $insertDetail = $connection->prepare(
        'INSERT INTO pedido_detalle (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
         VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario, :subtotal)'
    );

    foreach ($summary['items'] as $item) {
        $insertDetail->execute([
            'id_pedido' => $orderId,
            'id_producto' => (int) ($item['product']['id_producto'] ?? 0),
            'cantidad' => (int) $item['quantity'],
            'precio_unitario' => (float) $item['price'],
            'subtotal' => (float) $item['line_total'],
        ]);
    }
}

function storefrontCartInsertShipping(PDO $connection, int $orderId, string $deliveryMethod): void
{
    if ($deliveryMethod === 'retiro_tienda') {
        return;
    }

    $insertShipping = $connection->prepare(
        'INSERT INTO envios (id_pedido, empresa_envio, estado)
         VALUES (:id_pedido, :empresa_envio, :estado)'
    );
    $insertShipping->execute([
        'id_pedido' => $orderId,
        'empresa_envio' => storefrontCartDeliveryMethodLabel($deliveryMethod),
        'estado' => 'PENDIENTE',
    ]);
}

function storefrontCartLogOrder(PDO $connection, int $orderId, array $customerData): void
{
    $insertLog = $connection->prepare(
        'INSERT INTO bitacora (tabla_afectada, accion, id_registro, descripcion, usuario_bd)
         VALUES (:tabla_afectada, :accion, :id_registro, :descripcion, :usuario_bd)'
    );
    $insertLog->execute([
        'tabla_afectada' => 'pedidos',
        'accion' => 'CREAR',
        'id_registro' => (string) $orderId,
        'descripcion' => sprintf(
            'Pedido web por WhatsApp. Cliente: %s | Telefono: %s | Metodo: %s | Direccion: %s | Nota: %s',
            trim((string) ($customerData['customer_name'] ?? '')),
            trim((string) ($customerData['customer_phone'] ?? '')),
            storefrontCartDeliveryMethodLabel((string) ($customerData['delivery_method'] ?? '')),
            trim((string) ($customerData['customer_address'] ?? '')),
            trim((string) ($customerData['customer_note'] ?? ''))
        ),
        'usuario_bd' => 'tienda_virtual_web',
    ]);
}

function storefrontCartPersistOrder(array $summary, array $customerData): array
{
    $connection = storefrontCartConnection();

    if (!$connection) {
        return [
            'ok' => false,
            'message' => 'No se pudo conectar a la base de datos para guardar el pedido.',
        ];
    }

    try {
        $connection->beginTransaction();
        $customerId = storefrontCartFindOrCreateCustomer($connection, $customerData);
        $addressId = storefrontCartCreateAddress($connection, $customerId, $customerData);
        $order = storefrontCartCreateOrder($connection, $customerId, $addressId, $summary);
        storefrontCartInsertOrderDetails($connection, (int) $order['id'], $summary);
        storefrontCartInsertShipping($connection, (int) $order['id'], (string) ($customerData['delivery_method'] ?? 'delivery_local'));
        storefrontCartLogOrder($connection, (int) $order['id'], $customerData);
        $connection->commit();

        return [
            'ok' => true,
            'order_id' => (int) $order['id'],
            'order_number' => (string) $order['number'],
        ];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        return [
            'ok' => false,
            'message' => 'No se pudo guardar el pedido localmente: ' . $exception->getMessage(),
        ];
    }
}