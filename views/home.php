<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>
<div class="card">
    <h3 class="card-title">
        ¡Hola, <?= Security::escape($user['descrip'] ?? $user['codusua'] ?? 'Usuario') ?>! Bienvenido a Soluciones Totales Despostadora
    </h3>
    <p class="card-desc">
        Módulo operativo para el control de pesajes, rendimientos cárnicos y desglose de canales.
    </p>
</div>
<?php
$content = ob_get_clean();
$title = 'Inicio - ST Despostadora';
$pageTitle = 'Inicio';
$activeNav = 'home';

require __DIR__ . '/layout.php';
