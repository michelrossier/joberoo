<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class FunnelAnalyticsStageFunnelChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Funnel-Stufen';

    protected static ?string $description = 'Stufenzaehlung und Konversion innerhalb der Recruiting-Pipeline.';

    protected int | string | array $columnSpan = 'full';

    /**
     * @var array<int, array{label: string, count: int, conversion: float}>
     */
    #[Reactive]
    public array $stageFunnel = [];

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $labels = array_map(
            static fn (array $stage): string => (string) ($stage['label'] ?? ''),
            $this->stageFunnel
        );

        $counts = array_map(
            static fn (array $stage): int => (int) ($stage['count'] ?? 0),
            $this->stageFunnel
        );

        $conversions = array_map(
            static fn (array $stage): float => (float) ($stage['conversion'] ?? 0),
            $this->stageFunnel
        );

        return [
            'datasets' => [
                [
                    'label' => 'Anzahl',
                    'data' => $counts,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
                [
                    'type' => 'line',
                    'label' => 'Konversion %',
                    'data' => $conversions,
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b981',
                    'yAxisID' => 'y1',
                    'tension' => 0.25,
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
                'y1' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'max' => 100,
                ],
            ],
        ];
    }
}

