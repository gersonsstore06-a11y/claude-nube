<?php
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../includes/admin_panel_data.php';

adminAuthRequireLogin();

function adminExpenseBuildUrl(array $overrides = []): string
{
    $params = $overrides;

    if (($params['success'] ?? '') === '') {
        unset($params['success']);
    }

    if (($params['error'] ?? '') === '') {
        unset($params['error']);
    }

    return adminAuthBaseUrl() . '/admin/gastos/index.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function adminExpenseDefaultData(): array
{
    return [
        'fecha_gasto' => date('Y-m-d'),
        'categoria' => '',
        'concepto' => '',
        'monto' => '',
        'metodo_pago' => '',
        'referencia' => '',
        'estado' => 'PAGADO',
        'observacion' => '',
    ];
}

$connection = adminPanelConnection();
$errors = [];
$successMessage = null;
$formData = adminExpenseDefaultData();

if ($connection instanceof PDO) {
    adminPanelEnsureManagementTables($connection);
}

if (isset($_GET['success'])) {
    $successKey = trim((string) $_GET['success']);
    $successMap = [
        'created' => 'Gasto registrado correctamente.',
        'status' => 'Estado del gasto actualizado.',
    ];
    $successMessage = $successMap[$successKey] ?? null;
}

if (isset($_GET['error'])) {
    $errorKey = trim((string) $_GET['error']);
    $errorMap = [
        'missing' => 'El gasto seleccionado ya no existe.',
        'save' => 'No se pudo guardar el gasto.',
    ];
    if (isset($errorMap[$errorKey])) {
        $errors[] = $errorMap[$errorKey];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connection instanceof PDO) {
    $action = trim((string) ($_POST['action'] ?? 'create_expense'));

    if ($action === 'create_expense') {
        $formData = [
            'fecha_gasto' => trim((string) ($_POST['fecha_gasto'] ?? date('Y-m-d'))),
            'categoria' => trim((string) ($_POST['categoria'] ?? '')),
            'concepto' => trim((string) ($_POST['concepto'] ?? '')),
            'monto' => trim((string) ($_POST['monto'] ?? '')),
            'metodo_pago' => trim((string) ($_POST['metodo_pago'] ?? '')),
            'referencia' => trim((string) ($_POST['referencia'] ?? '')),
            'estado' => strtoupper(trim((string) ($_POST['estado'] ?? 'PAGADO'))),
            'observacion' => trim((string) ($_POST['observacion'] ?? '')),
        ];

        $amount = filter_var(str_replace(',', '.', $formData['monto']), FILTER_VALIDATE_FLOAT);
        $allowedStatuses = ['PAGADO', 'PENDIENTE', 'ANULADO'];

        if ($formData['categoria'] === '') {
            $errors[] = 'Ingresa una categoria para el gasto.';
        }

        if ($formData['concepto'] === '') {
            $errors[] = 'Ingresa el concepto del gasto.';
        }

        if ($amount === false || $amount <= 0) {
            $errors[] = 'Ingresa un monto valido.';
        }

        if (!in_array($formData['estado'], $allowedStatuses, true)) {
            $errors[] = 'Selecciona un estado valido para el gasto.';
        }

        if ($errors === []) {
            try {
                $statement = $connection->prepare(
                    'INSERT INTO gastos (fecha_gasto, categoria, concepto, monto, metodo_pago, referencia, estado, observacion)
                     VALUES (:fecha_gasto, :categoria, :concepto, :monto, :metodo_pago, :referencia, :estado, :observacion)'
                );
                $statement->execute([
                    'fecha_gasto' => $formData['fecha_gasto'],
                    'categoria' => $formData['categoria'],
                    'concepto' => $formData['concepto'],
                    'monto' => $amount,
                    'metodo_pago' => $formData['metodo_pago'] !== '' ? $formData['metodo_pago'] : null,
                    'referencia' => $formData['referencia'] !== '' ? $formData['referencia'] : null,
                    'estado' => $formData['estado'],
                    'observacion' => $formData['observacion'] !== '' ? $formData['observacion'] : null,
                ]);

                adminAuthRedirect(adminExpenseBuildUrl(['success' => 'created']));
            } catch (Throwable $exception) {
                $errors[] = 'No se pudo guardar el gasto. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'change_status') {
        $expenseId = filter_var($_POST['expense_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $nextStatus = strtoupper(trim((string) ($_POST['next_status'] ?? '')));
        $allowedStatuses = ['PAGADO', 'PENDIENTE', 'ANULADO'];

        if ($expenseId <= 0 || !in_array($nextStatus, $allowedStatuses, true)) {
            adminAuthRedirect(adminExpenseBuildUrl(['error' => 'missing']));
        }

        $updateStatement = $connection->prepare('UPDATE gastos SET estado = :estado WHERE id_gasto = :id_gasto');
        $updateStatement->execute([
            'estado' => $nextStatus,
            'id_gasto' => $expenseId,
        ]);

        adminAuthRedirect(adminExpenseBuildUrl(['success' => 'status']));
    }
}

$expenseSnapshot = adminPanelExpenseSnapshot();
$expenseMetrics = $expenseSnapshot['metrics'];
$expenseCategorySummary = $expenseSnapshot['by_category'];
$recentExpenses = $expenseSnapshot['recent_expenses'];
$operationsSnapshot = $expenseSnapshot['operations'];
$operationsMetrics = $operationsSnapshot['metrics'];
$inventoryAlerts = $operationsSnapshot['inventory_alerts'];
$shippingByMethod = $operationsSnapshot['shipping_by_method'];

$pageTitle = 'Admin Gastos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Gastos y salidas operativas</h1>
        <p class="lead">Registra gastos y controla salidas del negocio.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Gastos registrados</span>
            <strong><?php echo (int) $expenseMetrics['total_expenses']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Total contabilizado</span>
            <strong>S/ <?php echo number_format((float) $expenseMetrics['paid_amount'], 2); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Pendiente</span>
            <strong>S/ <?php echo number_format((float) $expenseMetrics['pending_amount'], 2); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Ultimos 30 dias</span>
            <strong>S/ <?php echo number_format((float) $expenseMetrics['amount_last_30_days'], 2); ?></strong>
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
                <h2 class="section-title">Gastos recientes</h2>
                <p class="admin-help-text">Guarda concepto, monto, método y estado.</p>
            </div>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/compras/index.php">Ver reposicion</a>
        </div>

        <?php if ($recentExpenses !== []): ?>
            <div class="admin-table-wrap">
                <table class="table admin-table-cardify">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Categoria</th>
                            <th>Concepto</th>
                            <th>Metodo</th>
                            <th>Estado</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentExpenses as $expense): ?>
                            <tr>
                                <td data-label="Fecha"><?php echo htmlspecialchars((string) ($expense['fecha_gasto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Categoria"><?php echo htmlspecialchars($expense['categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Concepto">
                                    <?php echo htmlspecialchars($expense['concepto'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($expense['referencia'])): ?>
                                        <small class="admin-inline-note"><?php echo htmlspecialchars((string) $expense['referencia'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Metodo"><?php echo htmlspecialchars((string) ($expense['metodo_pago'] ?? 'Sin definir'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Estado"><?php echo htmlspecialchars($expense['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Monto">S/ <?php echo number_format((float) ($expense['monto'] ?? 0), 2); ?></td>
                                <td data-label="Acciones">
                                    <div class="admin-table-actions">
                                        <?php foreach (['PAGADO', 'PENDIENTE', 'ANULADO'] as $status): ?>
                                            <?php if ($status !== (string) $expense['estado']): ?>
                                                <form action="" method="post">
                                                    <input type="hidden" name="action" value="change_status">
                                                    <input type="hidden" name="expense_id" value="<?php echo (int) $expense['id_gasto']; ?>">
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
                <strong>Todavia no hay gastos registrados.</strong>
                <p>Usa el formulario lateral para empezar el control de egresos del negocio.</p>
            </div>
        <?php endif; ?>

        <div class="admin-section-stack">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title-small">Resumen por categoria</h2>
                    <p class="admin-help-text">Muestra en qué rubros gastas más.</p>
                </div>
            </div>

            <?php if ($expenseCategorySummary !== []): ?>
                <div class="admin-summary-grid">
                    <?php foreach ($expenseCategorySummary as $expenseCategory): ?>
                        <article class="admin-summary-card">
                            <span><?php echo htmlspecialchars($expenseCategory['categoria'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?php echo (int) ($expenseCategory['expense_count'] ?? 0); ?> movimientos</strong>
                            <p>S/ <?php echo number_format((float) ($expenseCategory['total_amount'] ?? 0), 2); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item">
                    <strong>Aun no hay resumen por categoria.</strong>
                    <p>Se generara conforme registres gastos en distintas areas.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-section-stack">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title-small">Alertas operativas</h2>
                    <p class="admin-help-text">Ayuda a anticipar salidas por stock o entregas.</p>
                </div>
            </div>

            <?php if ($inventoryAlerts !== []): ?>
                <div class="admin-insight-list">
                    <?php foreach ($inventoryAlerts as $alert): ?>
                        <article class="admin-insight-card">
                            <div class="admin-insight-head">
                                <div>
                                    <h3><?php echo htmlspecialchars($alert['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="admin-insight-copy"><?php echo htmlspecialchars($alert['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) ($alert['codigo_producto'] ?? 'Sin codigo'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="admin-status-badge <?php echo (int) ($alert['stock_actual'] ?? 0) === 0 ? 'admin-status-badge-hidden' : 'admin-status-badge-visible'; ?>">Stock: <?php echo (int) ($alert['stock_actual'] ?? 0); ?></span>
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
                <h2 class="section-title section-title-small">Registrar gasto</h2>
                <p class="admin-help-text">Déjalo pagado, pendiente o anulado.</p>
            </div>
        </div>

        <form class="admin-form-grid" action="" method="post">
            <input type="hidden" name="action" value="create_expense">

            <label>
                Fecha
                <input type="date" name="fecha_gasto" value="<?php echo htmlspecialchars($formData['fecha_gasto'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Estado
                <select name="estado">
                    <?php foreach (['PAGADO', 'PENDIENTE', 'ANULADO'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $formData['estado'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="admin-field-span-2">
                Categoria
                <input type="text" name="categoria" value="<?php echo htmlspecialchars($formData['categoria'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ejemplo: Envio, publicidad, servicios" required>
            </label>

            <label class="admin-field-span-2">
                Concepto
                <input type="text" name="concepto" value="<?php echo htmlspecialchars($formData['concepto'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Monto
                <input type="number" name="monto" min="0.01" step="0.01" value="<?php echo htmlspecialchars($formData['monto'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Metodo de pago
                <input type="text" name="metodo_pago" value="<?php echo htmlspecialchars($formData['metodo_pago'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label class="admin-field-span-2">
                Referencia
                <input type="text" name="referencia" value="<?php echo htmlspecialchars($formData['referencia'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label class="admin-field-span-2">
                Observacion
                <textarea name="observacion" rows="4"><?php echo htmlspecialchars($formData['observacion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>

            <button class="button admin-field-span-2" type="submit">Guardar gasto</button>
        </form>

        <div class="admin-section-stack">
            <div>
                <h3 class="section-title-small">Carga de envios</h3>
            </div>

            <?php if ($shippingByMethod !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($shippingByMethod as $shipping): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars((string) ($shipping['empresa_envio'] ?? 'Sin definir'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo (int) ($shipping['total_envios'] ?? 0); ?> envios | Pendientes: <?php echo (int) ($shipping['pending_envios'] ?? 0); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item admin-note-item-success">
                    <strong>No hay carga de envios pendiente.</strong>
                    <p>Los pedidos con entrega llenaran este bloque automaticamente.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-quick-links">
            <a class="button" href="<?php echo BASE_URL; ?>/admin/pedidos/index.php">Revisar pedidos</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/compras/index.php">Ir a compras</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Volver al dashboard</a>
        </div>
    </aside>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
