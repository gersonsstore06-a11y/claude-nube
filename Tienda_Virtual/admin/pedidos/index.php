<?php
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../config/conexion.php';

adminAuthRequireLogin();

const ADMIN_ORDER_PAGE_SIZE = 10;

function adminOrderStatuses(): array
{
    return ['PENDIENTE', 'PAGADO', 'PREPARANDO', 'ENVIADO', 'ENTREGADO', 'CANCELADO'];
}

function adminOrderShippingStatus(string $orderStatus): ?string
{
    return match ($orderStatus) {
        'ENVIADO' => 'EN_TRANSITO',
        'ENTREGADO' => 'ENTREGADO',
        'CANCELADO' => 'DEVUELTO',
        'PENDIENTE', 'PAGADO', 'PREPARANDO' => 'PENDIENTE',
        default => null,
    };
}

function adminOrderBuildUrl(array $overrides = []): string
{
    $params = [];
    $currentView = trim((string) ($_GET['vista'] ?? 'activos'));
    $currentStatus = trim((string) ($_GET['estado'] ?? ''));
    $currentSearch = trim((string) ($_GET['buscar'] ?? ''));
    $currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

    if ($currentView !== '' && $currentView !== 'activos') {
        $params['vista'] = $currentView;
    }

    if ($currentStatus !== '') {
        $params['estado'] = $currentStatus;
    }

    if ($currentSearch !== '') {
        $params['buscar'] = $currentSearch;
    }

    if ($currentPage > 1) {
        $params['page'] = $currentPage;
    }

    $params = array_merge($params, $overrides);

    if (($params['estado'] ?? '') === '') {
        unset($params['estado']);
    }

    if (($params['buscar'] ?? '') === '') {
        unset($params['buscar']);
    }

    if (($params['vista'] ?? 'activos') === 'activos') {
        unset($params['vista']);
    }

    if ((int) ($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }

    return adminAuthBaseUrl() . '/admin/pedidos/index.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function adminOrderSyncShipping(PDO $connection, int $orderId, string $orderStatus): void
{
    $shippingStatus = adminOrderShippingStatus($orderStatus);

    if ($shippingStatus === null) {
        return;
    }

    $shippingStatement = $connection->prepare('SELECT id_envio FROM envios WHERE id_pedido = :id_pedido LIMIT 1');
    $shippingStatement->execute(['id_pedido' => $orderId]);

    if ($shippingStatement->fetchColumn() === false) {
        return;
    }

    $updateShipping = $connection->prepare('UPDATE envios SET estado = :estado WHERE id_pedido = :id_pedido');
    $updateShipping->execute([
        'estado' => $shippingStatus,
        'id_pedido' => $orderId,
    ]);
}

function adminOrderEnsureTrashColumns(PDO $connection): void
{
    $trashColumn = $connection->query("SHOW COLUMNS FROM pedidos LIKE 'en_papelera'")->fetch();
    if ($trashColumn === false) {
        $connection->exec('ALTER TABLE pedidos ADD COLUMN en_papelera TINYINT(1) NOT NULL DEFAULT 0');
    }

    $trashDateColumn = $connection->query("SHOW COLUMNS FROM pedidos LIKE 'fecha_papelera'")->fetch();
    if ($trashDateColumn === false) {
        $connection->exec('ALTER TABLE pedidos ADD COLUMN fecha_papelera DATETIME NULL DEFAULT NULL');
    }
}

function adminOrderNormalizeView(string $view): string
{
    return $view === 'papelera' ? 'papelera' : 'activos';
}

function adminOrderSelectedIds(array $rawIds): array
{
    $selectedIds = array_values(array_unique(array_filter(
        array_map('intval', $rawIds),
        static fn (int $orderId): bool => $orderId > 0
    )));

    return $selectedIds;
}

function adminOrderLogStatusChange(PDO $connection, int $orderId, string $nextStatus): void
{
    $logStatement = $connection->prepare(
        'INSERT INTO bitacora (tabla_afectada, accion, id_registro, descripcion, usuario_bd)
         VALUES (:tabla_afectada, :accion, :id_registro, :descripcion, :usuario_bd)'
    );
    $logStatement->execute([
        'tabla_afectada' => 'pedidos',
        'accion' => 'ACTUALIZAR',
        'id_registro' => (string) $orderId,
        'descripcion' => 'Estado del pedido actualizado a ' . $nextStatus . ' desde el panel admin.',
        'usuario_bd' => 'tienda_virtual_web',
    ]);
}

function adminOrderFetchById(PDO $connection, int $orderId): ?array
{
    $statement = $connection->prepare(
        'SELECT id_pedido, numero_pedido, en_papelera
         FROM pedidos
         WHERE id_pedido = :id_pedido
         LIMIT 1'
    );
    $statement->execute(['id_pedido' => $orderId]);
    $order = $statement->fetch();

    return $order !== false ? $order : null;
}

function adminOrderLogDeletion(PDO $connection, int $orderId, string $orderNumber): void
{
    $logStatement = $connection->prepare(
        'INSERT INTO bitacora (tabla_afectada, accion, id_registro, descripcion, usuario_bd)
         VALUES (:tabla_afectada, :accion, :id_registro, :descripcion, :usuario_bd)'
    );
    $logStatement->execute([
        'tabla_afectada' => 'pedidos',
        'accion' => 'ELIMINAR',
        'id_registro' => (string) $orderId,
        'descripcion' => 'Pedido ' . $orderNumber . ' eliminado desde el panel admin.',
        'usuario_bd' => 'tienda_virtual_web',
    ]);
}

function adminOrderLogTrashMove(PDO $connection, int $orderId, string $orderNumber): void
{
    $logStatement = $connection->prepare(
        'INSERT INTO bitacora (tabla_afectada, accion, id_registro, descripcion, usuario_bd)
         VALUES (:tabla_afectada, :accion, :id_registro, :descripcion, :usuario_bd)'
    );
    $logStatement->execute([
        'tabla_afectada' => 'pedidos',
        'accion' => 'ACTUALIZAR',
        'id_registro' => (string) $orderId,
        'descripcion' => 'Pedido ' . $orderNumber . ' movido a la papelera desde el panel admin.',
        'usuario_bd' => 'tienda_virtual_web',
    ]);
}

function adminOrderLogRestore(PDO $connection, int $orderId, string $orderNumber): void
{
    $logStatement = $connection->prepare(
        'INSERT INTO bitacora (tabla_afectada, accion, id_registro, descripcion, usuario_bd)
         VALUES (:tabla_afectada, :accion, :id_registro, :descripcion, :usuario_bd)'
    );
    $logStatement->execute([
        'tabla_afectada' => 'pedidos',
        'accion' => 'ACTUALIZAR',
        'id_registro' => (string) $orderId,
        'descripcion' => 'Pedido ' . $orderNumber . ' restaurado de la papelera desde el panel admin.',
        'usuario_bd' => 'tienda_virtual_web',
    ]);
}

function adminOrderDelete(PDO $connection, int $orderId): ?string
{
    $order = adminOrderFetchById($connection, $orderId);

    if ($order === null) {
        return null;
    }

    $deleteShipping = $connection->prepare('DELETE FROM envios WHERE id_pedido = :id_pedido');
    $deleteDetails = $connection->prepare('DELETE FROM pedido_detalle WHERE id_pedido = :id_pedido');
    $deleteOrder = $connection->prepare('DELETE FROM pedidos WHERE id_pedido = :id_pedido');

    $deleteShipping->execute(['id_pedido' => $orderId]);
    $deleteDetails->execute(['id_pedido' => $orderId]);
    $deleteOrder->execute(['id_pedido' => $orderId]);

    adminOrderLogDeletion($connection, $orderId, (string) $order['numero_pedido']);

    return (string) $order['numero_pedido'];
}

function adminOrderFetchNumbersByIds(PDO $connection, array $orderIds): array
{
    if ($orderIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $statement = $connection->prepare(
        "SELECT id_pedido, numero_pedido
         FROM pedidos
         WHERE id_pedido IN ($placeholders)"
    );
    $statement->execute($orderIds);

    $orders = [];
    foreach (($statement->fetchAll() ?: []) as $order) {
        $orders[(int) $order['id_pedido']] = (string) $order['numero_pedido'];
    }

    return $orders;
}

function adminOrderMoveToTrash(PDO $connection, array $orderIds): int
{
    if ($orderIds === []) {
        return 0;
    }

    $numbers = adminOrderFetchNumbersByIds($connection, $orderIds);
    if ($numbers === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($numbers), '?'));
    $updateStatement = $connection->prepare(
        "UPDATE pedidos
         SET en_papelera = 1, fecha_papelera = NOW()
         WHERE id_pedido IN ($placeholders)"
    );
    $updateStatement->execute(array_keys($numbers));

    foreach ($numbers as $orderId => $orderNumber) {
        adminOrderLogTrashMove($connection, $orderId, $orderNumber);
    }

    return count($numbers);
}

function adminOrderRestoreFromTrash(PDO $connection, array $orderIds): int
{
    if ($orderIds === []) {
        return 0;
    }

    $numbers = adminOrderFetchNumbersByIds($connection, $orderIds);
    if ($numbers === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($numbers), '?'));
    $updateStatement = $connection->prepare(
        "UPDATE pedidos
         SET en_papelera = 0, fecha_papelera = NULL
         WHERE id_pedido IN ($placeholders)"
    );
    $updateStatement->execute(array_keys($numbers));

    foreach ($numbers as $orderId => $orderNumber) {
        adminOrderLogRestore($connection, $orderId, $orderNumber);
    }

    return count($numbers);
}

function adminOrderDeleteMany(PDO $connection, array $orderIds): int
{
    $deleted = 0;
    foreach ($orderIds as $orderId) {
        if (adminOrderDelete($connection, $orderId) !== null) {
            $deleted++;
        }
    }

    return $deleted;
}

$orderStatuses = adminOrderStatuses();
$currentView = adminOrderNormalizeView(trim((string) ($_GET['vista'] ?? 'activos')));
$selectedStatus = trim((string) ($_GET['estado'] ?? ''));
$searchTerm = trim((string) ($_GET['buscar'] ?? ''));
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$orders = [];
$orderDetails = [];
$errorMessage = null;
$successMessage = null;
$totalOrders = 0;
$totalPages = 1;
$totalActiveOrders = 0;
$totalTrashOrders = 0;

try {
    $connection = (new Conexion())->getConexion();
    adminOrderEnsureTrashColumns($connection);

    $viewCountStatement = $connection->query(
        'SELECT COALESCE(en_papelera, 0) AS en_papelera, COUNT(*) AS total
         FROM pedidos
         GROUP BY COALESCE(en_papelera, 0)'
    );

    foreach (($viewCountStatement->fetchAll() ?: []) as $viewCount) {
        if ((int) ($viewCount['en_papelera'] ?? 0) === 1) {
            $totalTrashOrders = (int) ($viewCount['total'] ?? 0);
            continue;
        }

        $totalActiveOrders = (int) ($viewCount['total'] ?? 0);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'update_order_status') {
            $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
            $nextStatus = trim((string) ($_POST['next_status'] ?? ''));

            if ($orderId <= 0 || !in_array($nextStatus, $orderStatuses, true)) {
                adminAuthRedirect(adminOrderBuildUrl(['error' => 'status']));
            }

            $connection->beginTransaction();

            $updateStatement = $connection->prepare('UPDATE pedidos SET estado = :estado WHERE id_pedido = :id_pedido');
            $updateStatement->execute([
                'estado' => $nextStatus,
                'id_pedido' => $orderId,
            ]);

            adminOrderSyncShipping($connection, $orderId, $nextStatus);
            adminOrderLogStatusChange($connection, $orderId, $nextStatus);
            $connection->commit();

            adminAuthRedirect(adminOrderBuildUrl(['updated' => $orderId]));
        }

        if (in_array($action, ['move_to_trash', 'restore_orders', 'delete_orders_permanently'], true)) {
            $orderIds = adminOrderSelectedIds($_POST['order_ids'] ?? []);

            if ($orderIds === []) {
                adminAuthRedirect(adminOrderBuildUrl(['error' => 'selection']));
            }

            if ($action === 'move_to_trash' && $currentView !== 'activos') {
                adminAuthRedirect(adminOrderBuildUrl(['error' => 'trash']));
            }

            if (in_array($action, ['restore_orders', 'delete_orders_permanently'], true) && $currentView !== 'papelera') {
                adminAuthRedirect(adminOrderBuildUrl(['error' => 'delete']));
            }

            $connection->beginTransaction();

            if ($action === 'move_to_trash') {
                $affected = adminOrderMoveToTrash($connection, $orderIds);
                if ($affected === 0) {
                    $connection->rollBack();
                    adminAuthRedirect(adminOrderBuildUrl(['error' => 'trash']));
                }

                $connection->commit();
                adminAuthRedirect(adminOrderBuildUrl(['moved' => $affected, 'page' => 1]));
            }

            if ($action === 'restore_orders') {
                $affected = adminOrderRestoreFromTrash($connection, $orderIds);
                if ($affected === 0) {
                    $connection->rollBack();
                    adminAuthRedirect(adminOrderBuildUrl(['error' => 'restore']));
                }

                $connection->commit();
                adminAuthRedirect(adminOrderBuildUrl(['restored' => $affected, 'page' => 1]));
            }

            $affected = adminOrderDeleteMany($connection, $orderIds);
            if ($affected === 0) {
                $connection->rollBack();
                adminAuthRedirect(adminOrderBuildUrl(['error' => 'delete']));
            }

            $connection->commit();
            adminAuthRedirect(adminOrderBuildUrl(['deleted' => $affected, 'page' => 1]));
        }
    }

    if ($selectedStatus !== '' && !in_array($selectedStatus, $orderStatuses, true)) {
        $selectedStatus = '';
    }

    $where = [];
    $params = ['en_papelera' => $currentView === 'papelera' ? 1 : 0];

    $where[] = 'COALESCE(p.en_papelera, 0) = :en_papelera';

    if ($selectedStatus !== '') {
        $where[] = 'p.estado = :estado';
        $params['estado'] = $selectedStatus;
    }

    if ($searchTerm !== '') {
        $where[] = '(p.numero_pedido LIKE :buscar_numero OR CONCAT(u.nombre, " ", u.apellido) LIKE :buscar_cliente OR u.telefono LIKE :buscar_telefono)';
        $searchPattern = '%' . $searchTerm . '%';
        $params['buscar_numero'] = $searchPattern;
        $params['buscar_cliente'] = $searchPattern;
        $params['buscar_telefono'] = $searchPattern;
    }

    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStatement = $connection->prepare(
        "SELECT COUNT(*)
         FROM pedidos p
         INNER JOIN clientes c ON c.id_cliente = p.id_cliente
         INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
         $whereSql"
    );

    foreach ($params as $name => $value) {
        $countStatement->bindValue(':' . $name, $value);
    }

    $countStatement->execute();
    $totalOrders = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($totalOrders / ADMIN_ORDER_PAGE_SIZE));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * ADMIN_ORDER_PAGE_SIZE;

    $ordersStatement = $connection->prepare(
        "SELECT
            p.id_pedido,
            p.numero_pedido,
            p.fecha_pedido,
            p.estado,
            p.subtotal,
            p.total,
            p.id_direccion,
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
         $whereSql
         ORDER BY p.id_pedido DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $name => $value) {
        $ordersStatement->bindValue(':' . $name, $value);
    }

    $ordersStatement->bindValue(':limit', ADMIN_ORDER_PAGE_SIZE, PDO::PARAM_INT);
    $ordersStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $ordersStatement->execute();
    $orders = $ordersStatement->fetchAll() ?: [];

    if ($orders !== []) {
        $orderIds = array_map(static fn (array $order): int => (int) $order['id_pedido'], $orders);
        $detailPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
        $detailsStatement = $connection->prepare(
            "SELECT
                pd.id_pedido,
                pd.cantidad,
                pd.precio_unitario,
                pd.subtotal,
                pr.nombre,
                pr.codigo_producto
             FROM pedido_detalle pd
             INNER JOIN productos pr ON pr.id_producto = pd.id_producto
             WHERE pd.id_pedido IN ($detailPlaceholders)
             ORDER BY pd.id_pedido DESC, pd.id_pedido_detalle ASC"
        );
        $detailsStatement->execute($orderIds);

        foreach (($detailsStatement->fetchAll() ?: []) as $detail) {
            $orderId = (int) $detail['id_pedido'];

            if (!isset($orderDetails[$orderId])) {
                $orderDetails[$orderId] = [];
            }

            $orderDetails[$orderId][] = $detail;
        }
    }

    if (isset($_GET['updated'])) {
        $successMessage = 'Estado del pedido actualizado y sincronizado correctamente.';
    } elseif (isset($_GET['moved'])) {
        $successMessage = (int) $_GET['moved'] . ' pedido(s) enviados a la papelera.';
    } elseif (isset($_GET['restored'])) {
        $successMessage = (int) $_GET['restored'] . ' pedido(s) restaurados de la papelera.';
    } elseif (isset($_GET['deleted'])) {
        $successMessage = (int) $_GET['deleted'] . ' pedido(s) eliminados definitivamente.';
    } elseif (isset($_GET['error'])) {
        $errorCode = trim((string) $_GET['error']);
        $errorMessage = match ($errorCode) {
            'delete' => 'No se pudo eliminar definitivamente el pedido seleccionado.',
            'trash' => 'No se pudieron mover los pedidos seleccionados a la papelera.',
            'restore' => 'No se pudieron restaurar los pedidos seleccionados.',
            'selection' => 'Selecciona al menos un pedido para aplicar la acción.',
            default => 'No se pudo actualizar el estado del pedido.',
        };
    }
} catch (Throwable $exception) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    $errorMessage = 'No se pudieron cargar los pedidos del sistema. ' . $exception->getMessage();
}

