<?php
$pageTitle = 'Registro';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<section class="page-section form-panel customer-register-panel">
    <h1 class="page-title">Crear cuenta</h1>
    <form class="form-grid" action="#" method="post">
        <label>
            Nombres completos
            <input type="text" name="nombre" placeholder="Nombre y apellido">
        </label>
        <label>
            Correo electronico
            <input type="email" name="email" placeholder="correo@ejemplo.com">
        </label>
        <label>
            Telefono
            <input type="text" name="telefono" placeholder="999999999">
        </label>
        <label>
            Contrasena
            <input type="password" name="password" placeholder="Crea una contrasena segura">
        </label>
        <button class="button" type="submit">Registrarme</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
