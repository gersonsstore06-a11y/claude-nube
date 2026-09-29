<?php
require_once __DIR__ . '/../config/conexion.php';

function adminPanelConnection(): ?PDO
{
    static $connection = null;
    static $attempted = false;

    if ($attempted) {
        return $connection;
    }

    $attempted = true;

    try {
        $connection = (new Conexion())->getConexion();
    } catch (Throwable $exception) {
        $connection = null;
    }

    return $connection;
}

function adminPanelFetchAll(string $sql, array $params = []): array
{
    $connection = adminPanelConnection();

    if (!$connection) {
        return [];
    }

    try {
        $statement = $connection->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function adminPanelFetchOne(string $sql, array $params = []): array
{
    $rows = adminPanelFetchAll($sql, $params);

    return $rows[0] ?? [];
}

function adminPanelCategorySnapshot(): array
{
    $categories = adminPanelFetchAll(
        "SELECT
            c.id_categoria,
            c.nombre,
            c.descripcion,
            c.estado,
            COUNT(p.id_producto) AS product_count,
            COALESCE(SUM(COALESCE(i.stock_actual, 0)), 0) AS stock_total,
            COALESCE(SUM(CASE
                WHEN p.promocion_activa = 1
                 AND p.precio_promocional IS NOT NULL
                 AND p.precio_promocional > 0
                 AND p.precio_promocional < p.precio_venta
                THEN 1
                ELSE 0
            END), 0) AS promo_count
         FROM categorias c
         LEFT JOIN productos p ON p.id_categoria = c.id_categoria
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         GROUP BY c.id_categoria, c.nombre, c.descripcion, c.estado
         ORDER BY c.estado DESC, product_count DESC, c.nombre ASC"
    );

    $metrics = [
        'active' => 0,
        'with_products' => 0,
        'empty' => 0,
        'promotions' => 0,
    ];

    foreach ($categories as $category) {
        if ((int) ($category['estado'] ?? 0) === 1) {
            $metrics['active']++;
        }

        if ((int) ($category['product_count'] ?? 0) > 0) {
            $metrics['with_products']++;
        } else {
            $metrics['empty']++;
        }

        $metrics['promotions'] += (int) ($category['promo_count'] ?? 0);
    }

    return [
        'metrics' => $metrics,
        'categories' => $categories,
        'empty_categories' => array_values(array_filter(
            $categories,
            static fn (array $category): bool => (int) ($category['product_count'] ?? 0) === 0
        )),
    ];
}

function adminPanelProcurementSnapshot(): array
{
    $inventorySummary = adminPanelFetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN COALESCE(i.stock_actual, 0) = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
            COALESCE(SUM(CASE WHEN COALESCE(i.stock_actual, 0) BETWEEN 1 AND 3 THEN 1 ELSE 0 END), 0) AS low_stock,
            COALESCE(SUM(CASE WHEN COALESCE(i.stock_actual, 0) >= 4 THEN 1 ELSE 0 END), 0) AS healthy_stock
         FROM productos p
         LEFT JOIN inventario i ON i.id_producto = p.id_producto"
    );

    $pendingOrders = adminPanelFetchOne(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(total), 0) AS amount
         FROM pedidos
         WHERE estado IN ('PENDIENTE', 'PAGADO', 'PREPARANDO')"
    );

    $unitsLast30Days = adminPanelFetchOne(
        "SELECT COALESCE(SUM(pd.cantidad), 0) AS units
         FROM pedido_detalle pd
         INNER JOIN pedidos p ON p.id_pedido = pd.id_pedido
         WHERE p.estado <> 'CANCELADO'
           AND p.fecha_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
    );

    $replenishmentProducts = adminPanelFetchAll(
        "SELECT
            p.id_producto,
            p.nombre,
            p.codigo_producto,
            p.estado,
            c.nombre AS categoria_nombre,
            COALESCE(i.stock_actual, 0) AS stock_actual,
            p.fecha_creacion
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         ORDER BY (COALESCE(i.stock_actual, 0) = 0) DESC, COALESCE(i.stock_actual, 0) ASC, p.fecha_creacion DESC
         LIMIT 8"
    );

    $demandByCategory = adminPanelFetchAll(
        "SELECT
            c.nombre AS categoria_nombre,
            COALESCE(SUM(pd.cantidad), 0) AS units_sold,
            COUNT(DISTINCT pd.id_pedido) AS orders_count,
            COALESCE(SUM(pd.subtotal), 0) AS revenue
         FROM pedido_detalle pd
         INNER JOIN pedidos pe ON pe.id_pedido = pd.id_pedido
         INNER JOIN productos p ON p.id_producto = pd.id_producto
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE pe.estado <> 'CANCELADO'
         GROUP BY c.id_categoria, c.nombre
         ORDER BY units_sold DESC, revenue DESC
         LIMIT 6"
    );

    $recentPendingOrders = adminPanelFetchAll(
        "SELECT
            p.numero_pedido,
            p.estado,
            p.total,
            p.fecha_pedido,
            CONCAT(u.nombre, ' ', u.apellido) AS customer_name
         FROM pedidos p
         INNER JOIN clientes c ON c.id_cliente = p.id_cliente
         INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
         WHERE p.estado IN ('PENDIENTE', 'PAGADO', 'PREPARANDO')
         ORDER BY p.fecha_pedido DESC, p.id_pedido DESC
         LIMIT 6"
    );

    return [
        'metrics' => [
            'out_of_stock' => (int) ($inventorySummary['out_of_stock'] ?? 0),
            'low_stock' => (int) ($inventorySummary['low_stock'] ?? 0),
            'pending_orders' => (int) ($pendingOrders['total'] ?? 0),
            'units_last_30_days' => (int) ($unitsLast30Days['units'] ?? 0),
            'pending_amount' => (float) ($pendingOrders['amount'] ?? 0),
        ],
        'replenishment_products' => $replenishmentProducts,
        'demand_by_category' => $demandByCategory,
        'recent_pending_orders' => $recentPendingOrders,
    ];
}

