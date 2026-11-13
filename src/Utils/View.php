<?php
declare(strict_types=1);

namespace Src\Utils;

final class View
{
    /**
     * @param string $templatePath Nombre o ruta relativa de la vista
     * @param array<string, mixed> $data Datos inyectados a la vista
     */
    public static function render(string $templatePath, array $data = []): void
    {
        $baseDir = dirname(__DIR__, 2) . '/views/';
        $viewFile = $baseDir . ltrim($templatePath, '/') . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo "Vista no encontrada: " . htmlspecialchars($templatePath, ENT_QUOTES, 'UTF-8');
            return;
        }

        // Extraer variables sin usar extract() directo de datos inseguros
        foreach ($data as $key => $val) {
            if (is_string($key) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                $$key = $val;
            }
        }

        require $viewFile;
    }
}
