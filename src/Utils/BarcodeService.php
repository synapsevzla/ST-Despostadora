<?php
declare(strict_types=1);

namespace Src\Utils;

/**
 * Generador nativo de códigos de barras Code 128 (Set B) en SVG vectorial puro.
 * Solución ultra-compacta, sin librerías externas ni dependencias.
 */
final class BarcodeService
{
    /**
     * Patrones de barras y espacios para Code 128 (valores 0 a 106).
     * Cada valor consta de 6 dígitos que alternan anchos de barras y espacios (suman 11 módulos),
     * excepto el carácter Stop (106) que tiene 7 dígitos (suma 13 módulos).
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213', // 00-09
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132', // 10-19
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211', // 20-29
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313', // 30-39
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331', // 40-49
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111', // 50-59
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214', // 60-69
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111', // 70-79
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141', // 80-89
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141', // 90-99
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112'                                 // 100-106
    ];

    private const START_B = 104;
    private const STOP = 106;

    /**
     * Genera un SVG en línea de código de barras Code 128B listo para imprimir.
     *
     * @param string $text Texto a codificar (ej. REC-0000000000000001, D-0000000000000001)
     * @param int $barHeight Altura de las barras en píxeles (default 36)
     * @param int $moduleWidth Ancho de 1 módulo de barra en décimas de mm o px (default 1)
     * @param bool $showText Si muestra el texto legible al pie de las barras
     * @return string SVG crudo seguro y auto-contenido
     */
    public static function generateCode128Svg(
        string $text,
        int $barHeight = 36,
        int $moduleWidth = 1,
        bool $showText = true
    ): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // 1. Convertir texto a valores numéricos de Code 128B
        $values = [self::START_B];
        $checksum = self::START_B;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $ascii = ord($text[$i]);
            // En Code 128B, los caracteres ASCII 32 a 126 se mapean a 0 .. 94
            if ($ascii < 32 || $ascii > 126) {
                $codeVal = 0; // espacio por defecto si carácter inválido
            } else {
                $codeVal = $ascii - 32;
            }
            $values[] = $codeVal;
            $checksum += $codeVal * ($i + 1);
        }

        $checkVal = $checksum % 103;
        $values[] = $checkVal;
        $values[] = self::STOP;

        // 2. Construir la secuencia de barras SVG
        $x = 10; // Margen izquierdo en módulos (quiet zone)
        $rects = '';

        foreach ($values as $val) {
            $pattern = self::PATTERNS[$val] ?? '';
            $patLen = strlen($pattern);
            for ($p = 0; $p < $patLen; $p++) {
                $width = (int)$pattern[$p] * $moduleWidth;
                $isBar = ($p % 2 === 0);
                if ($isBar && $width > 0) {
                    $rects .= sprintf('<rect x="%d" y="0" width="%d" height="%d" fill="#000000"/>', $x, $width, $barHeight);
                }
                $x += $width;
            }
        }

        $totalWidth = $x + 10; // Quiet zone derecha
        $totalHeight = $barHeight + ($showText ? 14 : 0);

        $textSvg = '';
        if ($showText) {
            $escapedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $textSvg = sprintf(
                '<text x="%d" y="%d" text-anchor="middle" font-family="monospace,Courier,monospace" font-size="10" font-weight="bold" fill="#000000">%s</text>',
                (int)($totalWidth / 2),
                $barHeight + 11,
                $escapedText
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" preserveAspectRatio="xMidYMid meet" class="barcode-svg" style="display:block; max-width:100%%; height:auto;">%s%s</svg>',
            $totalWidth,
            $totalHeight,
            $rects,
            $textSvg
        );
    }
}