function adminPanelOperationsSnapshot(): array
{
    $shippingSummary = adminPanelFetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS pending_shipments,
            COALESCE(SUM(CASE WHEN estado = 'EN_TRANSITO' THEN 1 ELSE 0 END), 0) AS in_transit_shipments,
            COALESCE(SUM(CASE WHEN estado = 'ENTREGADO' THEN 1 ELSE 0 END), 0) AS delivered_shipments,
            COUNT(*) AS total_shipments
         FROM envios"
    );

    $orderSummary = adminPanelFetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN estado = 'PREPARANDO' THEN 1 ELSE 0 END), 0) AS preparing_orders,
            COALESCE(SUM(CASE WHEN estado IN ('PENDIENTE', 'PAGADO', 'PREPARANDO', 'ENVIADO') THEN total ELSE 0 END), 0) AS committed_amount
         FROM pedidos"
    );

    $inventoryAlerts = adminPanelFetchAll(
        "SELECT
            p.nombre,
            p.codigo_producto,
            c.nombre AS categoria_nombre,
            COALESCE(i.stock_actual, 0) AS stock_actual
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         WHERE COALESCE(i.stock_actual, 0) <= 2
         ORDER BY COALESCE(i.stock_actual, 0) ASC, p.nombre ASC
         LIMIT 6"
    );

    $shippingByMethod = adminPanelFetchAll(
        "SELECT
            empresa_envio,
            COUNT(*) AS total_envios,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS pending_envios,
            COALESCE(SUM(CASE WHEN estado = 'EN_TRANSITO' THEN 1 ELSE 0 END), 0) AS transit_envios
         FROM envios
         GROUP BY empresa_envio
         ORDER BY total_envios DESC, empresa_envio ASC
         LIMIT 6"
    );

    $recentOperationalOrders = adminPanelFetchAll(
        "SELECT
            numero_pedido,
            estado,
            total,
            fecha_pedido
         FROM pedidos
         WHERE estado IN ('PENDIENTE', 'PAGADO', 'PREPARANDO', 'ENVIADO')
         ORDER BY fecha_pedido DESC, id_pedido DESC
         LIMIT 6"
    );

    return [
        'metrics' => [
            'pending_shipments' => (int) ($shippingSummary['pending_shipments'] ?? 0),
            'in_transit_shipments' => (int) ($shippingSummary['in_transit_shipments'] ?? 0),
            'preparing_orders' => (int) ($orderSummary['preparing_orders'] ?? 0),
            'committed_amount' => (float) ($orderSummary['committed_amount'] ?? 0),
        ],
        'inventory_alerts' => $inventoryAlerts,
        'shipping_by_method' => $shippingByMethod,
        'recent_operational_orders' => $recentOperationalOrders,
    ];
}

