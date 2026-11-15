<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>
<div class="card" style="border-left: 4px solid #ef4444;">
    <h3 class="card-title" style="color: #ef4444; display: flex; align-items: center; gap: 8px;">
        <svg class="icon" viewBox="0 0 24 24" style="stroke: #ef4444; width: 24px; height: 24px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
        </svg>
        403 - Acceso Denegado
    </h3>
    <p class="card-desc">
        No posee privilegios suficientes para ingresar a este módulo. Esta área está restringida exclusivamente a usuarios administradores de nivel <strong>01</strong>.
    </p>
    <div style="margin-top: 1.5rem;">
        <a href="<?= Security::baseUrl('/') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 500;">
            <svg class="icon" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Volver al Inicio
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Acceso Denegado - ST Despostadora';
$pageTitle = 'Acceso Denegado';
$activeNav = '';

require __DIR__ . '/../layout.php';
