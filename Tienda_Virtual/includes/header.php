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

$pageTitle = $pageTitle ?? (string) appConfigGet('app.name', "Gerson's Store");
$stylesPath = __DIR__ . '/../public/css/styles.css';
$stylesVersion = is_file($stylesPath) ? (string) filemtime($stylesPath) : '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (STORE_LOGO_URL): ?>
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>">
        <link rel="shortcut icon" href="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>">
        <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(STORE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/styles.css?v=<?php echo urlencode($stylesVersion); ?>">
</head>
<body>
<div class="site-shell">