$pageTitle = 'Admin Pedidos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Pedidos registrados</h1>
        <p class="lead">Pedidos guardados y visibles solo para el administrador.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Total pedidos</span>
            <strong><?php echo $totalOrders; ?></strong>
        </article>
    </div>
</section>

<?php if ($successMessage !== null): ?>
    <section class="page-section admin-message admin-message-success">
        <strong>Cambio aplicado</strong>
        <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    </section>
<?php endif; ?>

<?php if ($errorMessage !== null): ?>
    <section class="page-section admin-message admin-message-error">
        <strong>No se pudo cargar el panel</strong>
        <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    </section>
<?php else: ?>
    <section class="page-section admin-products-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title"><?php echo $currentView === 'papelera' ? 'Papelera de pedidos' : 'Listado de pedidos'; ?></h2>
                <p class="admin-help-text"><?php echo $currentView === 'papelera' ? 'Selecciona pedidos para restaurarlos o eliminarlos definitivamente.' : 'Filtra, revisa detalle, cambia estado o envía pedidos a la papelera.'; ?></p>
            </div>
        </div>

        <div class="admin-shell-nav">
            <a class="top-category-link <?php echo $currentView === 'activos' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(adminOrderBuildUrl(['vista' => 'activos', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>">Activos (<?php echo $totalActiveOrders; ?>)</a>
            <a class="top-category-link <?php echo $currentView === 'papelera' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(adminOrderBuildUrl(['vista' => 'papelera', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>">Papelera (<?php echo $totalTrashOrders; ?>)</a>
        </div>

