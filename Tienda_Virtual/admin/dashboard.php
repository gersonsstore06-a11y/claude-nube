<?php
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../includes/storefront_data.php';
require_once __DIR__ . '/../includes/admin_panel_data.php';

adminAuthRequireLogin();

$metrics = storefrontMetrics();
$categorySnapshot = adminPanelCategorySnapshot();
$procurementSnapshot = adminPanelProcurementSnapshot();
$operationsSnapshot = adminPanelOperationsSnapshot();
$reportSnapshot = adminPanelReportSnapshot();

$categoryMetrics = $categorySnapshot['metrics'] ?? [];
$procurementMetrics = $procurementSnapshot['metrics'] ?? [];
$operationsMetrics = $operationsSnapshot['metrics'] ?? [];
$reportMetrics = $reportSnapshot['metrics'] ?? [];

$pendingOrders = (int) ($procurementMetrics['pending_orders'] ?? 0);
$inventoryAlerts = array_slice($operationsSnapshot['inventory_alerts'] ?? [], 0, 4);
$recentPendingOrders = array_slice($procurementSnapshot['recent_pending_orders'] ?? [], 0, 4);
$emptyCategories = array_slice($categorySnapshot['empty_categories'] ?? [], 0, 4);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel administrativo</span>
        <h1 class="page-title">Resumen operativo de la tienda</h1>
        <p class="lead">Control rapido de catalogo, ventas, stock y pedidos desde un solo lugar.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Categorias activas</span>
            <strong><?php echo (int) ($categoryMetrics['active'] ?? $metrics['categorias']); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Productos activos</span>
            <strong><?php echo (int) $metrics['productos']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Stock</span>
            <strong><?php echo (int) $metrics['stock']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Pedidos por atender</span>
            <strong><?php echo $pendingOrders; ?></strong>
        </article>
    </div>
</section>

<section class="admin-layout">
    <div class="page-section admin-products-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title">Estado general del panel</h2>
                <p class="admin-help-text">Indicadores reales del catalogo, flujo de pedidos y operacion diaria.</p>
            </div>
        </div>

        <div class="admin-summary-grid">
            <article class="admin-summary-card">
                <span>Ingresos cobrados</span>
                <strong>S/ <?php echo number_format((float) ($reportMetrics['collected_revenue'] ?? 0), 2); ?></strong>
                <p>Pedidos ya cobrados o en proceso de entrega.</p>
            </article>
            <article class="admin-summary-card">
                <span>Monto comprometido</span>
                <strong>S/ <?php echo number_format((float) ($operationsMetrics['committed_amount'] ?? 0), 2); ?></strong>
                <p>Ventas activas entre pendiente, pagado, preparando y enviado.</p>
            </article>
            <article class="admin-summary-card">
                <span>Stock critico</span>
                <strong><?php echo (int) ($procurementMetrics['out_of_stock'] ?? 0) + (int) ($procurementMetrics['low_stock'] ?? 0); ?></strong>
                <p><?php echo (int) ($procurementMetrics['out_of_stock'] ?? 0); ?> sin stock y <?php echo (int) ($procurementMetrics['low_stock'] ?? 0); ?> con nivel bajo.</p>
            </article>
            <article class="admin-summary-card">
                <span>Envios activos</span>
                <strong><?php echo (int) ($operationsMetrics['pending_shipments'] ?? 0) + (int) ($operationsMetrics['in_transit_shipments'] ?? 0); ?></strong>
                <p><?php echo (int) ($operationsMetrics['pending_shipments'] ?? 0); ?> pendientes y <?php echo (int) ($operationsMetrics['in_transit_shipments'] ?? 0); ?> en transito.</p>
            </article>
        </div>

        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Pulso comercial</h2>
                <p class="admin-help-text">Lectura rapida del movimiento reciente de la tienda.</p>
            </div>

            <div class="admin-summary-grid">
                <article class="admin-summary-card">
                    <span>Pedidos en 30 dias</span>
                    <strong><?php echo (int) ($reportMetrics['orders_30_days'] ?? 0); ?></strong>
                    <p>Ingreso del periodo: S/ <?php echo number_format((float) ($reportMetrics['revenue_30_days'] ?? 0), 2); ?></p>
                </article>
                <article class="admin-summary-card">
                    <span>Ticket promedio</span>
                    <strong>S/ <?php echo number_format((float) ($reportMetrics['average_ticket'] ?? 0), 2); ?></strong>
                    <p>Promedio calculado sobre pedidos no cancelados.</p>
                </article>
                <article class="admin-summary-card">
                    <span>Unidades movidas</span>
                    <strong><?php echo (int) ($procurementMetrics['units_last_30_days'] ?? 0); ?></strong>
                    <p>Productos vendidos en los ultimos 30 dias.</p>
                </article>
                <article class="admin-summary-card">
                    <span>Categorias vacias</span>
                    <strong><?php echo (int) ($categoryMetrics['empty'] ?? 0); ?></strong>
                    <p>Espacios listos para completar con nuevos productos.</p>
                </article>
            </div>
        </div>

        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Pedidos que requieren accion</h2>
                <p class="admin-help-text">Ultimos pedidos abiertos para preparar o despachar.</p>
            </div>

            <?php if ($recentPendingOrders !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($recentPendingOrders as $order): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars((string) ($order['numero_pedido'] ?? 'Sin numero'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo htmlspecialchars((string) ($order['customer_name'] ?? 'Cliente no disponible'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><?php echo htmlspecialchars((string) ($order['estado'] ?? 'PENDIENTE'), ENT_QUOTES, 'UTF-8'); ?> | S/ <?php echo number_format((float) ($order['total'] ?? 0), 2); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item admin-note-item-success">
                    <strong>No hay pedidos pendientes por ahora.</strong>
                    <p>Cuando entren nuevos pedidos abiertos apareceran aqui para seguimiento rapido.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="page-section admin-side-panel">
        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Alertas de inventario</h2>
            </div>

            <?php if ($inventoryAlerts !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($inventoryAlerts as $product): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars((string) ($product['nombre'] ?? 'Producto'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo htmlspecialchars((string) ($product['categoria_nombre'] ?? 'Sin categoria'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Stock actual: <?php echo (int) ($product['stock_actual'] ?? 0); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item admin-note-item-success">
                    <strong>Inventario estable.</strong>
                    <p>No hay productos con stock critico en este momento.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Categorias por completar</h2>
            </div>

            <?php if ($emptyCategories !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($emptyCategories as $category): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars((string) ($category['nombre'] ?? 'Categoria'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo htmlspecialchars((string) ($category['descripcion'] ?? 'Sin descripcion breve'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item admin-note-item-success">
                    <strong>Todas las categorias tienen productos.</strong>
                    <p>El catalogo ya esta distribuido sin espacios vacios visibles.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-quick-links">
            <a class="button" href="<?php echo BASE_URL; ?>/admin/pedidos/index.php">Gestionar pedidos</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Gestionar productos</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/reportes/index.php">Ver reportes</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/compras/index.php">Registrar compras</a>
        </div>
    </aside>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
