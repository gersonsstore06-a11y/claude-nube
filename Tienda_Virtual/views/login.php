<?php
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$pageTitle = 'Acceso Admin';
$errorMessage = null;
$successMessage = null;
$loginData = [
    'email' => '',
];
$redirectTarget = adminAuthNormalizeRedirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');
$authAction = trim((string) ($_GET['action'] ?? ''));
$authNotice = trim((string) ($_GET['auth'] ?? ''));
$authController = new AuthController();

if ($authAction === 'logout') {
    $authController->logout();
    $successMessage = 'Sesion cerrada correctamente.';
}

if (adminAuthIsLoggedIn() && $authAction !== 'logout') {
    adminAuthRedirect($redirectTarget);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginData['email'] = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($authController->login([
        'email' => $loginData['email'],
        'password' => $password,
    ])) {
        adminAuthRedirect($redirectTarget);
    }

    $errorMessage = 'Correo o contrasena incorrectos para el acceso administrativo.';
} elseif ($authNotice === 'required') {
    $errorMessage = 'Inicia sesion como administrador para entrar al panel.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<section class="page-section form-panel auth-panel">
    <div class="auth-header">
        <span class="section-label">Acceso restringido</span>
        <h1 class="page-title">Iniciar sesion de administrador</h1>
        <p class="lead">Accede al panel para gestionar la tienda.</p>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="auth-message auth-message-error">
            <strong>No se pudo iniciar sesion</strong>
            <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($successMessage !== null): ?>
        <div class="auth-message auth-message-success">
            <strong>Sesion finalizada</strong>
            <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <div class="auth-content">
        <form class="form-grid auth-form" action="" method="post">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="auth-field-grid">
                <label>
                    Correo electronico
                    <input type="email" name="email" value="<?php echo htmlspecialchars($loginData['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="admin@tienda.com" required>
                </label>
                <label>
                    Contrasena
                    <input type="password" name="password" placeholder="Ingresa tu contrasena" required>
                </label>
            </div>

            <button class="button button-primary auth-submit" type="submit">Ingresar al panel</button>
        </form>

        <div class="auth-note-box">
            <strong>Clientes</strong>
            <p>Los clientes pueden ver catalogo y detalle sin iniciar sesion.</p>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
