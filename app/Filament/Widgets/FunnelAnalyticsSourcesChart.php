<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class FunnelAnalyticsSourcesChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Quellen-Performance';

    protected ?string $description = 'Aufrufe und Bewerbungen je Quelle.';

    protected int | string | array $columnSpan = 1;

    /**
     * @var array<int, array{source: string, views: int, submissions: int, conversion: float}>
     */
    #[Reactive]
    public array $rows = [];

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $labels = array_map(function (array $row): string {
            $source = (string) ($row['source'] ?? 'direct');

            return $source === 'direct' ? 'Direkt' : $source;
        }, $this->rows);

        $views = array_map(
            static fn (array $row): int => (int) ($row['views'] ?? 0),
            $this->rows
        );
        $submissions = array_map(
            static fn (array $row): int => (int) ($row['submissions'] ?? 0),
            $this->rows
        );

        return [
            'datasets' => [
                [
                    'label' => 'Aufrufe',
                    'data' => $views,
                    'backgroundColor' => '#0ea5e9',
                    'borderColor' => '#0ea5e9',
                ],
                [
                    'label' => 'Bewerbungen',
                    'data' => $submissions,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
