<?php
require_once __DIR__ . '/../config/conexion.php';

function storefrontConnection(): ?PDO
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

function storefrontHasImageOrderColumn(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $connection = storefrontConnection();

    if (!$connection) {
        $available = false;
        return $available;
    }

    $statement = $connection->query("SHOW COLUMNS FROM producto_imagenes LIKE 'orden'");
    $available = $statement !== false && $statement->fetch() !== false;

    return $available;
}

function storefrontImageOrderClause(): string
{
    if (storefrontHasImageOrderColumn()) {
        return 'principal DESC, orden ASC, id_imagen ASC';
    }

    return 'principal DESC, id_imagen ASC';
}

function storefrontCategories(): array
{
    $connection = storefrontConnection();

    if (!$connection) {
        return [];
    }

    $statement = $connection->query(
        "SELECT
            c.id_categoria,
            c.nombre,
            c.descripcion,
            COUNT(p.id_producto) AS product_count
         FROM categorias c
         LEFT JOIN productos p ON p.id_categoria = c.id_categoria AND p.estado = 'ACTIVO'
         WHERE c.estado = 1
         GROUP BY c.id_categoria, c.nombre, c.descripcion
         ORDER BY c.id_categoria ASC"
    );

    return $statement->fetchAll() ?: [];
}

function storefrontCategorySections(int $limitPerCategory = 6): array
{
    $sections = [];

    foreach (storefrontCategories() as $category) {
        $category['products'] = storefrontProducts((int) $category['id_categoria'], '', $limitPerCategory);

        if ($category['products'] === []) {
            continue;
        }

        $sections[] = $category;
    }

    return $sections;
}

function storefrontMetrics(): array
{
    $connection = storefrontConnection();

    if (!$connection) {
        return [
            'categorias' => 0,
            'productos' => 0,
            'stock' => 0,
        ];
    }

    $statement = $connection->query(
        "SELECT
            (SELECT COUNT(*) FROM categorias WHERE estado = 1) AS categorias,
            COUNT(*) AS productos,
            COALESCE(SUM(COALESCE(i.stock_actual, 0)), 0) AS stock
         FROM productos p
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         WHERE p.estado = 'ACTIVO'"
    );

    $metrics = $statement->fetch() ?: [];

    return [
        'categorias' => (int) ($metrics['categorias'] ?? 0),
        'productos' => (int) ($metrics['productos'] ?? 0),
        'stock' => (int) ($metrics['stock'] ?? 0),
    ];
}

