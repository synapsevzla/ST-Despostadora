<?php
declare(strict_types=1);

/**
 * Soluciones Totales Despostadora
 * Fallback Front Controller para Apache / XAMPP / Hosting Compartido
 */
$publicIndex = __DIR__ . '/public/index.php';
if (is_file($publicIndex)) {
    require_once $publicIndex;
} else {
    http_response_code(500);
    echo 'Error: public/index.php no encontrado.';
}
