<?php
/**
 * Endpoint JSON del carrito para la interfaz sin recarga (carrito lateral,
 * botones "Añadir" y cantidades). Reutiliza las funciones de
 * includes/storefront_cart.php: no contiene reglas de negocio propias.
 *
 * GET  -> resumen del carrito.
 * POST -> action = add | update | remove, con product_id y quantity.
 */
require_once __DIR__ . '/../includes/storefront_cart.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', appBaseUrl());
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function cartApiRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cartApiSummary(): array
{
    $summary = storefrontCartSummary();
    $items = [];

    foreach ($summary['items'] as $item) {
        $product = $item['product'];
        $items[] = [
            'id' => (int) $product['id_producto'],
            'name' => (string) $product['nombre'],
            'category' => (string) ($product['categoria_nombre'] ?? ''),
            'url' => BASE_URL . '/views/detalle_producto.php?id=' . (int) $product['id_producto'],
            'image' => storefrontImageUrl($product['url_imagen'] ?? ''),
            'initial' => storefrontInitial($product['categoria_nombre'] ?? $product['nombre']),
            'quantity' => (int) $item['quantity'],
            'max' => max(1, (int) ($product['stock_actual'] ?? 1)),
            'price' => appMoney((float) $item['price']),
            'lineTotal' => appMoney((float) $item['line_total']),
        ];
    }

    return [
        'count' => (int) $summary['count'],
        'subtotal' => appMoney((float) $summary['subtotal']),
        'items' => $items,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Un formulario de otro sitio no puede enviar cabeceras personalizadas sin
// una verificación CORS previa, así que exigirla bloquea peticiones cruzadas.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    cartApiRespond(['ok' => false, 'message' => 'Solicitud no válida.'], 400);
}

if ($method === 'GET') {
    cartApiRespond(['ok' => true, 'cart' => cartApiSummary()]);
}

if ($method !== 'POST') {
    cartApiRespond(['ok' => false, 'message' => 'Método no permitido.'], 405);
}

$action = trim((string) ($_POST['action'] ?? ''));
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: 0;
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

switch ($action) {
    case 'add':
        $result = storefrontCartAdd($productId, max(1, (int) ($quantity ?: 1)));
        break;
    case 'update':
        $result = storefrontCartUpdateQuantity($productId, max(0, (int) $quantity));
        break;
    case 'remove':
        storefrontCartRemove($productId);
        $result = ['ok' => true, 'message' => 'Producto eliminado del carrito.'];
        break;
    default:
        cartApiRespond(['ok' => false, 'message' => 'Acción de carrito no válida.'], 400);
}

cartApiRespond([
    'ok' => (bool) $result['ok'],
    'message' => (string) $result['message'],
    'cart' => cartApiSummary(),
], $result['ok'] ? 200 : 422);