function adminPanelReportSnapshot(): array
{
    $overview = adminPanelFetchOne(
        "SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN estado <> 'CANCELADO' THEN total ELSE 0 END), 0) AS gross_revenue,
            COALESCE(SUM(CASE WHEN estado IN ('PAGADO', 'PREPARANDO', 'ENVIADO', 'ENTREGADO') THEN total ELSE 0 END), 0) AS collected_revenue,
            COALESCE(AVG(CASE WHEN estado <> 'CANCELADO' THEN total END), 0) AS average_ticket,
            COALESCE(SUM(CASE WHEN estado IN ('PENDIENTE', 'PAGADO', 'PREPARANDO') THEN 1 ELSE 0 END), 0) AS pending_orders
         FROM pedidos"
    );

    $lastThirtyDays = adminPanelFetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN estado <> 'CANCELADO' THEN total ELSE 0 END), 0) AS revenue_30_days,
            COUNT(*) AS orders_30_days
         FROM pedidos
         WHERE fecha_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
    );

    $topProducts = adminPanelFetchAll(
        "SELECT
            pr.nombre,
            pr.codigo_producto,
            COALESCE(SUM(pd.cantidad), 0) AS units_sold,
            COALESCE(SUM(pd.subtotal), 0) AS revenue
         FROM pedido_detalle pd
         INNER JOIN pedidos pe ON pe.id_pedido = pd.id_pedido
         INNER JOIN productos pr ON pr.id_producto = pd.id_producto
         WHERE pe.estado <> 'CANCELADO'
         GROUP BY pr.id_producto, pr.nombre, pr.codigo_producto
         ORDER BY units_sold DESC, revenue DESC
         LIMIT 6"
    );

    $topCategories = adminPanelFetchAll(
        "SELECT
            c.nombre AS categoria_nombre,
            COALESCE(SUM(pd.cantidad), 0) AS units_sold,
            COALESCE(SUM(pd.subtotal), 0) AS revenue
         FROM pedido_detalle pd
         INNER JOIN pedidos pe ON pe.id_pedido = pd.id_pedido
         INNER JOIN productos pr ON pr.id_producto = pd.id_producto
         INNER JOIN categorias c ON c.id_categoria = pr.id_categoria
         WHERE pe.estado <> 'CANCELADO'
         GROUP BY c.id_categoria, c.nombre
         ORDER BY revenue DESC, units_sold DESC
         LIMIT 6"
    );

    $statusBreakdown = adminPanelFetchAll(
        "SELECT
            estado,
            COUNT(*) AS total_orders,
            COALESCE(SUM(total), 0) AS total_amount
         FROM pedidos
         GROUP BY estado
         ORDER BY FIELD(estado, 'PENDIENTE', 'PAGADO', 'PREPARANDO', 'ENVIADO', 'ENTREGADO', 'CANCELADO'), estado ASC"
    );

    return [
        'metrics' => [
            'total_orders' => (int) ($overview['total_orders'] ?? 0),
            'gross_revenue' => (float) ($overview['gross_revenue'] ?? 0),
            'collected_revenue' => (float) ($overview['collected_revenue'] ?? 0),
            'average_ticket' => (float) ($overview['average_ticket'] ?? 0),
            'pending_orders' => (int) ($overview['pending_orders'] ?? 0),
            'revenue_30_days' => (float) ($lastThirtyDays['revenue_30_days'] ?? 0),
            'orders_30_days' => (int) ($lastThirtyDays['orders_30_days'] ?? 0),
        ],
        'top_products' => $topProducts,
        'top_categories' => $topCategories,
        'status_breakdown' => $statusBreakdown,
    ];
}

