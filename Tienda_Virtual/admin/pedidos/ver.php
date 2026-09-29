<?php
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../config/conexion.php';

adminAuthRequireLogin();

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$order = null;
$details = [];
$errorMessage = null;

if ($orderId <= 0) {
    $errorMessage = 'Pedido no válido.';
} else {
    try {
        $connection = (new Conexion())->getConexion();
        $orderStatement = $connection->prepare(
            "SELECT
                p.id_pedido,
                p.numero_pedido,
                p.fecha_pedido,
                p.estado,
                p.subtotal,
                p.impuesto,
                p.envio,
                p.descuento,
                p.total,
                COALESCE(d.direccion, '') AS direccion,
                COALESCE(d.referencia, '') AS referencia,
                COALESCE(e.empresa_envio, 'Retiro en tienda') AS metodo_entrega,
                COALESCE(e.estado, 'NO_REQUIERE') AS estado_entrega,
                u.nombre,
                u.apellido,
                u.telefono,
                u.correo
             FROM pedidos p
             INNER JOIN clientes c ON c.id_cliente = p.id_cliente
             INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
             LEFT JOIN direcciones d ON d.id_direccion = p.id_direccion
             LEFT JOIN envios e ON e.id_pedido = p.id_pedido
             WHERE p.id_pedido = :id_pedido
             LIMIT 1"
        );
        $orderStatement->execute(['id_pedido' => $orderId]);
        $order = $orderStatement->fetch() ?: null;

        if ($order === null) {
            $errorMessage = 'El pedido solicitado no existe.';
        } else {
            $detailStatement = $connection->prepare(
                "SELECT
                    pd.cantidad,
                    pd.precio_unitario,
                    pd.subtotal,
                    pr.nombre,
                    pr.codigo_producto
                 FROM pedido_detalle pd
                 INNER JOIN productos pr ON pr.id_producto = pd.id_producto
                 WHERE pd.id_pedido = :id_pedido
                 ORDER BY pd.id_pedido_detalle ASC"
            );
            $detailStatement->execute(['id_pedido' => $orderId]);
            $details = $detailStatement->fetchAll() ?: [];
        }
    } catch (Throwable $exception) {
        $errorMessage = 'No se pudo cargar el resumen del pedido. ' . $exception->getMessage();
    }
}

$pageTitle = $order ? 'Pedido ' . $order['numero_pedido'] : 'Resumen de pedido';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<section class="page-section admin-heading admin-order-print-shell">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Resumen del pedido</h1>
        <p class="lead">Vista individual lista para revisar, compartir o imprimir.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Estado</span>
            <strong><?php echo $order ? htmlspecialchars((string) $order['estado'], ENT_QUOTES, 'UTF-8') : 'N/D'; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Total</span>
            <strong><?php echo $order ? 'S/ ' . number_format((float) $order['total'], 2) : 'S/ 0.00'; ?></strong>
        </article>
    </div>
</section>

<?php if ($errorMessage !== null): ?>
    <section class="page-section admin-message admin-message-error">
        <strong>No se pudo cargar el pedido</strong>
        <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/pedidos/index.php">Volver a pedidos</a>
    </section>
<?php else: ?>
    <section class="admin-layout admin-order-layout admin-order-print-shell">
        <div class="page-section admin-products-panel">
            <div class="section-head admin-order-actions">
                <div>
                    <h2 class="section-title"><?php echo htmlspecialchars((string) $order['numero_pedido'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="admin-help-text">Fecha: <?php echo htmlspecialchars((string) $order['fecha_pedido'], ENT_QUOTES, 'UTF-8'); ?> | Entrega: <?php echo htmlspecialchars((string) $order['metodo_entrega'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="admin-inline-actions admin-order-action-buttons">
                    <button class="button button-secondary" type="button" onclick="window.print()">Imprimir</button>
                    <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/pedidos/index.php">Volver al listado</a>
                </div>
            </div>

            <div class="admin-summary-grid admin-order-summary-grid">
                <article class="admin-summary-card">
                    <span>Subtotal</span>
                    <strong>S/ <?php echo number_format((float) $order['subtotal'], 2); ?></strong>
                    <p>Base del pedido antes de impuesto y envio.</p>
                </article>
                <article class="admin-summary-card">
                    <span>Impuesto</span>
                    <strong>S/ <?php echo number_format((float) $order['impuesto'], 2); ?></strong>
                    <p>Cargo tributario aplicado al pedido.</p>
                </article>
                <article class="admin-summary-card">
                    <span>Envio</span>
                    <strong>S/ <?php echo number_format((float) $order['envio'], 2); ?></strong>
                    <p>Costo asociado al metodo de entrega seleccionado.</p>
                </article>
                <article class="admin-summary-card">
                    <span>Total final</span>
                    <strong>S/ <?php echo number_format((float) $order['total'], 2); ?></strong>
                    <p>Descuento aplicado: S/ <?php echo number_format((float) $order['descuento'], 2); ?></p>
                </article>
            </div>

            <div class="admin-section-stack">
                <div>
                    <h3 class="section-title section-title-small">Detalle de productos</h3>
                    <p class="admin-help-text">Resumen de lineas incluidas en este pedido.</p>
                </div>

                <div class="admin-table-wrap">
                    <table class="table admin-table-cardify">
                        <thead>
                        <tr>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($details as $detail): ?>
                            <tr>
                                <td data-label="Producto"><?php echo htmlspecialchars((string) $detail['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="SKU"><?php echo htmlspecialchars((string) $detail['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Cantidad"><?php echo (int) $detail['cantidad']; ?></td>
                                <td data-label="Precio unitario">S/ <?php echo number_format((float) $detail['precio_unitario'], 2); ?></td>
                                <td data-label="Subtotal">S/ <?php echo number_format((float) $detail['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside class="page-section admin-side-panel admin-order-side-panel">
            <div class="admin-section-stack">
                <div>
                    <h3 class="section-title section-title-small">Cliente</h3>
                </div>

                <div class="admin-note-list">
                    <article class="admin-note-item">
                        <strong><?php echo htmlspecialchars(trim($order['nombre'] . ' ' . $order['apellido']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p><?php echo htmlspecialchars((string) $order['telefono'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><?php echo htmlspecialchars((string) $order['correo'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                </div>
            </div>

            <div class="admin-section-stack">
                <div>
                    <h3 class="section-title section-title-small">Entrega</h3>
                </div>

                <div class="admin-note-list">
                    <article class="admin-note-item">
                        <strong><?php echo htmlspecialchars((string) $order['metodo_entrega'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p>Estado envio: <?php echo htmlspecialchars((string) $order['estado_entrega'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ((string) $order['direccion'] !== ''): ?>
                            <p>Direccion: <?php echo htmlspecialchars((string) $order['direccion'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php if ((string) $order['referencia'] !== ''): ?>
                            <p>Nota: <?php echo htmlspecialchars((string) $order['referencia'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </article>
                </div>
            </div>

            <div class="admin-section-stack">
                <div>
                    <h3 class="section-title section-title-small">Resumen rapido</h3>
                </div>

                <div class="admin-note-list">
                    <article class="admin-note-item">
                        <strong>Pedido <?php echo htmlspecialchars((string) $order['numero_pedido'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p>Estado: <?php echo htmlspecialchars((string) $order['estado'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Total: S/ <?php echo number_format((float) $order['total'], 2); ?></p>
                    </article>
                </div>
            </div>
        </aside>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>