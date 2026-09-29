<?php
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../includes/admin_panel_data.php';

adminAuthRequireLogin();

function adminCategoryBuildUrl(array $overrides = []): string
{
    $params = [];
    $currentEdit = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

    if ($currentEdit > 0) {
        $params['edit'] = $currentEdit;
    }

    $params = array_merge($params, $overrides);

    if ((int) ($params['edit'] ?? 0) <= 0) {
        unset($params['edit']);
    }

    if (($params['success'] ?? '') === '') {
        unset($params['success']);
    }

    if (($params['error'] ?? '') === '') {
        unset($params['error']);
    }

    return adminAuthBaseUrl() . '/admin/categorias/index.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function adminCategoryDefaultData(): array
{
    return [
        'id_categoria' => '',
        'nombre' => '',
        'descripcion' => '',
        'estado' => '1',
    ];
}

$connection = adminPanelConnection();
$formData = adminCategoryDefaultData();
$errors = [];
$successMessage = null;
$editingCategoryId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

if (isset($_GET['success'])) {
    $successKey = trim((string) $_GET['success']);
    $successMap = [
        'created' => 'Categoria creada correctamente.',
        'updated' => 'Categoria actualizada correctamente.',
        'status' => 'Estado de la categoria actualizado.',
        'deleted' => 'Categoria eliminada correctamente.',
    ];
    $successMessage = $successMap[$successKey] ?? null;
}

if (isset($_GET['error'])) {
    $errorKey = trim((string) $_GET['error']);
    $errorMap = [
        'missing' => 'La categoria seleccionada ya no existe.',
        'linked' => 'No puedes eliminar una categoria que ya tiene productos asociados.',
        'save' => 'No se pudo guardar la categoria.',
    ];
    if (isset($errorMap[$errorKey])) {
        $errors[] = $errorMap[$errorKey];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connection instanceof PDO) {
    $action = trim((string) ($_POST['action'] ?? 'create'));

    if (in_array($action, ['create', 'update'], true)) {
        $formData = [
            'id_categoria' => trim((string) ($_POST['category_id'] ?? '')),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'estado' => trim((string) ($_POST['estado'] ?? '1')),
        ];

        $categoryId = filter_var($formData['id_categoria'], FILTER_VALIDATE_INT) ?: null;
        $isEditing = $action === 'update' && $categoryId !== null;

        if ($formData['nombre'] === '') {
            $errors[] = 'El nombre de la categoria es obligatorio.';
        }

        if (!in_array($formData['estado'], ['0', '1'], true)) {
            $errors[] = 'Selecciona un estado valido para la categoria.';
        }

        if ($isEditing) {
            $existsStatement = $connection->prepare('SELECT id_categoria FROM categorias WHERE id_categoria = :id_categoria LIMIT 1');
            $existsStatement->execute(['id_categoria' => $categoryId]);

            if ($existsStatement->fetch() === false) {
                adminAuthRedirect(adminCategoryBuildUrl(['error' => 'missing']));
            }
        }

        if ($errors === []) {
            try {
                if ($isEditing) {
                    $statement = $connection->prepare(
                        'UPDATE categorias
                         SET nombre = :nombre,
                             descripcion = :descripcion,
                             estado = :estado
                         WHERE id_categoria = :id_categoria'
                    );
                    $statement->execute([
                        'id_categoria' => $categoryId,
                        'nombre' => $formData['nombre'],
                        'descripcion' => $formData['descripcion'] !== '' ? $formData['descripcion'] : null,
                        'estado' => (int) $formData['estado'],
                    ]);

                    adminAuthRedirect(adminCategoryBuildUrl(['success' => 'updated', 'edit' => $categoryId]));
                }

                $statement = $connection->prepare(
                    'INSERT INTO categorias (nombre, descripcion, estado)
                     VALUES (:nombre, :descripcion, :estado)'
                );
                $statement->execute([
                    'nombre' => $formData['nombre'],
                    'descripcion' => $formData['descripcion'] !== '' ? $formData['descripcion'] : null,
                    'estado' => (int) $formData['estado'],
                ]);

                adminAuthRedirect(adminCategoryBuildUrl(['success' => 'created']));
            } catch (Throwable $exception) {
                $errors[] = 'No se pudo guardar la categoria. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

        if ($categoryId <= 0) {
            adminAuthRedirect(adminCategoryBuildUrl(['error' => 'missing']));
        }

        $statement = $connection->prepare('UPDATE categorias SET estado = CASE WHEN estado = 1 THEN 0 ELSE 1 END WHERE id_categoria = :id_categoria');
        $statement->execute(['id_categoria' => $categoryId]);

        adminAuthRedirect(adminCategoryBuildUrl(['success' => 'status']));
    } elseif ($action === 'delete') {
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

        if ($categoryId <= 0) {
            adminAuthRedirect(adminCategoryBuildUrl(['error' => 'missing']));
        }

        $linkedStatement = $connection->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = :id_categoria');
        $linkedStatement->execute(['id_categoria' => $categoryId]);

        if ((int) $linkedStatement->fetchColumn() > 0) {
            adminAuthRedirect(adminCategoryBuildUrl(['error' => 'linked']));
        }

        $deleteStatement = $connection->prepare('DELETE FROM categorias WHERE id_categoria = :id_categoria');
        $deleteStatement->execute(['id_categoria' => $categoryId]);

        adminAuthRedirect(adminCategoryBuildUrl(['success' => 'deleted']));
    }
}

if ($editingCategoryId > 0 && $connection instanceof PDO) {
    $editStatement = $connection->prepare('SELECT id_categoria, nombre, descripcion, estado FROM categorias WHERE id_categoria = :id_categoria LIMIT 1');
    $editStatement->execute(['id_categoria' => $editingCategoryId]);
    $editingCategory = $editStatement->fetch();

    if ($editingCategory) {
        $formData = [
            'id_categoria' => (string) $editingCategory['id_categoria'],
            'nombre' => (string) $editingCategory['nombre'],
            'descripcion' => (string) ($editingCategory['descripcion'] ?? ''),
            'estado' => (string) (int) ($editingCategory['estado'] ?? 0),
        ];
    } elseif ($errors === []) {
        $errors[] = 'La categoria seleccionada para editar ya no existe.';
        $editingCategoryId = 0;
        $formData = adminCategoryDefaultData();
    }
}

$categorySnapshot = adminPanelCategorySnapshot();
$categoryMetrics = $categorySnapshot['metrics'];
$categories = $categorySnapshot['categories'];
$emptyCategories = $categorySnapshot['empty_categories'];

$pageTitle = 'Admin Categorias';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Categorias y cobertura del catalogo</h1>
        <p class="lead">Crea, edita o desactiva categorías desde un solo lugar.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Categorias activas</span>
            <strong><?php echo (int) $categoryMetrics['active']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Con productos</span>
            <strong><?php echo (int) $categoryMetrics['with_products']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Vacias</span>
            <strong><?php echo (int) $categoryMetrics['empty']; ?></strong>
        </article>
        <article class="hero-stat">
            <span>Promociones activas</span>
            <strong><?php echo (int) $categoryMetrics['promotions']; ?></strong>
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
                <h2 class="section-title">Categorias registradas</h2>
                <p class="admin-help-text">Si una categoría ya tiene productos, usa el cambio de estado.</p>
            </div>
            <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Ir a productos</a>
        </div>

        <?php if ($categories !== []): ?>
            <div class="admin-table-wrap">
                <table class="table admin-table-cardify">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Descripcion</th>
                            <th>Productos</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td data-label="Categoria"><?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Descripcion"><?php echo htmlspecialchars(trim((string) ($category['descripcion'] ?? '')) ?: 'Sin descripcion.', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Productos"><?php echo (int) ($category['product_count'] ?? 0); ?></td>
                                <td data-label="Stock"><?php echo (int) ($category['stock_total'] ?? 0); ?></td>
                                <td data-label="Estado">
                                    <span class="admin-status-badge <?php echo (int) ($category['estado'] ?? 0) === 1 ? 'admin-status-badge-visible' : 'admin-status-badge-hidden'; ?>">
                                        <?php echo (int) ($category['estado'] ?? 0) === 1 ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td data-label="Acciones">
                                    <div class="admin-table-actions">
                                        <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/admin/categorias/index.php?edit=<?php echo (int) $category['id_categoria']; ?>">Editar</a>
                                        <form action="" method="post">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?php echo (int) $category['id_categoria']; ?>">
                                            <button class="button button-secondary admin-small-button" type="submit">
                                                <?php echo (int) ($category['estado'] ?? 0) === 1 ? 'Desactivar' : 'Activar'; ?>
                                            </button>
                                        </form>
                                        <?php if ((int) ($category['product_count'] ?? 0) === 0): ?>
                                            <form action="" method="post" onsubmit="return confirm('Esta accion eliminara la categoria seleccionada.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?php echo (int) $category['id_categoria']; ?>">
                                                <button class="button button-danger admin-small-button" type="submit">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-note-item">
                <strong>Todavia no hay categorias creadas.</strong>
                <p>Usa el formulario lateral para registrar la primera categoria del catalogo.</p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="page-section admin-side-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title section-title-small"><?php echo $editingCategoryId > 0 ? 'Editar categoria' : 'Nueva categoria'; ?></h2>
                <p class="admin-help-text"><?php echo $editingCategoryId > 0 ? 'Ajusta nombre o estado.' : 'Créala activa o déjala oculta por ahora.'; ?></p>
            </div>
            <?php if ($editingCategoryId > 0): ?>
                <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/admin/categorias/index.php">Cancelar</a>
            <?php endif; ?>
        </div>

        <form class="admin-form-grid" action="" method="post">
            <input type="hidden" name="action" value="<?php echo $editingCategoryId > 0 ? 'update' : 'create'; ?>">
            <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($formData['id_categoria'], ENT_QUOTES, 'UTF-8'); ?>">

            <label class="admin-field-span-2">
                Nombre de la categoria
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($formData['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label class="admin-field-span-2">
                Descripcion
                <textarea name="descripcion" rows="4" placeholder="Describe el tipo de productos que agrupa esta categoria."><?php echo htmlspecialchars($formData['descripcion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>

            <label class="admin-field-span-2">
                Estado
                <select name="estado">
                    <option value="1" <?php echo $formData['estado'] === '1' ? 'selected' : ''; ?>>Activa</option>
                    <option value="0" <?php echo $formData['estado'] === '0' ? 'selected' : ''; ?>>Inactiva</option>
                </select>
            </label>

            <button class="button admin-field-span-2" type="submit"><?php echo $editingCategoryId > 0 ? 'Guardar cambios' : 'Crear categoria'; ?></button>
        </form>

        <div class="admin-section-stack">
            <div>
                <h3 class="section-title-small">Categorias sin productos</h3>
                <p class="admin-help-text">Revísalas antes de publicarlas.</p>
            </div>

            <?php if ($emptyCategories !== []): ?>
                <div class="admin-note-list">
                    <?php foreach ($emptyCategories as $category): ?>
                        <article class="admin-note-item">
                            <strong><?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p>Estado: <?php echo (int) ($category['estado'] ?? 0) === 1 ? 'activa' : 'inactiva'; ?> | Promociones: <?php echo (int) ($category['promo_count'] ?? 0); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-note-item admin-note-item-success">
                    <strong>Todas las categorias ya tienen productos.</strong>
                    <p>No quedan categorias vacias en el catalogo actual.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
