<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class FunnelAnalyticsRecruiterThroughputChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Recruiter-Durchsatz';

    protected ?string $description = 'Aktivitaet und Einstellungen pro Recruiter.';

    protected int | string | array $columnSpan = 1;

    /**
     * @var array<int, array{name: string, assigned: int, status_updates: int, notes: int, hires: int, hire_rate: float, throughput_score: int}>
     */
    #[Reactive]
    public array $recruiterThroughput = [];

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
            static fn (array $row): string => (string) ($row['name'] ?? '-'),
            $this->recruiterThroughput
        );
        $throughputScores = array_map(
            static fn (array $row): int => (int) ($row['throughput_score'] ?? 0),
            $this->recruiterThroughput
        );
        $hires = array_map(
            static fn (array $row): int => (int) ($row['hires'] ?? 0),
            $this->recruiterThroughput
        );

        return [
            'datasets' => [
                [
                    'label' => 'Durchsatz-Score',
                    'data' => $throughputScores,
                    'backgroundColor' => '#8b5cf6',
                    'borderColor' => '#8b5cf6',
                ],
                [
                    'label' => 'Einstellungen',
                    'data' => $hires,
                    'backgroundColor' => '#f97316',
                    'borderColor' => '#f97316',
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
