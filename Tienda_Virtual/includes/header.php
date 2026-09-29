<?php
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../config/app.php';

adminAuthStartSession();

if (!defined('BASE_URL')) {
    define('BASE_URL', appBaseUrl());
}

if (!defined('STORE_LOGO_URL')) {
    $logoCandidates = glob(__DIR__ . '/../public/img/*.{png,jpg,jpeg,webp,gif,svg,ico}', GLOB_BRACE) ?: [];
    $logoCandidates = array_values(array_filter(
        $logoCandidates,
        static fn (string $path): bool => is_file($path)
    ));

    usort($logoCandidates, static function (string $left, string $right): int {
        $leftName = strtolower(pathinfo($left, PATHINFO_FILENAME));
        $rightName = strtolower(pathinfo($right, PATHINFO_FILENAME));
        $leftScore = str_contains($leftName, 'logo') || str_contains($leftName, 'gerson') ? 0 : 1;
        $rightScore = str_contains($rightName, 'logo') || str_contains($rightName, 'gerson') ? 0 : 1;

        if ($leftScore !== $rightScore) {
            return $leftScore <=> $rightScore;
        }

        return strcmp($leftName, $rightName);
    });

    $logoPath = $logoCandidates[0] ?? null;
    $logoUrl = null;

    if ($logoPath) {
        $logoUrl = BASE_URL . '/public/img/' . rawurlencode(basename($logoPath));
    }

    define('STORE_LOGO_URL', $logoUrl);
}

if (!defined('STORE_IS_ADMIN_AREA')) {
    $headerRequestPath = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    define('STORE_IS_ADMIN_AREA', strpos($headerRequestPath, '/admin/') !== false);
}

$storeName = (string) appConfigGet('app.name', "Gerson's Store");
$pageTitle = $pageTitle ?? $storeName;
$documentTitle = $pageTitle === $storeName ? $storeName : $pageTitle . ' | ' . $storeName;
$pageDescription = $pageDescription ?? 'Joyería, perfumes y regalos en ' . $storeName . '. Explora el catálogo, aprovecha las ofertas y haz tu pedido por WhatsApp.';
$pageImage = $pageImage ?? null;

$assetVersion = static function (string $relativePath): string {
    $path = __DIR__ . '/../' . $relativePath;

    return is_file($path) ? (string) filemtime($path) : '1';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!STORE_IS_ADMIN_AREA): ?>
        <meta name="theme-color" content="#0e2547">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="<?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:title" content="<?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($pageImage): ?>
            <meta property="og:image" content="<?php echo htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
    <?php else: ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if (STORE_LOGO_URL): ?>
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>">
        <link rel="shortcut icon" href="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>">
        <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if (STORE_IS_ADMIN_AREA): ?>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <?php else: ?>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/styles.css?v=<?php echo urlencode($assetVersion('public/css/styles.css')); ?>">
    <?php if (!STORE_IS_ADMIN_AREA): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/store.css?v=<?php echo urlencode($assetVersion('public/css/store.css')); ?>">
        <script>document.documentElement.classList.add('js');</script>
    <?php endif; ?>
</head>
<?php if (STORE_IS_ADMIN_AREA): ?>
<body class="is-admin">
<div class="site-shell">
<?php else: ?>
<body class="is-store">
<a class="sf-skip-link" href="#contenido">Saltar al contenido</a>
<?php endif; ?>
