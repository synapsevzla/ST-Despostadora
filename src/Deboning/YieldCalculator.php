<?php
declare(strict_types=1);

namespace Src\Deboning;

final class YieldCalculator
{
    /**
     * Calcula rendimientos, merma física y distribución de merma según CostAct de SAPROD.
     *
     * @param float $pesoCanal Peso de entrada de la canal
     * @param float $costActCanal Costo unitario actual de la canal en SAPROD
     * @param list<array<string, mixed>> $cuts Lista de cortes pesados
     * @return array<string, mixed>
     */
    public static function compute(float $pesoCanal, float $costActCanal, array $cuts): array
    {
        $costoTotalCanal = round($pesoCanal * $costActCanal, 4);

        $pesoTotalCortes = 0.0;
        foreach ($cuts as $c) {
            $pesoTotalCortes += (float)$c['PesoNeto'];
        }

        $pesoMerma = round($pesoCanal - $pesoTotalCortes, 4);
        $rendimientoPct = $pesoCanal > 0 ? round(($pesoTotalCortes / $pesoCanal) * 100, 2) : 0.0;
        $mermaPct = $pesoCanal > 0 ? round(($pesoMerma / $pesoCanal) * 100, 2) : 0.0;

        // Pérdida monetaria por merma valorizada al costo de la canal
        $perdidaMermaDinero = ($pesoMerma > 0) ? round($pesoMerma * $costActCanal, 4) : 0.0;

        // Valor base de cortes
        $valorBaseTotalCortes = 0.0;
        $itemsPrelim = [];

        foreach ($cuts as $c) {
            $pNeto = (float)$c['PesoNeto'];
            $costBase = (float)$c['CostActBase'];
            $costReferencia = ($costBase > 0) ? $costBase : $costActCanal;
            $valorBaseCorte = round($pNeto * $costReferencia, 4);
            $valorBaseTotalCortes += $valorBaseCorte;

            $itemsPrelim[] = array_merge($c, [
                'PesoNeto' => $pNeto,
                'CostActBase' => $costBase,
                'CostReferencia' => $costReferencia,
                'ValorBaseCorte' => $valorBaseCorte,
                'RendimientoPct' => $pesoCanal > 0 ? round(($pNeto / $pesoCanal) * 100, 2) : 0.0
            ]);
        }

        // Distribuir merma
        $tablaCosteo = [];
        $totalCostoRealAcumulado = 0.0;

        foreach ($itemsPrelim as $item) {
            $pNeto = (float)$item['PesoNeto'];
            $valorBase = (float)$item['ValorBaseCorte'];

            $factor = 0.0;
            if ($valorBaseTotalCortes > 0) {
                $factor = $valorBase / $valorBaseTotalCortes;
            } elseif ($pesoTotalCortes > 0) {
                $factor = $pNeto / $pesoTotalCortes;
            }

            $mermaAsignada = ($perdidaMermaDinero > 0) ? round($perdidaMermaDinero * $factor, 4) : 0.0;
            $mermaAsignadaPorKg = ($pNeto > 0) ? round($mermaAsignada / $pNeto, 4) : 0.0;

            $costoRealTotal = round($valorBase + $mermaAsignada, 4);
            $costoRealUnitario = ($pNeto > 0) ? round($costoRealTotal / $pNeto, 4) : 0.0;
            $totalCostoRealAcumulado += $costoRealTotal;

            $margen = (float)($item['MargenUtilidadPct'] ?? 30.0);
            $precioSugerido = ($margen < 100 && $costoRealUnitario > 0)
                ? round($costoRealUnitario / (1 - ($margen / 100)), 4)
                : $costoRealUnitario;

            $tablaCosteo[] = array_merge($item, [
                'FactorPonderacion' => round($factor * 100, 2),
                'MermaAsignada' => $mermaAsignada,
                'CostoMermaKg' => $mermaAsignadaPorKg,
                'CostoRealUnitario' => $costoRealUnitario,
                'CostoRealTotal' => $costoRealTotal,
                'PrecioVentaSugerido' => $precioSugerido
            ]);
        }

        return [
            'PesoTotalCortes' => $pesoTotalCortes,
            'PesoMerma' => $pesoMerma,
            'PerdidaMermaDinero' => $perdidaMermaDinero,
            'RendimientoPct' => $rendimientoPct,
            'MermaPct' => $mermaPct,
            'DescuadreFisico' => ($pesoTotalCortes > $pesoCanal),
            'Cortes' => $tablaCosteo,
            'TotalCostoRealCortes' => round($totalCostoRealAcumulado, 4)
        ];
    }
}
