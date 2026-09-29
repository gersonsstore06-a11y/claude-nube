<?php
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/admin_auth.php';
require_once __DIR__ . '/../../includes/storefront_data.php';

adminAuthRequireLogin();

const PRODUCT_UPLOAD_DIR = __DIR__ . '/../../public/uploads/productos';
const PRODUCT_UPLOAD_BASE = 'public/uploads/productos';
const PRODUCT_MAX_FILE_SIZE = 5_242_880;

function adminProductDefaultData(): array
{
    return [
        'id_producto' => '',
        'id_categoria' => '',
        'codigo_producto' => '',
        'nombre' => '',
        'descripcion' => '',
        'precio_compra' => '',
        'precio_venta' => '',
        'promocion_activa' => '0',
        'precio_promocional' => '',
        'texto_promocion' => '',
        'stock_actual' => '0',
        'stock_minimo' => '5',
        'estado' => 'ACTIVO',
    ];
}

function adminProductGeneratedCode(): string
{
    return 'PROD-' . date('YmdHis') . '-' . random_int(100, 999);
}

function adminProductNormalizeAmount(string $value): string
{
    return str_replace(',', '.', trim($value));
}

function adminProductSingleFile(?array $file): ?array
{
    if (!$file || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return $file;
}

function adminProductMultipleFiles(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $normalized = [];
    $total = count($files['name']);

    for ($index = 0; $index < $total; $index++) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $normalized;
}

function adminProductUpload(array $file): string
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo procesar uno de los archivos seleccionados.');
    }

    if ((int) ($file['size'] ?? 0) > PRODUCT_MAX_FILE_SIZE) {
        throw new RuntimeException('Cada imagen debe pesar menos de 5 MB.');
    }

    if (!is_dir(PRODUCT_UPLOAD_DIR) && !mkdir(PRODUCT_UPLOAD_DIR, 0777, true) && !is_dir(PRODUCT_UPLOAD_DIR)) {
        throw new RuntimeException('No se pudo preparar la carpeta de uploads para productos.');
    }

    $mimeType = '';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($file['tmp_name']);
    }

    $allowedExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedExtensions[$mimeType])) {
        throw new RuntimeException('Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
    }

    $extension = $allowedExtensions[$mimeType];
    $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = PRODUCT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('No se pudo guardar una de las imágenes en el servidor.');
    }

    return PRODUCT_UPLOAD_BASE . '/' . $fileName;
}

