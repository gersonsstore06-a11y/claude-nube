<?php
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../includes/admin_panel_data.php';

adminAuthRequireLogin();

function adminPurchaseBuildUrl(array $overrides = []): string
{
    $params = $overrides;

    if (($params['success'] ?? '') === '') {
        unset($params['success']);
    }

    if (($params['error'] ?? '') === '') {
        unset($params['error']);
    }

    return adminAuthBaseUrl() . '/admin/compras/index.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function adminPurchaseDefaultData(): array
{
    return [
        'proveedor' => '',
        'numero_documento' => '',
        'fecha_compra' => date('Y-m-d'),
        'id_producto' => '',
        'cantidad' => '1',
        'costo_unitario' => '',
        'estado' => 'RECIBIDA',
        'observacion' => '',
    ];
}

function adminPurchaseAdjustInventory(PDO $connection, int $productId, int $quantityDelta, float $purchaseCost): void
{
    $updateInventory = $connection->prepare(
        'UPDATE inventario
         SET stock_actual = COALESCE(stock_actual, 0) + :quantity_delta,
             ultimo_movimiento = NOW()
         WHERE id_producto = :id_producto'
    );
    $updateInventory->execute([
        'quantity_delta' => $quantityDelta,
        'id_producto' => $productId,
    ]);

    if ($updateInventory->rowCount() === 0) {
        $insertInventory = $connection->prepare(
            'INSERT INTO inventario (id_producto, stock_actual, ultimo_movimiento)
             VALUES (:id_producto, :stock_actual, NOW())'
        );
        $insertInventory->execute([
            'id_producto' => $productId,
            'stock_actual' => max(0, $quantityDelta),
        ]);
    }

    $updateProduct = $connection->prepare('UPDATE productos SET precio_compra = :precio_compra WHERE id_producto = :id_producto');
    $updateProduct->execute([
        'precio_compra' => $purchaseCost,
        'id_producto' => $productId,
    ]);
}

$connection = adminPanelConnection();
$errors = [];
$successMessage = null;
$formData = adminPurchaseDefaultData();

if ($connection instanceof PDO) {
    adminPanelEnsureManagementTables($connection);
}

if (isset($_GET['success'])) {
    $successKey = trim((string) $_GET['success']);
    $successMap = [
        'created' => 'Compra registrada correctamente.',
        'status' => 'Estado de la compra actualizado.',
    ];
    $successMessage = $successMap[$successKey] ?? null;
}

if (isset($_GET['error'])) {
    $errorKey = trim((string) $_GET['error']);
    $errorMap = [
        'missing' => 'La compra seleccionada ya no existe.',
        'save' => 'No se pudo guardar la compra.',
    ];
    if (isset($errorMap[$errorKey])) {
        $errors[] = $errorMap[$errorKey];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connection instanceof PDO) {
    $action = trim((string) ($_POST['action'] ?? 'create_purchase'));

    if ($action === 'create_purchase') {
        $formData = [
            'proveedor' => trim((string) ($_POST['proveedor'] ?? '')),
            'numero_documento' => trim((string) ($_POST['numero_documento'] ?? '')),
            'fecha_compra' => trim((string) ($_POST['fecha_compra'] ?? date('Y-m-d'))),
            'id_producto' => trim((string) ($_POST['id_producto'] ?? '')),
            'cantidad' => trim((string) ($_POST['cantidad'] ?? '1')),
            'costo_unitario' => trim((string) ($_POST['costo_unitario'] ?? '')),
            'estado' => strtoupper(trim((string) ($_POST['estado'] ?? 'RECIBIDA'))),
            'observacion' => trim((string) ($_POST['observacion'] ?? '')),
        ];

        $productId = filter_var($formData['id_producto'], FILTER_VALIDATE_INT) ?: 0;
        $quantity = filter_var($formData['cantidad'], FILTER_VALIDATE_INT);
        $purchaseCost = filter_var(str_replace(',', '.', $formData['costo_unitario']), FILTER_VALIDATE_FLOAT);
        $allowedStatuses = ['RECIBIDA', 'PENDIENTE', 'CANCELADA'];

        if ($formData['proveedor'] === '') {
            $errors[] = 'Ingresa el proveedor de la compra.';
        }

        if ($productId <= 0) {
            $errors[] = 'Selecciona un producto valido.';
        }

        if ($quantity === false || $quantity <= 0) {
            $errors[] = 'Ingresa una cantidad valida.';
        }

        if ($purchaseCost === false || $purchaseCost <= 0) {
            $errors[] = 'Ingresa un costo unitario valido.';
        }

        if (!in_array($formData['estado'], $allowedStatuses, true)) {
            $errors[] = 'Selecciona un estado valido para la compra.';
        }

        if ($errors === []) {
            try {
                $connection->beginTransaction();
                $total = $quantity * $purchaseCost;

                $purchaseStatement = $connection->prepare(
                    'INSERT INTO compras (proveedor, numero_documento, fecha_compra, estado, total, observacion)
                     VALUES (:proveedor, :numero_documento, :fecha_compra, :estado, :total, :observacion)'
                );
                $purchaseStatement->execute([
                    'proveedor' => $formData['proveedor'],
                    'numero_documento' => $formData['numero_documento'] !== '' ? $formData['numero_documento'] : null,
                    'fecha_compra' => $formData['fecha_compra'],
                    'estado' => $formData['estado'],
                    'total' => $total,
                    'observacion' => $formData['observacion'] !== '' ? $formData['observacion'] : null,
                ]);

                $purchaseId = (int) $connection->lastInsertId();

                $detailStatement = $connection->prepare(
                    'INSERT INTO compra_detalle (id_compra, id_producto, cantidad, costo_unitario, subtotal)
                     VALUES (:id_compra, :id_producto, :cantidad, :costo_unitario, :subtotal)'
                );
                $detailStatement->execute([
                    'id_compra' => $purchaseId,
                    'id_producto' => $productId,
                    'cantidad' => $quantity,
                    'costo_unitario' => $purchaseCost,
                    'subtotal' => $total,
                ]);

                if ($formData['estado'] === 'RECIBIDA') {
                    adminPurchaseAdjustInventory($connection, $productId, $quantity, $purchaseCost);
                }

                $connection->commit();
                adminAuthRedirect(adminPurchaseBuildUrl(['success' => 'created']));
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                $errors[] = 'No se pudo guardar la compra. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'change_status') {
        $purchaseId = filter_var($_POST['purchase_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $nextStatus = strtoupper(trim((string) ($_POST['next_status'] ?? '')));
        $allowedStatuses = ['RECIBIDA', 'PENDIENTE', 'CANCELADA'];

        if ($purchaseId <= 0 || !in_array($nextStatus, $allowedStatuses, true)) {
            adminAuthRedirect(adminPurchaseBuildUrl(['error' => 'missing']));
        }

        try {
            $detailStatement = $connection->prepare(
                'SELECT c.estado, cd.id_producto, cd.cantidad, cd.costo_unitario
                 FROM compras c
                 INNER JOIN compra_detalle cd ON cd.id_compra = c.id_compra
                 WHERE c.id_compra = :id_compra
                 LIMIT 1'
            );
            $detailStatement->execute(['id_compra' => $purchaseId]);
            $purchaseDetail = $detailStatement->fetch();

            if (!$purchaseDetail) {
                adminAuthRedirect(adminPurchaseBuildUrl(['error' => 'missing']));
            }

            $previousStatus = (string) $purchaseDetail['estado'];
            $productId = (int) $purchaseDetail['id_producto'];
            $quantity = (int) $purchaseDetail['cantidad'];
            $purchaseCost = (float) $purchaseDetail['costo_unitario'];

            $connection->beginTransaction();

            if ($previousStatus !== 'RECIBIDA' && $nextStatus === 'RECIBIDA') {
                adminPurchaseAdjustInventory($connection, $productId, $quantity, $purchaseCost);
            } elseif ($previousStatus === 'RECIBIDA' && $nextStatus !== 'RECIBIDA') {
                adminPurchaseAdjustInventory($connection, $productId, -$quantity, $purchaseCost);
            }

            $updateStatement = $connection->prepare('UPDATE compras SET estado = :estado WHERE id_compra = :id_compra');
            $updateStatement->execute([
                'estado' => $nextStatus,
                'id_compra' => $purchaseId,
            ]);

            $connection->commit();
            adminAuthRedirect(adminPurchaseBuildUrl(['success' => 'status']));
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            $errors[] = 'No se pudo actualizar el estado de la compra. Detalle: ' . $exception->getMessage();
        }
    }
}

$products = adminPanelFetchAll(
    "SELECT
        p.id_producto,
        p.nombre,
        p.codigo_producto,
        COALESCE(i.stock_actual, 0) AS stock_actual
     FROM productos p
     LEFT JOIN inventario i ON i.id_producto = p.id_producto
     ORDER BY p.nombre ASC"
);

$purchaseSnapshot = adminPanelPurchaseSnapshot();
$purchaseMetrics = $purchaseSnapshot['metrics'];
$recentPurchases = $purchaseSnapshot['recent_purchases'];
$supplierSummary = $purchaseSnapshot['by_supplier'];
$catalogSignals = $purchaseSnapshot['catalog_signals'];
$replenishmentProducts = $catalogSignals['replenishment_products'];
$demandByCategory = $catalogSignals['demand_by_category'];

$pageTitle = 'Admin Compras';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Compras y reposicion</h1>
        <p class="lead">Registra compras y actualiza inventario al recibirlas.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Compras registradas</span>
            <strong><?php echo (int) $purchaseMetrics['total_purchases']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Invertido total</span>
            <strong>S/ <?php echo number_format((float) $purchaseMetrics['invested_amount'], 2); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Pendiente por recibir</span>
            <strong>S/ <?php echo number_format((float) $purchaseMetrics['pending_amount'], 2); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Unidades compradas</span>
            <strong><?php echo (int) $purchaseMetrics['total_units']; ?></strong>
        </article>
    </div>
</section>

<?php if ($successMessage !== null): ?>
    <section class="page-section admin-message admin-message-success">
        <strong>Operacion completada</strong>
        <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    </section>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <section class="page-section admin-message admin-message-error">
        <strong>Revisa estos puntos</strong>
        <ul class="admin-error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="admin-layout">
    <div class="page-section admin-products-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title">Compras recientes</h2>
                <p class="admin-help-text">Cada compra queda guardada y puede mover stock.</p>
            </div>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Actualizar stock</a>
        </div>

        <?php if ($recentPurchases !== []): ?>
            <div class="admin-table-wrap">
                <table class="table admin-table-cardify">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Producto</th>
                            <th>Documento</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPurchases as $purchase): ?>
                            <tr>
                                <td data-label="Fecha"><?php echo htmlspecialchars((string) ($purchase['fecha_compra'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Proveedor"><?php echo htmlspecialchars($purchase['proveedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Producto">
                                    <?php echo htmlspecialchars($purchase['producto_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    <small class="admin-inline-note"><?php echo htmlspecialchars((string) ($purchase['codigo_producto'] ?? 'Sin codigo'), ENT_QUOTES, 'UTF-8'); ?> | <?php echo (int) ($purchase['cantidad'] ?? 0); ?> uds</small>
                                </td>
                                <td data-label="Documento"><?php echo htmlspecialchars((string) ($purchase['numero_documento'] ?? 'Sin documento'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Estado"><?php echo htmlspecialchars($purchase['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Total">S/ <?php echo number_format((float) ($purchase['total'] ?? 0), 2); ?></td>
                                <td data-label="Acciones">
                                    <div class="admin-table-actions">
                                        <?php foreach (['RECIBIDA', 'PENDIENTE', 'CANCELADA'] as $status): ?>
                                            <?php if ($status !== (string) $purchase['estado']): ?>
                                                <form action="" method="post">
                                                    <input type="hidden" name="action" value="change_status">
                                                    <input type="hidden" name="purchase_id" value="<?php echo (int) $purchase['id_compra']; ?>">
                                                    <input type="hidden" name="next_status" value="<?php echo $status; ?>">
                                                    <button class="button button-secondary admin-small-button" type="submit"><?php echo $status; ?></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-note-item">
                <strong>Todavia no hay compras registradas.</strong>
                <p>Usa el formulario lateral para guardar la primera compra del sistema.</p>
            </div>
        <?php endif; ?>

        <div class="admin-section-stack">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title-small">Resumen por proveedor</h2>
                    <p class="admin-help-text">Muestra cuánto inviertes por proveedor.</p>
                </div>
            </div>

            <?php if ($supplierSummary !== []): ?>
                <div class="admin-summary-grid">
                    <?php foreach ($supplierSummary as $row): ?>
                        <article class="admin-summary-card">
                            <span><?php echo htmlspecialchars($row['proveedor'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?php echo (int) ($row['purchase_count'] ?? 0); ?> compras</strong>
                            <p>S/ <?php echo number_format((float) ($row['total_amount'] ?? 0), 2); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item">
                    <strong>Aun no hay resumen por proveedor.</strong>
                    <p>Se completara automaticamente cuando registres compras reales.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-section-stack">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title-small">Reposicion sugerida</h2>
                    <p class="admin-help-text">Apoyo rápido para decidir qué reponer.</p>
                </div>
            </div>

            <?php if ($replenishmentProducts !== []): ?>
                <div class="admin-insight-list">
                    <?php foreach ($replenishmentProducts as $product): ?>
                        <article class="admin-insight-card">
                            <div class="admin-insight-head">
                                <div>
                                    <h3><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="admin-insight-copy"><?php echo htmlspecialchars($product['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) ($product['codigo_producto'] ?? 'Sin codigo'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="admin-status-badge <?php echo (int) ($product['stock_actual'] ?? 0) === 0 ? 'admin-status-badge-hidden' : 'admin-status-badge-visible'; ?>">Stock: <?php echo (int) ($product['stock_actual'] ?? 0); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="page-section admin-side-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title section-title-small">Registrar compra</h2>
                <p class="admin-help-text">Si queda recibida, el stock sube al momento.</p>
            </div>
        </div>

        <form class="admin-form-grid" action="" method="post">
            <input type="hidden" name="action" value="create_purchase">

            <label class="admin-field-span-2">
                Proveedor
                <input type="text" name="proveedor" value="<?php echo htmlspecialchars($formData['proveedor'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Documento
                <input type="text" name="numero_documento" value="<?php echo htmlspecialchars($formData['numero_documento'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Fecha
                <input type="date" name="fecha_compra" value="<?php echo htmlspecialchars($formData['fecha_compra'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label class="admin-field-span-2">
                Producto
                <select name="id_producto" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo (int) $product['id_producto']; ?>" <?php echo (string) $product['id_producto'] === $formData['id_producto'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) ($product['codigo_producto'] ?? 'Sin codigo'), ENT_QUOTES, 'UTF-8'); ?> | Stock <?php echo (int) ($product['stock_actual'] ?? 0); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Cantidad
                <input type="number" name="cantidad" min="1" value="<?php echo htmlspecialchars($formData['cantidad'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Costo unitario
                <input type="number" name="costo_unitario" min="0.01" step="0.01" value="<?php echo htmlspecialchars($formData['costo_unitario'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label class="admin-field-span-2">
                Estado inicial
                <select name="estado">
                    <?php foreach (['RECIBIDA', 'PENDIENTE', 'CANCELADA'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $formData['estado'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="admin-field-span-2">
                Observacion
                <textarea name="observacion" rows="4"><?php echo htmlspecialchars($formData['observacion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>

            <button class="button admin-field-span-2" type="submit">Guardar compra</button>
        </form>

        <div class="admin-section-stack">
            <div>
                <h3 class="section-title-small">Demanda por categoria</h3>
            </div>

            <?php if ($demandByCategory !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($demandByCategory as $row): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars($row['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo (int) ($row['units_sold'] ?? 0); ?> unidades | S/ <?php echo number_format((float) ($row['revenue'] ?? 0), 2); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item">
                    <strong>Sin señal de demanda todavia.</strong>
                    <p>Cuando crezcan los pedidos, aqui veras que categorias requieren mas reposicion.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