function storefrontPromoProducts(int $limit = 3): array
{
    $connection = storefrontConnection();

    if (!$connection) {
        return [];
    }

    $statement = $connection->prepare(
        "SELECT
            p.id_producto,
            p.id_categoria,
            p.codigo_producto,
            p.nombre,
            p.descripcion,
            p.precio_venta,
            p.promocion_activa,
            p.precio_promocional,
            p.texto_promocion,
            p.fecha_creacion,
            c.nombre AS categoria_nombre,
            COALESCE(i.stock_actual, 0) AS stock_actual,
            pi.url_imagen
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.estado = 1
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.principal = 1
         WHERE p.estado = 'ACTIVO'
           AND p.promocion_activa = 1
           AND p.precio_promocional IS NOT NULL
           AND p.precio_promocional > 0
           AND p.precio_promocional < p.precio_venta
         ORDER BY p.fecha_creacion DESC, p.id_producto DESC
         LIMIT :limit"
    );
    $statement->bindValue(':limit', max(1, min($limit, 6)), PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll() ?: [];
}

function storefrontProductHasPromotion(array $product): bool
{
    return (int) ($product['promocion_activa'] ?? 0) === 1
        && isset($product['precio_promocional'])
        && (float) $product['precio_promocional'] > 0
        && (float) $product['precio_promocional'] < (float) ($product['precio_venta'] ?? 0);
}

function storefrontProductCurrentPrice(array $product): float
{
    if (storefrontProductHasPromotion($product)) {
        return (float) $product['precio_promocional'];
    }

    return (float) ($product['precio_venta'] ?? 0);
}

function storefrontProductOriginalPrice(array $product): ?float
{
    if (!storefrontProductHasPromotion($product)) {
        return null;
    }

    return (float) ($product['precio_venta'] ?? 0);
}

function storefrontProductDiscountPercent(array $product): ?int
{
    if (!storefrontProductHasPromotion($product)) {
        return null;
    }

    $originalPrice = (float) ($product['precio_venta'] ?? 0);
    $currentPrice = (float) storefrontProductCurrentPrice($product);

    if ($originalPrice <= 0 || $currentPrice <= 0 || $currentPrice >= $originalPrice) {
        return null;
    }

    return (int) round((($originalPrice - $currentPrice) / $originalPrice) * 100);
}

function storefrontProductsOrderBy(string $sort = 'recent'): string
{
    switch ($sort) {
        case 'price_asc':
            return 'storefront_price_sub ASC, id_producto DESC';
        case 'price_desc':
            return 'storefront_price_sub DESC, id_producto DESC';
        case 'name_asc':
            return 'nombre ASC, id_producto DESC';
        case 'stock_desc':
            return 'stock_actual DESC, id_producto DESC';
        case 'recent':
        default:
            return 'fecha_creacion DESC, id_producto DESC';
    }
}

function storefrontProductsBuildWhere(
    ?int $categoryId,
    string $search,
    ?float $priceMin,
    ?float $priceMax,
    bool $onlyInStock,
    bool $onlyOnSale,
    array &$params
): string {
    $conditions = ['1=1'];

    if ($priceMin !== null && $priceMin > 0) {
        $conditions[] = 'storefront_price_sub >= :price_min';
        $params['price_min'] = $priceMin;
    }

    if ($priceMax !== null && $priceMax > 0) {
        $conditions[] = 'storefront_price_sub <= :price_max';
        $params['price_max'] = $priceMax;
    }

    if ($onlyInStock) {
        $conditions[] = 'stock_actual > 0';
    }

    if ($onlyOnSale) {
        $conditions[] = "(promocion_activa = 1 AND precio_promocional IS NOT NULL AND precio_promocional > 0 AND precio_promocional < precio_venta)";
    }

    return implode(' AND ', $conditions);
}

function storefrontProductsInnerWhere(?int $categoryId, string $search, array &$params): string
{
    $conditions = ["p.estado = 'ACTIVO'"];

    if ($categoryId !== null && $categoryId > 0) {
        $conditions[] = 'p.id_categoria = :category_id';
        $params['category_id'] = $categoryId;
    }

    if ($search !== '') {
        $conditions[] = '(p.nombre LIKE :search_name OR p.codigo_producto LIKE :search_code OR c.nombre LIKE :search_category)';
        $pattern = '%' . $search . '%';
        $params['search_name'] = $pattern;
        $params['search_code'] = $pattern;
        $params['search_category'] = $pattern;
    }

    return implode(' AND ', $conditions);
}

function storefrontPriceSubquery(): string
{
    return "CASE
                WHEN p.promocion_activa = 1
                    AND p.precio_promocional IS NOT NULL
                    AND p.precio_promocional > 0
                    AND p.precio_promocional < p.precio_venta
                THEN p.precio_promocional
                ELSE p.precio_venta
            END";
}

function storefrontProducts(
    ?int $categoryId = null,
    string $search = '',
    int $limit = 12,
    int $offset = 0,
    string $sort = 'recent',
    ?float $priceMin = null,
    ?float $priceMax = null,
    bool $onlyInStock = false,
    bool $onlyOnSale = false
): array {
    $connection = storefrontConnection();

    if (!$connection) {
        return [];
    }

    $params = [];
    $priceExpr = storefrontPriceSubquery();
    $innerWhere = storefrontProductsInnerWhere($categoryId, $search, $params);
    $outerWhere = storefrontProductsBuildWhere($categoryId, $search, $priceMin, $priceMax, $onlyInStock, $onlyOnSale, $params);

    $sql = "SELECT * FROM (
                SELECT
                    p.id_producto,
                    p.id_categoria,
                    p.codigo_producto,
                    p.nombre,
                    p.descripcion,
                    p.precio_venta,
                    p.promocion_activa,
                    p.precio_promocional,
                    p.texto_promocion,
                    p.fecha_creacion,
                    c.nombre AS categoria_nombre,
                    COALESCE(i.stock_actual, 0) AS stock_actual,
                    ({$priceExpr}) AS storefront_price_sub,
                    pi.url_imagen
                FROM productos p
                INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.estado = 1
                LEFT JOIN inventario i ON i.id_producto = p.id_producto
                LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.principal = 1
                WHERE {$innerWhere}
            ) AS plist
            WHERE {$outerWhere}
            ORDER BY " . storefrontProductsOrderBy($sort) . "
            LIMIT :limit OFFSET :offset";

    $statement = $connection->prepare($sql);

    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }

    $statement->bindValue(':limit', max(1, min($limit, 120)), PDO::PARAM_INT);
    $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll() ?: [];
}