<?php if ($orders === []): ?>
    <section class="page-section">
        <div class="collection-empty">
            <strong><?php echo $currentView === 'papelera' ? 'La papelera está vacía.' : 'Todavia no hay pedidos registrados.'; ?></strong>
            <p><?php echo $currentView === 'papelera' ? 'Cuando envíes pedidos a la papelera, aparecerán aquí para restaurarlos o eliminarlos definitivamente.' : 'Los pedidos enviados desde el carrito por WhatsApp aparecerán aquí para revisión del administrador.'; ?></p>
        </div>
    </section>
<?php else: ?>
        <form class="admin-filter-bar" method="get" action="">
            <input type="hidden" name="vista" value="<?php echo htmlspecialchars($currentView, ENT_QUOTES, 'UTF-8'); ?>">
            <label class="admin-filter-search">
                Buscar pedido o cliente
                <input type="search" name="buscar" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Numero, nombre o telefono">
                <span class="admin-filter-hint">Filtra por número de pedido, nombre del cliente o teléfono.</span>
            </label>

            <label>
                Estado del pedido
                <select name="estado">
                    <option value="">Todos</option>
                    <?php foreach ($orderStatuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedStatus === $status ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="admin-filter-actions">
                <button class="button button-secondary admin-small-button" type="submit">Filtrar</button>
                <?php if ($searchTerm !== '' || $selectedStatus !== ''): ?>
                    <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars(adminOrderBuildUrl(['buscar' => '', 'estado' => '', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>">Limpiar filtros</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="admin-filter-summary">
            <strong><?php echo $totalOrders; ?> pedido(s)</strong>
            <span>
                Estado: <?php echo $selectedStatus !== '' ? htmlspecialchars($selectedStatus, ENT_QUOTES, 'UTF-8') : 'Todos'; ?>
                <?php if ($searchTerm !== ''): ?>
                    | Búsqueda: <?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
                <?php if ($totalPages > 1): ?>
                    | Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?>
                <?php endif; ?>
            </span>
        </div>

        <form id="bulk-order-form" class="admin-filter-bar admin-bulk-actions" method="post" action="">
            <div class="admin-bulk-actions-copy">
                <strong>Acciones con selección</strong>
                <span class="admin-filter-hint"><?php echo $currentView === 'papelera' ? 'Marca pedidos de la papelera para restaurarlos o borrarlos definitivamente.' : 'Marca uno o varios pedidos para enviarlos a la papelera.'; ?></span>
                <span class="admin-bulk-actions-count" data-selection-count="orders-bulk">0 pedidos seleccionados</span>
            </div>
            <div class="admin-filter-actions">
                <?php if ($currentView === 'papelera'): ?>
                    <button class="button button-secondary admin-small-button" type="submit" name="action" value="restore_orders" data-selection-action="orders-bulk" disabled>Restaurar seleccionados</button>
                    <button class="button button-danger admin-small-button" type="submit" name="action" value="delete_orders_permanently" data-selection-action="orders-bulk" onclick="return confirm('Los pedidos seleccionados se eliminarán definitivamente. ¿Deseas continuar?');" disabled>Eliminar definitivamente</button>
                <?php else: ?>
                    <button class="button button-secondary admin-small-button" type="submit" name="action" value="move_to_trash" data-selection-action="orders-bulk" disabled>Enviar seleccionados a papelera</button>
                <?php endif; ?>
            </div>
        </form>

        <div class="admin-table-wrap">
            <table class="table admin-table-cardify">
                <thead>
                <tr>
                    <th>
                        <input type="checkbox" data-select-all="orders-bulk" aria-label="Seleccionar todos los pedidos visibles">
                    </th>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th>Entrega</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th>Detalle</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $details = $orderDetails[(int) $order['id_pedido']] ?? []; ?>
                    <tr>
                        <td data-label="Seleccionar">
                            <input type="checkbox" name="order_ids[]" value="<?php echo (int) $order['id_pedido']; ?>" form="bulk-order-form" data-select-item="orders-bulk" aria-label="Seleccionar pedido <?php echo htmlspecialchars($order['numero_pedido'], ENT_QUOTES, 'UTF-8'); ?>">
                        </td>
                        <td data-label="Pedido">
                            <strong><?php echo htmlspecialchars($order['numero_pedido'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="admin-help-text">ID interno: <?php echo (int) $order['id_pedido']; ?></div>
                            <div class="admin-help-text">Fecha: <?php echo htmlspecialchars((string) $order['fecha_pedido'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td data-label="Cliente">
                            <strong><?php echo htmlspecialchars(trim($order['nombre'] . ' ' . $order['apellido']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </td>
                        <td data-label="Contacto">
                            <div><?php echo htmlspecialchars((string) $order['telefono'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="admin-help-text"><?php echo htmlspecialchars((string) $order['correo'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td data-label="Entrega">
                            <strong><?php echo htmlspecialchars((string) $order['metodo_entrega'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="admin-help-text"><?php echo htmlspecialchars((string) $order['estado_entrega'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ((string) $order['direccion'] !== ''): ?>
                                <div class="admin-help-text"><?php echo htmlspecialchars((string) $order['direccion'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <?php if ((string) $order['referencia'] !== ''): ?>
                                <div class="admin-help-text">Nota: <?php echo htmlspecialchars((string) $order['referencia'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <span class="admin-status-badge"><?php echo htmlspecialchars((string) $order['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Total">
                            <strong>S/ <?php echo number_format((float) $order['total'], 2); ?></strong>
                            <div class="admin-help-text">Subtotal: S/ <?php echo number_format((float) $order['subtotal'], 2); ?></div>
                        </td>
                        <td data-label="Detalle">
                            <?php if ($details !== []): ?>
                                <div class="admin-visibility-cell">
                                    <?php foreach ($details as $detail): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars((string) $detail['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <div class="admin-help-text">
                                                SKU: <?php echo htmlspecialchars((string) $detail['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?>
                                                | Cantidad: <?php echo (int) $detail['cantidad']; ?>
                                                | Subtotal: S/ <?php echo number_format((float) $detail['subtotal'], 2); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="admin-help-text">Sin detalle registrado.</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Acciones">
                            <?php if ($currentView !== 'papelera'): ?>
                                <form method="post" action="" class="cart-line-form">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id_pedido']; ?>">
                                    <select name="next_status">
                                        <?php foreach ($orderStatuses as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $order['estado'] === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button button-secondary admin-small-button" type="submit">Actualizar</button>
                                </form>
                            <?php endif; ?>
                            <div class="cart-line-actions" style="margin-top: 10px;">
                                <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/admin/pedidos/ver.php?id=<?php echo (int) $order['id_pedido']; ?>">Ver resumen</a>
                                <?php if ($currentView === 'papelera'): ?>
                                    <span class="admin-help-text">Usa la selección para restaurar o borrar definitivamente.</span>
                                <?php else: ?>
                                    <span class="admin-help-text">Usa la selección para enviar a papelera.</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-pagination" aria-label="Paginacion de pedidos">
                <?php if ($currentPage > 1): ?>
                    <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars(adminOrderBuildUrl(['page' => $currentPage - 1]), ENT_QUOTES, 'UTF-8'); ?>">Anterior</a>
                <?php endif; ?>

                <div class="admin-pagination-pages">
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a class="admin-pagination-link <?php echo $pageNumber === $currentPage ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(adminOrderBuildUrl(['page' => $pageNumber]), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $pageNumber; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($currentPage < $totalPages): ?>
                    <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars(adminOrderBuildUrl(['page' => $currentPage + 1]), ENT_QUOTES, 'UTF-8'); ?>">Siguiente</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
<?php endif; ?>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
