<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class FunnelAnalyticsCampaignPerformanceChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Job-Performance';

    protected static ?string $description = 'Konversionen und Einstellungen je Job.';

    protected int | string | array $columnSpan = 'full';

    /**
     * @var array<int, array{title: string, views: int, submissions: int, hires: int, conversion: float, hire_rate: float}>
     */
    #[Reactive]
    public array $campaignPerformance = [];

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
            static fn (array $row): string => (string) ($row['title'] ?? '-'),
            $this->campaignPerformance
        );
        $submissions = array_map(
            static fn (array $row): int => (int) ($row['submissions'] ?? 0),
            $this->campaignPerformance
        );
        $conversion = array_map(
            static fn (array $row): float => (float) ($row['conversion'] ?? 0),
            $this->campaignPerformance
        );
        $hireRate = array_map(
            static fn (array $row): float => (float) ($row['hire_rate'] ?? 0),
            $this->campaignPerformance
        );

        return [
            'datasets' => [
                [
                    'label' => 'Bewerbungen',
                    'data' => $submissions,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                    'yAxisID' => 'y',
                ],
                [
                    'type' => 'line',
                    'label' => 'Aufruf -> Bewerbung %',
                    'data' => $conversion,
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b981',
                    'yAxisID' => 'y1',
                    'tension' => 0.2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Bewerbung -> Einstellung %',
                    'data' => $hireRate,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#f59e0b',
                    'yAxisID' => 'y1',
                    'tension' => 0.2,
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

