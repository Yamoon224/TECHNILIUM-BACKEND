<?php

declare(strict_types=1);

namespace App\Modules\Shared\Dashboard;

/**
 * Builders for the generic dashboard payload. The back office renders any
 * section from this shape alone, so a module adds a block without front-end work.
 *
 * Amounts are integers in XOF (no minor units) and use the "money" format.
 */
final class Widgets
{
    public const NUMBER = 'number';

    public const MONEY = 'money';

    public const PERCENT = 'percent';

    public const GOOD = 'good';

    public const WARN = 'warn';

    public const BAD = 'bad';

    /**
     * A headline figure.
     *
     * @param  'number'|'money'|'percent'  $format
     * @param  'good'|'warn'|'bad'|null  $tone  colour hint: good (green), warn (amber), bad (red)
     * @return array{key: string, label: string, label_en: string, value: int|float, format: string, tone: string|null}
     */
    public static function kpi(string $key, string $label, string $labelEn, int|float $value, string $format = self::NUMBER, ?string $tone = null): array
    {
        return ['key' => $key, 'label' => $label, 'label_en' => $labelEn, 'value' => $value, 'format' => $format, 'tone' => $tone];
    }

    /**
     * A small chart: "bar" or "line" over ordered points, or "donut" for shares.
     *
     * @param  'bar'|'line'|'donut'  $type
     * @param  list<array{label: string, label_en?: string, value: int|float}>  $points
     * @return array{key: string, title: string, title_en: string, type: string, format: string, points: list<array{label: string, label_en: string, value: int|float}>}
     */
    public static function chart(string $key, string $title, string $titleEn, string $type, array $points, string $format = self::NUMBER): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'title_en' => $titleEn,
            'type' => $type,
            'format' => $format,
            'points' => array_map(fn (array $point) => [
                'label' => (string) $point['label'],
                'label_en' => (string) ($point['label_en'] ?? $point['label']),
                'value' => $point['value'],
            ], array_values($points)),
        ];
    }

    /**
     * A short table (a "top 5", the latest items, what needs attention).
     *
     * @param  list<array{0: string, 1: string}>  $columns  [French, English] header of each column
     * @param  list<list<string|int|float|null>>  $rows
     * @return array{key: string, title: string, title_en: string, columns: list<array{label: string, label_en: string}>, rows: list<list<string|int|float|null>>}
     */
    public static function table(string $key, string $title, string $titleEn, array $columns, array $rows): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'title_en' => $titleEn,
            'columns' => array_map(fn (array $column) => ['label' => $column[0], 'label_en' => $column[1] ?? $column[0]], $columns),
            'rows' => array_values($rows),
        ];
    }

    /**
     * One dashboard block.
     *
     * @param  string  $link  back-office page of the feature, e.g. "/commandes"
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $charts
     * @param  list<array<string, mixed>>  $tables
     * @return array<string, mixed>
     */
    public static function section(string $title, string $titleEn, string $link, array $kpis = [], array $charts = [], array $tables = []): array
    {
        return ['title' => $title, 'title_en' => $titleEn, 'link' => $link, 'kpis' => $kpis, 'charts' => $charts, 'tables' => $tables];
    }
}