function storefrontProductsCount(
    ?int $categoryId = null,
    string $search = '',
    ?float $priceMin = null,
    ?float $priceMax = null,
    bool $onlyInStock = false,
    bool $onlyOnSale = false
): int {
    $connection = storefrontConnection();

    if (!$connection) {
        return 0;
    }

    $params = [];
    $priceExpr = storefrontPriceSubquery();
    $innerWhere = storefrontProductsInnerWhere($categoryId, $search, $params);
    $outerWhere = storefrontProductsBuildWhere($categoryId, $search, $priceMin, $priceMax, $onlyInStock, $onlyOnSale, $params);

    $sql = "SELECT COUNT(*) FROM (
                SELECT
                    p.id_producto,
                    ({$priceExpr}) AS storefront_price_sub,
                    COALESCE(i.stock_actual, 0) AS stock_actual,
                    p.promocion_activa,
                    p.precio_promocional,
                    p.precio_venta
                FROM productos p
                INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.estado = 1
                LEFT JOIN inventario i ON i.id_producto = p.id_producto
                WHERE {$innerWhere}
            ) AS plist
            WHERE {$outerWhere}";

    $statement = $connection->prepare($sql);

    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }

    $statement->execute();

    return (int) ($statement->fetchColumn() ?: 0);
}

function storefrontPriceRange(?int $categoryId = null, string $search = ''): array
{
    $connection = storefrontConnection();

    if (!$connection) {
        return ['min' => 0, 'max' => 0];
    }

    $priceExpr = storefrontPriceSubquery();
    $params = [];
    $conditions = ["p.estado = 'ACTIVO'"];

    if ($categoryId !== null && $categoryId > 0) {
        $conditions[] = 'p.id_categoria = :category_id';
        $params['category_id'] = $categoryId;
    }

    if ($search !== '') {
        $conditions[] = '(p.nombre LIKE :search_name OR p.codigo_producto LIKE :search_code OR c.nombre LIKE :search_category)';
        $pattern = '%' . $search . '%';
        $params['search_name'] = $pattern;
        $params['search_code'] = $pattern;
        $params['search_category'] = $pattern;
    }

    $where = implode(' AND ', $conditions);

    $sql = "SELECT
                FLOOR(MIN({$priceExpr})) AS min_price,
                CEIL(MAX({$priceExpr})) AS max_price
            FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.estado = 1
            LEFT JOIN inventario i ON i.id_producto = p.id_producto
            WHERE {$where}";

    $statement = $connection->prepare($sql);

    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }

    $statement->execute();
    $row = $statement->fetch();

    return [
        'min' => (float) ($row['min_price'] ?? 0),
        'max' => (float) ($row['max_price'] ?? 0),
    ];
}

