<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class PassedModuleChartData
{
    private const PASSING_SCORE = 50.0;

    private const EXTERNAL_PASS_CHART_VALUE = 100;

    private const BAR_COLOR = '#2b5c9e';

    /**
     * @return array{series: array<int, int>, labels: array<int, string>, tooltips: array<int, string>, colors: array<int, string>}
     */
    public function build(array $programData): array
    {
        $points = [];

        foreach (data_get($programData, 'tn_baust', []) as $position => $block) {
            if (! is_array($block) || ! $this->isCourse($block)) {
                continue;
            }

            $rawResult = $block['tn_punkte'] ?? null;
            $normalizedResult = is_string($rawResult)
                ? mb_strtolower(trim($rawResult))
                : null;
            $isExternalPass = in_array($normalizedResult, ['passed', 'bestanden'], true);
            $numericScore = is_numeric($rawResult) ? (float) $rawResult : null;

            if (! $isExternalPass && ($numericScore === null || $numericScore < self::PASSING_SCORE)) {
                continue;
            }

            $score = $isExternalPass
                ? self::EXTERNAL_PASS_CHART_VALUE
                : (int) round($numericScore);
            $endDate = $this->parseDate($block['ende_baustein'] ?? null);

            $points[] = [
                'label' => $this->label($block),
                'value' => $score,
                'tooltip' => $isExternalPass
                    ? 'extern bestanden – keine Punktzahl'
                    : $score.' Pkt.',
                '_sort' => $endDate?->timestamp ?? PHP_INT_MAX,
                '_position' => (int) $position,
            ];
        }

        usort($points, static function (array $left, array $right): int {
            return ($left['_sort'] <=> $right['_sort'])
                ?: ($left['_position'] <=> $right['_position']);
        });

        $series = array_column($points, 'value');

        return [
            'series' => $series,
            'labels' => array_column($points, 'label'),
            'tooltips' => array_column($points, 'tooltip'),
            'colors' => array_fill(0, count($series), self::BAR_COLOR),
        ];
    }

    private function isCourse(array $block): bool
    {
        $code = mb_strtoupper(trim((string) ($block['kurzbez'] ?? '')));

        return $code !== 'FERI'
            && $code !== 'PRAK'
            && ! str_starts_with($code, 'PRUE');
    }

    private function label(array $block): string
    {
        $label = trim((string) ($block['kurzbez'] ?? ''));

        return $label !== '' ? $label : 'Baustein';
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', $value), 'Europe/Berlin');
        } catch (\Throwable) {
            return null;
        }
    }
}
