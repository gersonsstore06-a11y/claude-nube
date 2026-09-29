<?php
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../includes/admin_panel_data.php';

adminAuthRequireLogin();

$reportSnapshot = adminPanelReportSnapshot();
$reportMetrics = $reportSnapshot['metrics'];
$topProducts = $reportSnapshot['top_products'];
$topCategories = $reportSnapshot['top_categories'];
$statusBreakdown = $reportSnapshot['status_breakdown'];

$pageTitle = 'Admin Reportes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Reportes y analitica comercial</h1>
        <p class="lead">Resumen de ventas, ticket, pedidos y rotación.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Pedidos totales</span>
            <strong><?php echo (int) $reportMetrics['total_orders']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Ingresos 30 dias</span>
            <strong>S/ <?php echo number_format((float) $reportMetrics['revenue_30_days'], 2); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Ticket promedio</span>
            <strong>S/ <?php echo number_format((float) $reportMetrics['average_ticket'], 2); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Pedidos pendientes</span>
            <strong><?php echo (int) $reportMetrics['pending_orders']; ?></strong>
        </article>
    </div>
</section>

<section class="admin-layout">
    <div class="page-section admin-products-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title">Estado general del negocio</h2>
                <p class="admin-help-text">Resumen monetario basado en pedidos guardados.</p>
            </div>
        </div>

        <div class="admin-summary-grid">
            <article class="admin-summary-card">
                <span>Ingresos acumulados</span>
                <strong>S/ <?php echo number_format((float) $reportMetrics['gross_revenue'], 2); ?></strong>
                <p>Pedidos validos registrados: <?php echo (int) $reportMetrics['total_orders']; ?></p>
            </article>
            <article class="admin-summary-card">
                <span>Ingresos cobrados</span>
                <strong>S/ <?php echo number_format((float) $reportMetrics['collected_revenue'], 2); ?></strong>
                <p>Incluye pedidos pagados, preparando, enviados y entregados.</p>
            </article>
            <article class="admin-summary-card">
                <span>Pedidos en 30 dias</span>
                <strong><?php echo (int) $reportMetrics['orders_30_days']; ?></strong>
                <p>Ingreso del periodo: S/ <?php echo number_format((float) $reportMetrics['revenue_30_days'], 2); ?></p>
            </article>
        </div>

        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Desglose por estado</h2>
                <p class="admin-help-text">Ayuda a detectar cuellos de botella.</p>
            </div>

            <?php if ($statusBreakdown !== []): ?>
                <div class="admin-table-wrap">
                    <table class="table admin-table-cardify">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Pedidos</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusBreakdown as $status): ?>
                                <tr>
                                    <td data-label="Estado"><?php echo htmlspecialchars($status['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Pedidos"><?php echo (int) ($status['total_orders'] ?? 0); ?></td>
                                    <td data-label="Total">S/ <?php echo number_format((float) ($status['total_amount'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-note-item">
                    <strong>Aun no hay pedidos suficientes para generar este reporte.</strong>
                    <p>El desglose por estado aparecera cuando existan pedidos guardados desde el carrito.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="page-section admin-side-panel">
        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Productos mas vendidos</h2>
            </div>

            <?php if ($topProducts !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($topProducts as $product): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo htmlspecialchars((string) ($product['codigo_producto'] ?? 'Sin codigo'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><?php echo (int) ($product['units_sold'] ?? 0); ?> uds | S/ <?php echo number_format((float) ($product['revenue'] ?? 0), 2); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item">
                    <strong>Sin ranking de productos por ahora.</strong>
                    <p>Esta lista se completara a medida que entren pedidos con detalle de productos.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-section-stack">
            <div>
                <h2 class="section-title section-title-small">Categorias con mayor ingreso</h2>
            </div>

            <?php if ($topCategories !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($topCategories as $category): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars($category['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo (int) ($category['units_sold'] ?? 0); ?> uds | S/ <?php echo number_format((float) ($category['revenue'] ?? 0), 2); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item">
                    <strong>Sin ranking de categorias todavia.</strong>
                    <p>Se activara cuando exista suficiente historial de ventas.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-quick-links">
            <a class="button" href="<?php echo BASE_URL; ?>/admin/pedidos/index.php">Analizar pedidos</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Analizar productos</a>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Volver al dashboard</a>
        </div>
    </aside>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