function storefrontProduct(?int $productId = null): ?array
{
    $connection = storefrontConnection();

    if (!$connection) {
        return null;
    }

    $sql = "SELECT
                p.id_producto,
                p.id_categoria,
                p.codigo_producto,
                p.nombre,
                p.descripcion,
                p.precio_venta,
                p.promocion_activa,
                p.precio_promocional,
                p.texto_promocion,
                p.fecha_creacion,
                c.nombre AS categoria_nombre,
                COALESCE(i.stock_actual, 0) AS stock_actual,
                pi.url_imagen
            FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.estado = 1
            LEFT JOIN inventario i ON i.id_producto = p.id_producto
            LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.principal = 1
            WHERE p.estado = 'ACTIVO'";

    if ($productId !== null && $productId > 0) {
        $sql .= ' AND p.id_producto = :product_id ORDER BY p.id_producto DESC LIMIT 1';
        $statement = $connection->prepare($sql);
        $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
    } else {
        $sql .= ' ORDER BY p.fecha_creacion DESC, p.id_producto DESC LIMIT 1';
        $statement = $connection->prepare($sql);
    }

    $statement->execute();
    $product = $statement->fetch();

    if (!$product) {
        return null;
    }

    $galleryStatement = $connection->prepare(
        "SELECT url_imagen, principal
         FROM producto_imagenes
         WHERE id_producto = :product_id
            ORDER BY " . storefrontImageOrderClause()
    );
    $galleryStatement->bindValue(':product_id', (int) $product['id_producto'], PDO::PARAM_INT);
    $galleryStatement->execute();
    $product['galeria'] = $galleryStatement->fetchAll() ?: [];

    return $product;
}

function storefrontRelatedProducts(int $categoryId, int $excludeProductId, int $limit = 3): array
{
    $connection = storefrontConnection();

    if (!$connection) {
        return [];
    }

    $statement = $connection->prepare(
        "SELECT
            p.id_producto,
            p.id_categoria,
            p.codigo_producto,
            p.nombre,
            p.descripcion,
            p.precio_venta,
            p.promocion_activa,
            p.precio_promocional,
            p.texto_promocion,
            c.nombre AS categoria_nombre,
            COALESCE(i.stock_actual, 0) AS stock_actual,
            pi.url_imagen
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.estado = 1
         LEFT JOIN inventario i ON i.id_producto = p.id_producto
         LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.principal = 1
         WHERE p.estado = 'ACTIVO'
           AND p.id_categoria = :category_id
           AND p.id_producto <> :exclude_product_id
         ORDER BY p.fecha_creacion DESC, p.id_producto DESC
         LIMIT :limit"
    );
    $statement->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $statement->bindValue(':exclude_product_id', $excludeProductId, PDO::PARAM_INT);
    $statement->bindValue(':limit', max(1, min($limit, 12)), PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll() ?: [];
}

function storefrontExcerpt(?string $text, int $limit = 120): string
{
    $cleanText = trim((string) preg_replace('/\s+/', ' ', (string) $text));

    if ($cleanText === '') {
        return 'Producto listo para personalizar desde tu inventario y catálogo.';
    }

    if (function_exists('mb_strlen') && mb_strlen($cleanText) > $limit) {
        return rtrim(mb_substr($cleanText, 0, $limit - 1)) . '...';
    }

    if (strlen($cleanText) > $limit) {
        return rtrim(substr($cleanText, 0, $limit - 1)) . '...';
    }

    return $cleanText;
}

function storefrontImageUrl(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    $baseUrl = defined('BASE_URL') ? BASE_URL : '';

    return $baseUrl . '/' . ltrim($path, '/');
}

function storefrontInitial(?string $text): string
{
    $text = trim((string) $text);

    if ($text === '') {
        return '?';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($text, 0, 1));
    }

    return strtoupper(substr($text, 0, 1));
}