function adminPanelEnsureManagementTables(?PDO $connection = null): bool
{
    $connection = $connection ?? adminPanelConnection();

    if (!$connection) {
        return false;
    }

    static $prepared = false;

    if ($prepared) {
        return true;
    }

    $connection->exec(
        "CREATE TABLE IF NOT EXISTS compras (
            id_compra INT AUTO_INCREMENT PRIMARY KEY,
            proveedor VARCHAR(160) NOT NULL,
            numero_documento VARCHAR(80) NULL,
            fecha_compra DATE NOT NULL,
            estado ENUM('RECIBIDA', 'PENDIENTE', 'CANCELADA') NOT NULL DEFAULT 'RECIBIDA',
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            observacion TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $connection->exec(
        "CREATE TABLE IF NOT EXISTS compra_detalle (
            id_compra_detalle INT AUTO_INCREMENT PRIMARY KEY,
            id_compra INT NOT NULL,
            id_producto INT NOT NULL,
            cantidad INT NOT NULL,
            costo_unitario DECIMAL(12,2) NOT NULL,
            subtotal DECIMAL(12,2) NOT NULL,
            FOREIGN KEY (id_compra) REFERENCES compras(id_compra) ON DELETE CASCADE,
            FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE RESTRICT,
            INDEX idx_compra_detalle_compra (id_compra),
            INDEX idx_compra_detalle_producto (id_producto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $connection->exec(
        "CREATE TABLE IF NOT EXISTS gastos (
            id_gasto INT AUTO_INCREMENT PRIMARY KEY,
            fecha_gasto DATE NOT NULL,
            categoria VARCHAR(120) NOT NULL,
            concepto VARCHAR(180) NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            metodo_pago VARCHAR(80) NULL,
            referencia VARCHAR(120) NULL,
            estado ENUM('PAGADO', 'PENDIENTE', 'ANULADO') NOT NULL DEFAULT 'PAGADO',
            observacion TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gastos_fecha (fecha_gasto),
            INDEX idx_gastos_categoria (categoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $prepared = true;

    return true;
}

function adminPanelPurchaseSnapshot(): array
{
    adminPanelEnsureManagementTables();

    $summary = adminPanelFetchOne(
        "SELECT
            COUNT(*) AS total_purchases,
            COALESCE(SUM(CASE WHEN estado <> 'CANCELADA' THEN total ELSE 0 END), 0) AS invested_amount,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN total ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(CASE WHEN fecha_compra >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND estado <> 'CANCELADA' THEN total ELSE 0 END), 0) AS amount_last_30_days
         FROM compras"
    );

    $units = adminPanelFetchOne(
        "SELECT COALESCE(SUM(CASE WHEN c.estado <> 'CANCELADA' THEN cd.cantidad ELSE 0 END), 0) AS total_units
         FROM compra_detalle cd
         INNER JOIN compras c ON c.id_compra = cd.id_compra"
    );

    $recentPurchases = adminPanelFetchAll(
        "SELECT
            c.id_compra,
            c.proveedor,
            c.numero_documento,
            c.fecha_compra,
            c.estado,
            c.total,
            c.observacion,
            cd.cantidad,
            cd.costo_unitario,
            p.nombre AS producto_nombre,
            p.codigo_producto
         FROM compras c
         INNER JOIN compra_detalle cd ON cd.id_compra = c.id_compra
         INNER JOIN productos p ON p.id_producto = cd.id_producto
         ORDER BY c.fecha_compra DESC, c.id_compra DESC
         LIMIT 10"
    );

    $bySupplier = adminPanelFetchAll(
        "SELECT
            proveedor,
            COUNT(*) AS purchase_count,
            COALESCE(SUM(CASE WHEN estado <> 'CANCELADA' THEN total ELSE 0 END), 0) AS total_amount
         FROM compras
         GROUP BY proveedor
         ORDER BY total_amount DESC, purchase_count DESC
         LIMIT 6"
    );

    $catalogSignals = adminPanelProcurementSnapshot();

    return [
        'metrics' => [
            'total_purchases' => (int) ($summary['total_purchases'] ?? 0),
            'invested_amount' => (float) ($summary['invested_amount'] ?? 0),
            'pending_amount' => (float) ($summary['pending_amount'] ?? 0),
            'amount_last_30_days' => (float) ($summary['amount_last_30_days'] ?? 0),
            'total_units' => (int) ($units['total_units'] ?? 0),
        ],
        'recent_purchases' => $recentPurchases,
        'by_supplier' => $bySupplier,
        'catalog_signals' => $catalogSignals,
    ];
}

function adminPanelExpenseSnapshot(): array
{
    adminPanelEnsureManagementTables();

    $summary = adminPanelFetchOne(
        "SELECT
            COUNT(*) AS total_expenses,
            COALESCE(SUM(CASE WHEN estado <> 'ANULADO' THEN monto ELSE 0 END), 0) AS paid_amount,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN monto ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(CASE WHEN fecha_gasto >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND estado <> 'ANULADO' THEN monto ELSE 0 END), 0) AS amount_last_30_days
         FROM gastos"
    );

    $byCategory = adminPanelFetchAll(
        "SELECT
            categoria,
            COUNT(*) AS expense_count,
            COALESCE(SUM(CASE WHEN estado <> 'ANULADO' THEN monto ELSE 0 END), 0) AS total_amount
         FROM gastos
         GROUP BY categoria
         ORDER BY total_amount DESC, expense_count DESC
         LIMIT 6"
    );

    $recentExpenses = adminPanelFetchAll(
        "SELECT
            id_gasto,
            fecha_gasto,
            categoria,
            concepto,
            monto,
            metodo_pago,
            referencia,
            estado,
            observacion
         FROM gastos
         ORDER BY fecha_gasto DESC, id_gasto DESC
         LIMIT 10"
    );

    $operations = adminPanelOperationsSnapshot();

    return [
        'metrics' => [
            'total_expenses' => (int) ($summary['total_expenses'] ?? 0),
            'paid_amount' => (float) ($summary['paid_amount'] ?? 0),
            'pending_amount' => (float) ($summary['pending_amount'] ?? 0),
            'amount_last_30_days' => (float) ($summary['amount_last_30_days'] ?? 0),
        ],
        'by_category' => $byCategory,
        'recent_expenses' => $recentExpenses,
        'operations' => $operations,
    ];
}