function adminProductAbsolutePath(string $path): string
{
    return __DIR__ . '/../../' . str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function adminProductCleanupUploads(array $paths): void
{
    foreach ($paths as $path) {
        $absolutePath = adminProductAbsolutePath($path);

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
}

function adminProductImageOrderAvailable(PDO $connection, bool $refresh = false): bool
{
    static $available = null;

    if ($refresh || $available === null) {
        $statement = $connection->query("SHOW COLUMNS FROM producto_imagenes LIKE 'orden'");
        $available = $statement !== false && $statement->fetch() !== false;
    }

    return $available;
}

function adminProductEnsureImageOrderColumn(PDO $connection): bool
{
    if (adminProductImageOrderAvailable($connection)) {
        return true;
    }

    $connection->exec('ALTER TABLE producto_imagenes ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER url_imagen');
    $connection->exec('UPDATE producto_imagenes SET orden = id_imagen WHERE orden = 0');

    return adminProductImageOrderAvailable($connection, true);
}

function adminProductImageOrderClause(PDO $connection): string
{
    if (adminProductImageOrderAvailable($connection)) {
        return 'principal DESC, orden ASC, id_imagen ASC';
    }

    return 'principal DESC, id_imagen ASC';
}

function adminProductFetchImages(PDO $connection, int $productId): array
{
    $orderColumn = adminProductImageOrderAvailable($connection) ? 'orden' : '0 AS orden';
    $statement = $connection->prepare(
        'SELECT id_imagen, id_producto, url_imagen, ' . $orderColumn . ', principal
         FROM producto_imagenes
         WHERE id_producto = :id_producto
         ORDER BY ' . adminProductImageOrderClause($connection)
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchAll() ?: [];
}

function adminProductNextImageOrder(PDO $connection, int $productId): int
{
    if (!adminProductImageOrderAvailable($connection)) {
        return 0;
    }

    $statement = $connection->prepare(
        'SELECT COALESCE(MAX(orden), 0) + 1
         FROM producto_imagenes
         WHERE id_producto = :id_producto'
    );
    $statement->execute(['id_producto' => $productId]);

    return max(1, (int) $statement->fetchColumn());
}

function adminProductInsertImage(PDO $connection, int $productId, string $imagePath, int $principal): void
{
    if (adminProductImageOrderAvailable($connection)) {
        $statement = $connection->prepare(
            'INSERT INTO producto_imagenes (id_producto, url_imagen, orden, principal)
             VALUES (:id_producto, :url_imagen, :orden, :principal)'
        );
        $statement->execute([
            'id_producto' => $productId,
            'url_imagen' => $imagePath,
            'orden' => adminProductNextImageOrder($connection, $productId),
            'principal' => $principal,
        ]);

        return;
    }

    $statement = $connection->prepare(
        'INSERT INTO producto_imagenes (id_producto, url_imagen, principal)
         VALUES (:id_producto, :url_imagen, :principal)'
    );
    $statement->execute([
        'id_producto' => $productId,
        'url_imagen' => $imagePath,
        'principal' => $principal,
    ]);
}

function adminProductNormalizeImageOrder(PDO $connection, int $productId): void
{
    if (!adminProductImageOrderAvailable($connection)) {
        return;
    }

    $images = adminProductFetchImages($connection, $productId);
    $statement = $connection->prepare(
        'UPDATE producto_imagenes
         SET orden = :orden
         WHERE id_imagen = :id_imagen'
    );

    foreach ($images as $index => $image) {
        $statement->execute([
            'orden' => $index + 1,
            'id_imagen' => (int) $image['id_imagen'],
        ]);
    }
}

function adminProductMoveGalleryImage(PDO $connection, int $productId, int $imageId, string $direction): bool
{
    if (!adminProductImageOrderAvailable($connection)) {
        return false;
    }

    adminProductNormalizeImageOrder($connection, $productId);

    $galleryImages = array_values(array_filter(
        adminProductFetchImages($connection, $productId),
        static fn (array $image): bool => (int) $image['principal'] !== 1
    ));

    $currentIndex = null;

    foreach ($galleryImages as $index => $image) {
        if ((int) $image['id_imagen'] === $imageId) {
            $currentIndex = $index;
            break;
        }
    }

    if ($currentIndex === null) {
        return false;
    }

    $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

    if ($targetIndex < 0 || $targetIndex >= count($galleryImages)) {
        return false;
    }

    $updateStatement = $connection->prepare(
        'UPDATE producto_imagenes
         SET orden = :orden
         WHERE id_imagen = :id_imagen'
    );

    $updateStatement->execute([
        'orden' => (int) $galleryImages[$targetIndex]['orden'],
        'id_imagen' => (int) $galleryImages[$currentIndex]['id_imagen'],
    ]);
    $updateStatement->execute([
        'orden' => (int) $galleryImages[$currentIndex]['orden'],
        'id_imagen' => (int) $galleryImages[$targetIndex]['id_imagen'],
    ]);

    adminProductNormalizeImageOrder($connection, $productId);

    return true;
}

function adminProductReorderGalleryImages(PDO $connection, int $productId, array $orderedImageIds): bool
{
    if (!adminProductImageOrderAvailable($connection)) {
        return false;
    }

    $images = adminProductFetchImages($connection, $productId);
    $primaryImage = null;
    $galleryImages = [];

    foreach ($images as $image) {
        if ((int) $image['principal'] === 1) {
            $primaryImage = $image;
            continue;
        }

        $galleryImages[] = $image;
    }

    $currentGalleryIds = array_map(
        static fn (array $image): int => (int) $image['id_imagen'],
        $galleryImages
    );

    $orderedImageIds = array_values(array_unique(array_map('intval', $orderedImageIds)));

    if ($currentGalleryIds === [] || count($currentGalleryIds) !== count($orderedImageIds)) {
        return false;
    }

    sort($currentGalleryIds);
    $validatedOrderedIds = $orderedImageIds;
    sort($validatedOrderedIds);

    if ($currentGalleryIds !== $validatedOrderedIds) {
        return false;
    }

    $updateStatement = $connection->prepare(
        'UPDATE producto_imagenes
         SET orden = :orden
         WHERE id_imagen = :id_imagen AND id_producto = :id_producto'
    );

    if ($primaryImage !== null) {
        $updateStatement->execute([
            'orden' => 1,
            'id_imagen' => (int) $primaryImage['id_imagen'],
            'id_producto' => $productId,
        ]);
    }

    $startOrder = $primaryImage !== null ? 2 : 1;

    foreach ($orderedImageIds as $index => $imageId) {
        $updateStatement->execute([
            'orden' => $startOrder + $index,
            'id_imagen' => $imageId,
            'id_producto' => $productId,
        ]);
    }

    adminProductNormalizeImageOrder($connection, $productId);

    return true;
}

function adminProductFetchById(PDO $connection, int $productId): ?array
{
    $statement = $connection->prepare(
        "SELECT
            p.id_producto,
            p.id_categoria,
            p.codigo_producto,
            p.nombre,
            p.descripcion,
            p.precio_compra,
            p.precio_venta,
            p.promocion_activa,
            p.precio_promocional,
            p.texto_promocion,
            p.stock_minimo,
            p.estado,
            COALESCE(i.stock_actual, 0) AS stock_actual
         FROM productos p
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         WHERE p.id_producto = :id_producto
         LIMIT 1"
    );
    $statement->execute(['id_producto' => $productId]);
    $product = $statement->fetch();

    if (!$product) {
        return null;
    }

    $product['imagenes'] = adminProductFetchImages($connection, $productId);

    return $product;
}

function adminProductFormDataFromRecord(array $product): array
{
    return [
        'id_producto' => (string) ($product['id_producto'] ?? ''),
        'id_categoria' => (string) ($product['id_categoria'] ?? ''),
        'codigo_producto' => (string) ($product['codigo_producto'] ?? ''),
        'nombre' => (string) ($product['nombre'] ?? ''),
        'descripcion' => (string) ($product['descripcion'] ?? ''),
        'precio_compra' => isset($product['precio_compra']) ? (string) $product['precio_compra'] : '',
        'precio_venta' => isset($product['precio_venta']) ? (string) $product['precio_venta'] : '',
        'promocion_activa' => isset($product['promocion_activa']) ? (string) $product['promocion_activa'] : '0',
        'precio_promocional' => isset($product['precio_promocional']) ? (string) $product['precio_promocional'] : '',
        'texto_promocion' => (string) ($product['texto_promocion'] ?? ''),
        'stock_actual' => isset($product['stock_actual']) ? (string) $product['stock_actual'] : '0',
        'stock_minimo' => isset($product['stock_minimo']) ? (string) $product['stock_minimo'] : '5',
        'estado' => (string) ($product['estado'] ?? 'ACTIVO'),
    ];
}

$pageTitle = 'Admin Productos';
$formData = adminProductDefaultData();
$errors = [];
$successMessage = null;
$editingProductId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
$currentImages = [];
$selectedStatusFilter = trim((string) ($_GET['filter_status'] ?? 'all'));
$selectedImageFilter = trim((string) ($_GET['filter_images'] ?? 'all'));
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$requestedPage = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
) ?: 1;
$currentPage = $requestedPage;
$itemsPerPage = 10;
$totalProducts = 0;
$totalPages = 1;

if (!in_array($selectedStatusFilter, ['all', 'ACTIVO', 'INACTIVO'], true)) {
    $selectedStatusFilter = 'all';
}

if (!in_array($selectedImageFilter, ['all', 'with_images', 'without_images'], true)) {
    $selectedImageFilter = 'all';
}

$categories = storefrontCategories();
$imageOrderingAvailable = false;

try {
    $connection = (new Conexion())->getConexion();
} catch (Throwable $exception) {
    $connection = null;
    $errors[] = 'No se pudo conectar con la base de datos. ' . $exception->getMessage();
}

if ($connection instanceof PDO) {
    try {
        $imageOrderingAvailable = adminProductEnsureImageOrderColumn($connection);
    } catch (Throwable $exception) {
        $errors[] = 'No se pudo preparar el orden de las galerías. ' . $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connection instanceof PDO) {
    $action = trim((string) ($_POST['action'] ?? 'create'));

    if (in_array($action, ['create', 'update'], true)) {
        $formData = [
            'id_producto' => trim((string) ($_POST['product_id'] ?? '')),
            'id_categoria' => trim((string) ($_POST['id_categoria'] ?? '')),
            'codigo_producto' => trim((string) ($_POST['codigo_producto'] ?? '')),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'precio_compra' => adminProductNormalizeAmount((string) ($_POST['precio_compra'] ?? '0')),
            'precio_venta' => adminProductNormalizeAmount((string) ($_POST['precio_venta'] ?? '0')),
            'promocion_activa' => trim((string) ($_POST['promocion_activa'] ?? '0')),
            'precio_promocional' => adminProductNormalizeAmount((string) ($_POST['precio_promocional'] ?? '')),
            'texto_promocion' => trim((string) ($_POST['texto_promocion'] ?? '')),
            'stock_actual' => trim((string) ($_POST['stock_actual'] ?? '0')),
            'stock_minimo' => trim((string) ($_POST['stock_minimo'] ?? '5')),
            'estado' => strtoupper(trim((string) ($_POST['estado'] ?? 'ACTIVO'))),
        ];

        $productId = filter_var($formData['id_producto'], FILTER_VALIDATE_INT) ?: null;
        $isEditing = $action === 'update' && $productId !== null;
        $editingProductId = $isEditing ? $productId : $editingProductId;

        $selectedCategoryId = filter_var($formData['id_categoria'], FILTER_VALIDATE_INT);
        $purchasePrice = filter_var($formData['precio_compra'], FILTER_VALIDATE_FLOAT);
        $salePrice = filter_var($formData['precio_venta'], FILTER_VALIDATE_FLOAT);
        $promotionEnabled = $formData['promocion_activa'] === '1';
        $promotionalPrice = $formData['precio_promocional'] === '' ? null : filter_var($formData['precio_promocional'], FILTER_VALIDATE_FLOAT);
        $currentStock = filter_var($formData['stock_actual'], FILTER_VALIDATE_INT);
        $minimumStock = filter_var($formData['stock_minimo'], FILTER_VALIDATE_INT);
        $mainImageFile = adminProductSingleFile($_FILES['imagen_principal'] ?? null);
        $galleryFiles = adminProductMultipleFiles($_FILES['imagenes_galeria'] ?? []);

        if ($isEditing && adminProductFetchById($connection, $productId) === null) {
            $errors[] = 'El producto que intentas editar ya no existe.';
        }

        if (!$selectedCategoryId) {
            $errors[] = 'Selecciona una categoría válida.';
        }

        if ($formData['nombre'] === '') {
            $errors[] = 'El nombre del producto es obligatorio.';
        }

        if ($purchasePrice === false || $purchasePrice < 0) {
            $errors[] = 'Ingresa un precio de compra válido.';
        }

        if ($salePrice === false || $salePrice < 0) {
            $errors[] = 'Ingresa un precio de venta válido.';
        }

        if (!in_array($formData['promocion_activa'], ['0', '1'], true)) {
            $errors[] = 'Selecciona una configuración válida para la promoción.';
        }

        if ($promotionEnabled) {
            if ($promotionalPrice === false || $promotionalPrice === null || $promotionalPrice <= 0) {
                $errors[] = 'Ingresa un precio promocional válido.';
            } elseif ($salePrice !== false && $promotionalPrice >= $salePrice) {
                $errors[] = 'El precio promocional debe ser menor al precio de venta.';
            }
        }

        if ($currentStock === false || $currentStock < 0) {
            $errors[] = 'Ingresa un stock actual válido.';
        }

        if ($minimumStock === false || $minimumStock < 0) {
            $errors[] = 'Ingresa un stock mínimo válido.';
        }

        if (!in_array($formData['estado'], ['ACTIVO', 'INACTIVO'], true)) {
            $errors[] = 'Selecciona un estado válido.';
        }

        if ($formData['codigo_producto'] === '') {
            $formData['codigo_producto'] = adminProductGeneratedCode();
        }

        $uploadedPaths = [];

        if ($errors === []) {
            try {
                $connection->beginTransaction();

                if ($isEditing) {
                    $productStatement = $connection->prepare(
                        'UPDATE productos
                         SET id_categoria = :id_categoria,
                             codigo_producto = :codigo_producto,
                             nombre = :nombre,
                             descripcion = :descripcion,
                             precio_compra = :precio_compra,
                             precio_venta = :precio_venta,
                             promocion_activa = :promocion_activa,
                             precio_promocional = :precio_promocional,
                             texto_promocion = :texto_promocion,
                             stock_minimo = :stock_minimo,
                             estado = :estado
                         WHERE id_producto = :id_producto'
                    );
                    $productStatement->execute([
                        'id_producto' => $productId,
                        'id_categoria' => $selectedCategoryId,
                        'codigo_producto' => $formData['codigo_producto'],
                        'nombre' => $formData['nombre'],
                        'descripcion' => $formData['descripcion'] !== '' ? $formData['descripcion'] : null,
                        'precio_compra' => $purchasePrice,
                        'precio_venta' => $salePrice,
                        'promocion_activa' => $promotionEnabled ? 1 : 0,
                        'precio_promocional' => $promotionEnabled ? $promotionalPrice : null,
                        'texto_promocion' => $promotionEnabled && $formData['texto_promocion'] !== '' ? $formData['texto_promocion'] : null,
                        'stock_minimo' => $minimumStock,
                        'estado' => $formData['estado'],
                    ]);
                } else {
                    $productStatement = $connection->prepare(
                        'INSERT INTO productos (
                            id_categoria,
                            codigo_producto,
                            nombre,
                            descripcion,
                            precio_compra,
                            precio_venta,
                            promocion_activa,
                            precio_promocional,
                            texto_promocion,
                            stock_minimo,
                            estado
                        ) VALUES (
                            :id_categoria,
                            :codigo_producto,
                            :nombre,
                            :descripcion,
                            :precio_compra,
                            :precio_venta,
                            :promocion_activa,
                            :precio_promocional,
                            :texto_promocion,
                            :stock_minimo,
                            :estado
                        )'
                    );
                    $productStatement->execute([
                        'id_categoria' => $selectedCategoryId,
                        'codigo_producto' => $formData['codigo_producto'],
                        'nombre' => $formData['nombre'],
                        'descripcion' => $formData['descripcion'] !== '' ? $formData['descripcion'] : null,
                        'precio_compra' => $purchasePrice,
                        'precio_venta' => $salePrice,
                        'promocion_activa' => $promotionEnabled ? 1 : 0,
                        'precio_promocional' => $promotionEnabled ? $promotionalPrice : null,
                        'texto_promocion' => $promotionEnabled && $formData['texto_promocion'] !== '' ? $formData['texto_promocion'] : null,
                        'stock_minimo' => $minimumStock,
                        'estado' => $formData['estado'],
                    ]);

                    $productId = (int) $connection->lastInsertId();
                    $editingProductId = $productId;
                }

                $inventoryUpdateStatement = $connection->prepare(
                    'UPDATE inventario
                     SET stock_actual = :stock_actual,
                         ultimo_movimiento = NOW()
                     WHERE id_producto = :id_producto'
                );
                $inventoryUpdateStatement->execute([
                    'id_producto' => $productId,
                    'stock_actual' => $currentStock,
                ]);

                if ($inventoryUpdateStatement->rowCount() === 0) {
                    $inventoryInsertStatement = $connection->prepare(
                        'INSERT INTO inventario (id_producto, stock_actual, ultimo_movimiento)
                         VALUES (:id_producto, :stock_actual, NOW())'
                    );
                    $inventoryInsertStatement->execute([
                        'id_producto' => $productId,
                        'stock_actual' => $currentStock,
                    ]);
                }

                if ($mainImageFile !== null) {
                    $mainImagePath = adminProductUpload($mainImageFile);
                    $uploadedPaths[] = $mainImagePath;

                    $connection->prepare('UPDATE producto_imagenes SET principal = 0 WHERE id_producto = :id_producto')
                        ->execute(['id_producto' => $productId]);

                    adminProductInsertImage($connection, $productId, $mainImagePath, 1);
                }

                foreach ($galleryFiles as $galleryFile) {
                    $galleryPath = adminProductUpload($galleryFile);
                    $uploadedPaths[] = $galleryPath;

                    adminProductInsertImage($connection, $productId, $galleryPath, 0);
                }

                adminProductNormalizeImageOrder($connection, $productId);

                $connection->commit();
                $productRecord = adminProductFetchById($connection, $productId);
                $formData = $productRecord ? adminProductFormDataFromRecord($productRecord) : adminProductDefaultData();
                $currentImages = $productRecord['imagenes'] ?? [];
                $successMessage = $isEditing
                    ? 'Producto actualizado correctamente. Puedes seguir ajustando datos e imágenes.'
                    : 'Producto creado correctamente. Ya puedes verlo en el listado y seguir agregando imágenes.';
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                adminProductCleanupUploads($uploadedPaths);

                $message = $exception->getMessage();
                if (stripos($message, 'codigo_producto') !== false) {
                    $errors[] = 'El código del producto ya existe. Usa otro código o deja el campo vacío para generarlo automáticamente.';
                } else {
                    $errors[] = 'No se pudo guardar el producto. Detalle: ' . $message;
                }
            }
        }
    } elseif ($action === 'replace_image') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT);
        $replacementFile = adminProductSingleFile($_FILES['replacement_image'] ?? null);
        $editingProductId = $productId ?: $editingProductId;

        if (!$productId || !$imageId) {
            $errors[] = 'La imagen que intentas reemplazar no es válida.';
        } elseif ($replacementFile === null) {
            $errors[] = 'Selecciona una imagen nueva para reemplazar la actual.';
        } else {
            $uploadedPath = null;

            try {
                $imageStatement = $connection->prepare(
                    'SELECT id_imagen, id_producto, url_imagen, principal
                     FROM producto_imagenes
                     WHERE id_imagen = :id_imagen AND id_producto = :id_producto
                     LIMIT 1'
                );
                $imageStatement->execute([
                    'id_imagen' => $imageId,
                    'id_producto' => $productId,
                ]);
                $image = $imageStatement->fetch();

                if (!$image) {
                    $errors[] = 'La imagen seleccionada ya no existe.';
                } else {
                    $uploadedPath = adminProductUpload($replacementFile);
                    $connection->beginTransaction();
                    $connection->prepare(
                        'UPDATE producto_imagenes
                         SET url_imagen = :url_imagen
                         WHERE id_imagen = :id_imagen AND id_producto = :id_producto'
                    )->execute([
                        'url_imagen' => $uploadedPath,
                        'id_imagen' => $imageId,
                        'id_producto' => $productId,
                    ]);
                    $connection->commit();

                    adminProductCleanupUploads([(string) $image['url_imagen']]);
                    $successMessage = 'Imagen reemplazada correctamente.';
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                if ($uploadedPath !== null) {
                    adminProductCleanupUploads([$uploadedPath]);
                }

                $errors[] = 'No se pudo reemplazar la imagen. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'delete_product') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$productId) {
            $errors[] = 'Producto inválido para eliminar.';
        } else {
            try {
                $productRecord = adminProductFetchById($connection, $productId);

                if ($productRecord === null) {
                    $errors[] = 'El producto a eliminar ya no existe.';
                } else {
                    $imagePaths = array_map(
                        static fn (array $image): string => (string) $image['url_imagen'],
                        $productRecord['imagenes'] ?? []
                    );

                    $connection->beginTransaction();
                    $deleteStatement = $connection->prepare('DELETE FROM productos WHERE id_producto = :id_producto');
                    $deleteStatement->execute(['id_producto' => $productId]);
                    $connection->commit();

                    adminProductCleanupUploads($imagePaths);
                    if ($editingProductId === $productId) {
                        $editingProductId = null;
                        $formData = adminProductDefaultData();
                        $currentImages = [];
                    }
                    $successMessage = 'Producto eliminado correctamente.';
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = 'No se pudo eliminar el producto. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'move_image') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT);
        $direction = trim((string) ($_POST['direction'] ?? ''));
        $editingProductId = $productId ?: $editingProductId;

        if (!$productId || !$imageId || !in_array($direction, ['up', 'down'], true)) {
            $errors[] = 'No se pudo reordenar la imagen seleccionada.';
        } elseif (!$imageOrderingAvailable) {
            $errors[] = 'La galería todavía no tiene soporte de orden en esta base de datos.';
        } else {
            try {
                $connection->beginTransaction();
                $moved = adminProductMoveGalleryImage($connection, $productId, $imageId, $direction);

                if (!$moved) {
                    $connection->rollBack();
                    $errors[] = 'La imagen ya está en el límite de la galería o no se puede mover.';
                } else {
                    $connection->commit();
                    $successMessage = $direction === 'up'
                        ? 'Imagen movida hacia arriba en la galería.'
                        : 'Imagen movida hacia abajo en la galería.';
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = 'No se pudo reordenar la imagen. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'reorder_images') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $rawOrder = trim((string) ($_POST['image_order'] ?? ''));
        $editingProductId = $productId ?: $editingProductId;

        $orderedImageIds = array_values(array_filter(array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', $rawOrder)
        )));

        if (!$productId || count($orderedImageIds) < 2) {
            $errors[] = 'No se recibió un orden válido para la galería.';
        } elseif (!$imageOrderingAvailable) {
            $errors[] = 'La galería todavía no tiene soporte de orden en esta base de datos.';
        } else {
            try {
                $connection->beginTransaction();
                $reordered = adminProductReorderGalleryImages($connection, $productId, $orderedImageIds);

                if (!$reordered) {
                    $connection->rollBack();
                    $errors[] = 'No se pudo aplicar el nuevo orden de la galería.';
                } else {
                    $connection->commit();
                    $successMessage = 'Orden de galería actualizado correctamente.';
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = 'No se pudo guardar el nuevo orden de la galería. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'delete_image') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT);
        $editingProductId = $productId ?: $editingProductId;

        if (!$productId || !$imageId) {
            $errors[] = 'La imagen seleccionada no es válida.';
        } else {
            try {
                $imageStatement = $connection->prepare(
                    'SELECT id_imagen, id_producto, url_imagen, principal
                     FROM producto_imagenes
                     WHERE id_imagen = :id_imagen AND id_producto = :id_producto
                     LIMIT 1'
                );
                $imageStatement->execute([
                    'id_imagen' => $imageId,
                    'id_producto' => $productId,
                ]);
                $image = $imageStatement->fetch();

                if (!$image) {
                    $errors[] = 'La imagen seleccionada ya no existe.';
                } else {
                    $connection->beginTransaction();
                    $connection->prepare('DELETE FROM producto_imagenes WHERE id_imagen = :id_imagen')
                        ->execute(['id_imagen' => $imageId]);

                    if ((int) $image['principal'] === 1) {
                        $nextImageStatement = $connection->prepare(
                            'SELECT id_imagen
                             FROM producto_imagenes
                             WHERE id_producto = :id_producto
                             ORDER BY ' . adminProductImageOrderClause($connection) . '
                             LIMIT 1'
                        );
                        $nextImageStatement->execute(['id_producto' => $productId]);
                        $nextImageId = $nextImageStatement->fetchColumn();

                        if ($nextImageId !== false) {
                            $connection->prepare('UPDATE producto_imagenes SET principal = 1 WHERE id_imagen = :id_imagen')
                                ->execute(['id_imagen' => (int) $nextImageId]);
                        }
                    }

                    adminProductNormalizeImageOrder($connection, $productId);

                    $connection->commit();
                    adminProductCleanupUploads([(string) $image['url_imagen']]);
                    $successMessage = 'Imagen eliminada correctamente.';
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = 'No se pudo eliminar la imagen. Detalle: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'make_principal') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT);
        $editingProductId = $productId ?: $editingProductId;

        if (!$productId || !$imageId) {
            $errors[] = 'No se pudo marcar la imagen principal.';
        } else {
            try {
                $connection->beginTransaction();
                $connection->prepare('UPDATE producto_imagenes SET principal = 0 WHERE id_producto = :id_producto')
                    ->execute(['id_producto' => $productId]);
                $connection->prepare(
                    'UPDATE producto_imagenes
                     SET principal = 1
                     WHERE id_imagen = :id_imagen AND id_producto = :id_producto'
                )->execute([
                    'id_imagen' => $imageId,
                    'id_producto' => $productId,
                ]);
                adminProductNormalizeImageOrder($connection, $productId);
                $connection->commit();
                $successMessage = 'Imagen principal actualizada correctamente.';
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = 'No se pudo actualizar la imagen principal. Detalle: ' . $exception->getMessage();
            }
        }
    }
}

if ($connection instanceof PDO && $editingProductId !== null) {
    $editingProduct = adminProductFetchById($connection, $editingProductId);

    if ($editingProduct !== null) {
        if ($formData['id_producto'] === '' || (int) $formData['id_producto'] === $editingProductId) {
            $formData = adminProductFormDataFromRecord($editingProduct);
        }
        $currentImages = $editingProduct['imagenes'] ?? [];
    } elseif ($errors === []) {
        $editingProductId = null;
        $formData = adminProductDefaultData();
    }
}

$products = [];

if ($connection instanceof PDO) {
    try {
        $productsFrom = "
             FROM productos p
             INNER JOIN categorias c ON c.id_categoria = p.id_categoria
             LEFT JOIN inventario i ON i.id_producto = p.id_producto
             LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.principal = 1
             WHERE 1 = 1";
        $productsParams = [];

        if ($selectedStatusFilter !== 'all') {
            $productsFrom .= ' AND p.estado = :filter_status';
            $productsParams['filter_status'] = $selectedStatusFilter;
        }

        if ($selectedImageFilter === 'with_images') {
            $productsFrom .= ' AND EXISTS (SELECT 1 FROM producto_imagenes img WHERE img.id_producto = p.id_producto)';
        } elseif ($selectedImageFilter === 'without_images') {
            $productsFrom .= ' AND NOT EXISTS (SELECT 1 FROM producto_imagenes img WHERE img.id_producto = p.id_producto)';
        }

        if ($searchTerm !== '') {
            $productsFrom .= ' AND (p.nombre LIKE :search_name OR p.codigo_producto LIKE :search_code)';
            $productsParams['search_name'] = '%' . $searchTerm . '%';
            $productsParams['search_code'] = '%' . $searchTerm . '%';
        }

        $countStatement = $connection->prepare('SELECT COUNT(*)' . $productsFrom);

        foreach ($productsParams as $parameter => $value) {
            $countStatement->bindValue(':' . $parameter, $value);
        }

        $countStatement->execute();
        $totalProducts = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($totalProducts / $itemsPerPage));
        $currentPage = min($requestedPage, $totalPages);
        $offset = ($currentPage - 1) * $itemsPerPage;

        $productsQuery = "SELECT
                p.id_producto,
                p.codigo_producto,
                p.nombre,
                p.precio_venta,
                p.promocion_activa,
                p.precio_promocional,
                p.texto_promocion,
                p.estado,
                p.fecha_creacion,
                c.nombre AS categoria_nombre,
                COALESCE(i.stock_actual, 0) AS stock_actual,
                pi.url_imagen,
                (SELECT COUNT(*) FROM producto_imagenes img WHERE img.id_producto = p.id_producto) AS total_imagenes
             " . $productsFrom . ' ORDER BY p.id_producto DESC LIMIT :limit OFFSET :offset';
        $productsStatement = $connection->prepare($productsQuery);

        foreach ($productsParams as $parameter => $value) {
            $productsStatement->bindValue(':' . $parameter, $value);
        }

        $productsStatement->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $productsStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $productsStatement->execute();
        $products = $productsStatement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        $errors[] = 'No se pudo cargar el listado de productos. ' . $exception->getMessage();
    }
}

$isEditMode = $editingProductId !== null && $formData['id_producto'] !== '';
$filtersApplied = $selectedStatusFilter !== 'all' || $selectedImageFilter !== 'all' || $searchTerm !== '';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

$productPreviewUrl = $isEditMode
    ? BASE_URL . '/views/detalle_producto.php?id=' . (int) $formData['id_producto']
    : '';
$canPreviewProduct = $isEditMode && $formData['estado'] === 'ACTIVO';
$buildAdminProductsUrl = static function (array $overrides = []) use (
    $isEditMode,
    $formData,
    $selectedStatusFilter,
    $selectedImageFilter,
    $searchTerm
): string {
    $params = [];

    if ($isEditMode) {
        $params['edit'] = (int) $formData['id_producto'];
    }

    if ($selectedStatusFilter !== 'all') {
        $params['filter_status'] = $selectedStatusFilter;
    }

    if ($selectedImageFilter !== 'all') {
        $params['filter_images'] = $selectedImageFilter;
    }

    if ($searchTerm !== '') {
        $params['search'] = $searchTerm;
    }

    $params = array_merge($params, $overrides);

    if (($params['filter_status'] ?? 'all') === 'all') {
        unset($params['filter_status']);
    }

    if (($params['filter_images'] ?? 'all') === 'all') {
        unset($params['filter_images']);
    }

    if (($params['search'] ?? '') === '') {
        unset($params['search']);
    }

    if (($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }

    return BASE_URL . '/admin/productos/index.php' . ($params !== [] ? '?' . http_build_query($params) : '');
};
$resetFiltersUrl = $buildAdminProductsUrl([
    'filter_status' => 'all',
    'filter_images' => 'all',
    'search' => '',
    'page' => 1,
]);
?>
<section class="page-section admin-heading">
    <div>
        <span class="section-label">Panel admin</span>
        <h1 class="page-title">Gestion de productos e imagenes</h1>
        <p class="lead">Controla ficha, stock y galería desde una sola pantalla.</p>
    </div>
    <div class="admin-kpis">
        <article class="hero-stat">
            <span>Categorias activas</span>
            <strong><?php echo count($categories); ?></strong>
        </article>
        <article class="hero-stat">
            <span>Productos listados</span>
            <strong><?php echo $totalProducts; ?></strong>
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
    <div class="page-section admin-form-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title"><?php echo $isEditMode ? 'Editar producto' : 'Nuevo producto'; ?></h2>
                <p class="admin-mode-copy">
                    <?php echo $isEditMode ? 'Edita datos, cambia imágenes o reordena la galería.' : 'Crea un producto con stock e imágenes.'; ?>
                </p>
            </div>
            <?php if ($isEditMode): ?>
                <a class="button button-secondary" href="<?php echo BASE_URL; ?>/admin/productos/index.php">Cancelar edicion</a>
            <?php endif; ?>
        </div>

        <form class="admin-form-grid" action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $isEditMode ? 'update' : 'create'; ?>">
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($formData['id_producto'], ENT_QUOTES, 'UTF-8'); ?>">

            <label>
                Categoria
                <select name="id_categoria" required>
                    <option value="">Selecciona una categoria</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int) $category['id_categoria']; ?>" <?php echo (string) $category['id_categoria'] === $formData['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Codigo del producto
                <input type="text" name="codigo_producto" value="<?php echo htmlspecialchars($formData['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Si lo dejas vacío se genera automáticamente">
            </label>

            <label class="admin-field-span-2">
                Nombre del producto
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($formData['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label class="admin-field-span-2">
                Descripcion
                <textarea name="descripcion" rows="5" placeholder="Describe el producto como lo vería el cliente en la tienda"><?php echo htmlspecialchars($formData['descripcion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>

            <label>
                Precio de compra
                <input type="number" name="precio_compra" step="0.01" min="0" value="<?php echo htmlspecialchars($formData['precio_compra'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Precio de venta
                <input type="number" name="precio_venta" step="0.01" min="0" value="<?php echo htmlspecialchars($formData['precio_venta'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Promocion activa
                <select name="promocion_activa">
                    <option value="0" <?php echo $formData['promocion_activa'] === '0' ? 'selected' : ''; ?>>No</option>
                    <option value="1" <?php echo $formData['promocion_activa'] === '1' ? 'selected' : ''; ?>>Si</option>
                </select>
            </label>

            <label>
                Precio promocional
                <input type="number" name="precio_promocional" step="0.01" min="0" value="<?php echo htmlspecialchars($formData['precio_promocional'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcional si hay promocion">
            </label>

            <label class="admin-field-span-2">
                Texto promocional
                <input type="text" name="texto_promocion" maxlength="120" value="<?php echo htmlspecialchars($formData['texto_promocion'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ejemplo: Descuento especial por temporada">
                <span class="admin-help-text">Se mostrará cuando la promoción esté activa.</span>
            </label>

            <label>
                Stock actual
                <input type="number" name="stock_actual" min="0" value="<?php echo htmlspecialchars($formData['stock_actual'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Stock minimo
                <input type="number" name="stock_minimo" min="0" value="<?php echo htmlspecialchars($formData['stock_minimo'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label>
                Estado
                <select name="estado">
                    <option value="ACTIVO" <?php echo $formData['estado'] === 'ACTIVO' ? 'selected' : ''; ?>>ACTIVO</option>
                    <option value="INACTIVO" <?php echo $formData['estado'] === 'INACTIVO' ? 'selected' : ''; ?>>INACTIVO</option>
                </select>
            </label>

            <label class="admin-field-span-2">
                <?php echo $isEditMode ? 'Reemplazar o agregar imagen principal' : 'Imagen principal'; ?>
                <input type="file" name="imagen_principal" accept="image/png,image/jpeg,image/webp,image/gif">
                <span class="admin-help-text">La nueva principal deja las demás en galería.</span>
            </label>

            <label class="admin-field-span-2">
                Agregar imagenes a la galeria
                <input type="file" name="imagenes_galeria[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                <span class="admin-help-text">Puedes subir varias. Máximo 5 MB por imagen.</span>
            </label>

            <div class="admin-actions admin-field-span-2">
                <button class="button button-primary" type="submit"><?php echo $isEditMode ? 'Actualizar producto' : 'Guardar producto'; ?></button>
                <?php if ($canPreviewProduct): ?>
                    <button
                        class="button button-secondary"
                        type="button"
                        data-admin-preview-toggle
                        aria-expanded="false"
                        aria-controls="admin-preview-panel"
                    >Vista rapida</button>
                    <a class="button button-secondary" href="<?php echo htmlspecialchars($productPreviewUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Vista publica</a>
                <?php elseif ($isEditMode): ?>
                    <span class="admin-help-text admin-preview-note">Actívalo para verlo en tienda.</span>
                <?php endif; ?>
                <a class="button button-secondary" href="<?php echo BASE_URL; ?>/views/catalogo.php">Ver catalogo</a>
            </div>
        </form>

        <?php if ($isEditMode && !$canPreviewProduct): ?>
            <section class="admin-visibility-alert admin-visibility-alert-hidden">
                <div class="admin-visibility-copy">
                    <span class="admin-status-badge admin-status-badge-hidden">Oculto en la tienda</span>
                    <strong>Este producto no aparece en la tienda.</strong>
                    <p>Pásalo a ACTIVO para habilitar su vista previa.</p>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canPreviewProduct): ?>
            <section class="admin-preview-panel" id="admin-preview-panel" hidden data-admin-preview-panel>
                <div class="section-head admin-preview-head">
                    <div>
                        <h3 class="section-title section-title-small">Vista rapida dentro del admin</h3>
                        <p class="admin-help-text">Muestra la ficha pública sin salir del panel.</p>
                    </div>
                    <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars($productPreviewUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Abrir completa</a>
                </div>
                <div class="admin-preview-frame-wrap">
                    <iframe
                        class="admin-preview-frame"
                        title="Vista previa del producto"
                        loading="lazy"
                        data-admin-preview-frame
                        data-preview-src="<?php echo htmlspecialchars($productPreviewUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    ></iframe>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isEditMode): ?>
            <div class="admin-current-images">
                <div class="section-head admin-current-images-head">
                    <h3 class="section-title section-title-small">Imagenes actuales</h3>
                    <p class="admin-help-text">Marca principal, reordena o reemplaza archivos.</p>
                </div>
                <?php if ($currentImages !== []): ?>
                    <?php
                    $galleryImageIds = array_values(array_map(
                        static fn (array $image): int => (int) $image['id_imagen'],
                        array_filter(
                            $currentImages,
                            static fn (array $image): bool => (int) $image['principal'] !== 1
                        )
                    ));
                    $galleryPositions = array_flip($galleryImageIds);
                    $galleryCount = count($galleryImageIds);
                    ?>
                    <form method="post" action="" data-admin-sort-form>
                        <input type="hidden" name="action" value="reorder_images">
                        <input type="hidden" name="product_id" value="<?php echo (int) $formData['id_producto']; ?>">
                        <input type="hidden" name="image_order" value="" data-admin-image-order>
                    </form>
                    <div class="admin-image-grid" data-admin-gallery>
                        <?php foreach ($currentImages as $image): ?>
                            <?php $imageUrl = storefrontImageUrl($image['url_imagen'] ?? ''); ?>
                            <?php $isPrimaryImage = (int) $image['principal'] === 1; ?>
                            <?php $galleryPosition = $galleryPositions[(int) $image['id_imagen']] ?? null; ?>
                            <article
                                class="admin-image-card <?php echo !$isPrimaryImage ? 'admin-image-card-sortable' : 'admin-image-card-primary'; ?>"
                                <?php if (!$isPrimaryImage): ?>
                                    draggable="true"
                                    data-gallery-item
                                    data-image-id="<?php echo (int) $image['id_imagen']; ?>"
                                <?php endif; ?>
                            >
                                <div class="admin-image-preview">
                                    <?php if ($imageUrl !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen del producto">
                                    <?php endif; ?>
                                </div>
                                <div class="admin-image-meta admin-image-meta-stack">
                                    <span class="admin-image-badge <?php echo $isPrimaryImage ? 'admin-image-badge-primary' : ''; ?>">
                                        <?php echo $isPrimaryImage ? 'Principal' : 'Galeria'; ?>
                                    </span>
                                    <?php if (!$isPrimaryImage && $galleryPosition !== null): ?>
                                        <span class="admin-image-badge">Posicion <?php echo $galleryPosition + 1; ?></span>
                                        <span class="admin-image-badge">Arrastra para ordenar</span>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-inline-actions">
                                    <?php if (!$isPrimaryImage): ?>
                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="make_principal">
                                            <input type="hidden" name="product_id" value="<?php echo (int) $formData['id_producto']; ?>">
                                            <input type="hidden" name="image_id" value="<?php echo (int) $image['id_imagen']; ?>">
                                            <button class="button button-secondary admin-small-button" type="submit">Hacer principal</button>
                                        </form>
                                        <?php if ($imageOrderingAvailable && $galleryPosition !== null && $galleryPosition > 0): ?>
                                            <form method="post" action="">
                                                <input type="hidden" name="action" value="move_image">
                                                <input type="hidden" name="direction" value="up">
                                                <input type="hidden" name="product_id" value="<?php echo (int) $formData['id_producto']; ?>">
                                                <input type="hidden" name="image_id" value="<?php echo (int) $image['id_imagen']; ?>">
                                                <button class="button button-secondary admin-small-button" type="submit">Subir</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($imageOrderingAvailable && $galleryPosition !== null && $galleryPosition < $galleryCount - 1): ?>
                                            <form method="post" action="">
                                                <input type="hidden" name="action" value="move_image">
                                                <input type="hidden" name="direction" value="down">
                                                <input type="hidden" name="product_id" value="<?php echo (int) $formData['id_producto']; ?>">
                                                <input type="hidden" name="image_id" value="<?php echo (int) $image['id_imagen']; ?>">
                                                <button class="button button-secondary admin-small-button" type="submit">Bajar</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <form method="post" action="" onsubmit="return confirm('¿Eliminar esta imagen?');">
                                        <input type="hidden" name="action" value="delete_image">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $formData['id_producto']; ?>">
                                        <input type="hidden" name="image_id" value="<?php echo (int) $image['id_imagen']; ?>">
                                        <button class="button button-danger admin-small-button" type="submit">Eliminar</button>
                                    </form>
                                </div>
                                <form class="admin-replace-form" method="post" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="replace_image">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $formData['id_producto']; ?>">
                                    <input type="hidden" name="image_id" value="<?php echo (int) $image['id_imagen']; ?>">
                                    <label class="admin-replace-input">
                                        <span>Reemplazar archivo</span>
                                        <input type="file" name="replacement_image" accept="image/png,image/jpeg,image/webp,image/gif" required>
                                    </label>
                                    <button class="button button-secondary admin-small-button" type="submit">Subir reemplazo</button>
                                    <p class="admin-inline-note">Mantiene rol y posición.</p>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="collection-empty">
                        <strong>Este producto todavia no tiene imagenes guardadas.</strong>
                        <p>Sube una principal o agrega varias a la galería desde los campos anteriores.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</section>

<section class="page-section admin-products-panel">
    <div class="section-head">
        <div>
            <h2 class="section-title">Productos registrados</h2>
            <p class="admin-help-text">Busca por nombre o SKU y filtra por estado o imágenes.</p>
        </div>
    </div>

    <form class="admin-filter-bar" method="get" action="" data-admin-search-form>
        <?php if ($isEditMode): ?>
            <input type="hidden" name="edit" value="<?php echo (int) $formData['id_producto']; ?>">
        <?php endif; ?>

        <label class="admin-filter-search">
            Buscar
            <input
                type="search"
                name="search"
                value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Nombre o SKU del producto"
                data-admin-search-input
            >
            <span class="admin-filter-hint">La búsqueda se aplica sola al dejar de escribir.</span>
        </label>

        <label>
            Estado
            <select name="filter_status">
                <option value="all" <?php echo $selectedStatusFilter === 'all' ? 'selected' : ''; ?>>Todos</option>
                <option value="ACTIVO" <?php echo $selectedStatusFilter === 'ACTIVO' ? 'selected' : ''; ?>>Solo activos</option>
                <option value="INACTIVO" <?php echo $selectedStatusFilter === 'INACTIVO' ? 'selected' : ''; ?>>Solo inactivos</option>
            </select>
        </label>

        <label>
            Imagenes
            <select name="filter_images">
                <option value="all" <?php echo $selectedImageFilter === 'all' ? 'selected' : ''; ?>>Todas</option>
                <option value="without_images" <?php echo $selectedImageFilter === 'without_images' ? 'selected' : ''; ?>>Sin imagenes</option>
                <option value="with_images" <?php echo $selectedImageFilter === 'with_images' ? 'selected' : ''; ?>>Con imagenes</option>
            </select>
        </label>

        <div class="admin-filter-actions">
            <button class="button button-secondary admin-small-button" type="submit">Aplicar filtros</button>
            <?php if ($filtersApplied): ?>
                <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars($resetFiltersUrl, ENT_QUOTES, 'UTF-8'); ?>">Limpiar filtros</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($filtersApplied): ?>
        <div class="admin-filter-summary">
            <strong><?php echo $totalProducts; ?> producto(s) encontrado(s)</strong>
            <span>
                Estado: <?php echo $selectedStatusFilter === 'all' ? 'Todos' : htmlspecialchars($selectedStatusFilter, ENT_QUOTES, 'UTF-8'); ?>
                | Imagenes: <?php
                echo match ($selectedImageFilter) {
                    'without_images' => 'Sin imagenes',
                    'with_images' => 'Con imagenes',
                    default => 'Todas',
                };
                ?>
                <?php if ($searchTerm !== ''): ?>
                    | Busqueda: <?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
            <?php if ($totalPages > 1): ?>
                <span>Pagina <?php echo $currentPage; ?> de <?php echo $totalPages; ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($products !== []): ?>
        <div class="admin-table-wrap">
            <table class="table admin-table-cardify">
                <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>Categoria</th>
                    <th>Precio</th>
                    <th>Promocion</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Visibilidad</th>
                    <th>Imagenes</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <?php $productImage = storefrontImageUrl($product['url_imagen'] ?? ''); ?>
                    <?php $isProductActive = $product['estado'] === 'ACTIVO'; ?>
                    <?php $hasPromotion = storefrontProductHasPromotion($product); ?>
                    <tr>
                        <td data-label="Imagen">
                            <?php if ($productImage !== ''): ?>
                                <img class="admin-product-thumb" src="<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <div class="admin-product-thumb admin-product-thumb-placeholder"><?php echo htmlspecialchars(storefrontInitial($product['categoria_nombre']), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Producto">
                            <strong><?php echo htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="shop-card-sku">SKU: <?php echo htmlspecialchars($product['codigo_producto'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td data-label="Categoria"><?php echo htmlspecialchars($product['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Precio">
                            <strong>S/ <?php echo number_format((float) storefrontProductCurrentPrice($product), 2); ?></strong>
                            <?php if ($hasPromotion): ?>
                                <div class="admin-help-text">Regular: S/ <?php echo number_format((float) $product['precio_venta'], 2); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Promocion">
                            <?php if ($hasPromotion): ?>
                                <span class="admin-status-badge admin-status-badge-visible">Activa</span>
                                <div class="admin-help-text"><?php echo htmlspecialchars((string) ($product['texto_promocion'] ?: 'Precio especial activo'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                <span class="admin-help-text">Sin promocion</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Stock"><?php echo (int) $product['stock_actual']; ?></td>
                        <td data-label="Estado">
                            <span class="admin-status-badge <?php echo $isProductActive ? 'admin-status-badge-visible' : 'admin-status-badge-hidden'; ?>">
                                <?php echo htmlspecialchars($product['estado'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td data-label="Visibilidad">
                            <div class="admin-visibility-cell">
                                <strong class="admin-visibility-label <?php echo $isProductActive ? 'admin-visibility-label-visible' : 'admin-visibility-label-hidden'; ?>">
                                    <?php echo $isProductActive ? 'Visible en tienda' : 'No visible en tienda'; ?>
                                </strong>
                                <span class="admin-visibility-note">
                                    <?php echo $isProductActive ? 'Aparece en catálogo y detalle.' : 'Actívalo para que los clientes puedan verlo.'; ?>
                                </span>
                            </div>
                        </td>
                        <td data-label="Imagenes"><?php echo (int) $product['total_imagenes']; ?></td>
                        <td data-label="Fecha"><?php echo htmlspecialchars((string) $product['fecha_creacion'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Acciones">
                            <div class="admin-table-actions">
                                <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/admin/productos/index.php?edit=<?php echo (int) $product['id_producto']; ?>">Editar</a>
                                <?php if ($isProductActive): ?>
                                    <a class="button button-secondary admin-small-button" href="<?php echo BASE_URL; ?>/views/detalle_producto.php?id=<?php echo (int) $product['id_producto']; ?>" target="_blank" rel="noopener noreferrer">Ver tienda</a>
                                <?php else: ?>
                                    <span class="admin-action-hint">Primero actívalo para verlo en tienda</span>
                                <?php endif; ?>
                                <form method="post" action="" onsubmit="return confirm('¿Eliminar este producto y sus imágenes?');">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $product['id_producto']; ?>">
                                    <button class="button button-danger admin-small-button" type="submit">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-pagination" aria-label="Paginacion de productos">
                <?php if ($currentPage > 1): ?>
                    <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars($buildAdminProductsUrl(['page' => $currentPage - 1]), ENT_QUOTES, 'UTF-8'); ?>">Anterior</a>
                <?php endif; ?>

                <div class="admin-pagination-pages">
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a
                            class="admin-pagination-link <?php echo $pageNumber === $currentPage ? 'is-active' : ''; ?>"
                            href="<?php echo htmlspecialchars($buildAdminProductsUrl(['page' => $pageNumber]), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <?php echo $pageNumber; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($currentPage < $totalPages): ?>
                    <a class="button button-secondary admin-small-button" href="<?php echo htmlspecialchars($buildAdminProductsUrl(['page' => $currentPage + 1]), ENT_QUOTES, 'UTF-8'); ?>">Siguiente</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="collection-empty">
            <strong><?php echo $filtersApplied ? 'No hay productos que coincidan con la busqueda o filtros.' : 'Todavia no has registrado productos desde el panel.'; ?></strong>
            <p><?php echo $filtersApplied ? 'Prueba con otro nombre, SKU o limpia los filtros para volver al listado completo.' : 'Usa el formulario superior para crear el primero con imágenes y stock.'; ?></p>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
