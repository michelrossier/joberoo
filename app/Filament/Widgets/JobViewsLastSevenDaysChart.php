<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use App\Models\CampaignVisit;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class JobViewsLastSevenDaysChart extends ChartWidget
{
    protected static ?string $heading = 'Job-Aufrufe je Job (letzte 7 Tage)';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '360px';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $dateRange = collect(range(6, 0))
            ->map(fn (int $daysAgo): Carbon => now()->startOfDay()->subDays($daysAgo));
        $labels = $dateRange->map(fn (Carbon $date): string => $date->format('d.m'))->all();
        $dateKeys = $dateRange->map(fn (Carbon $date): string => $date->format('Y-m-d'))->all();

        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [
                'labels' => $labels,
                'datasets' => [],
            ];
        }

        $campaigns = Campaign::query()
            ->where('organization_id', $tenant->id)
            ->orderBy('title')
            ->get(['id', 'title']);

        if ($campaigns->isEmpty()) {
            return [
                'labels' => $labels,
                'datasets' => [],
            ];
        }

        $startDate = $dateRange->first()?->copy()->startOfDay();
        $endDate = $dateRange->last()?->copy()->endOfDay();

        $visits = CampaignVisit::query()
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('campaign_id, DATE(created_at) AS visit_date, COUNT(*) AS views_count')
            ->groupBy('campaign_id')
            ->groupByRaw('DATE(created_at)')
            ->get();

        $visitsByCampaignDate = $visits
            ->groupBy('campaign_id')
            ->map(function (Collection $rows): array {
                return $rows
                    ->mapWithKeys(fn (CampaignVisit $row): array => [(string) $row->visit_date => (int) $row->views_count])
                    ->all();
            });

        $colors = [
            '#0ea5e9',
            '#10b981',
            '#f97316',
            '#f43f5e',
            '#a855f7',
            '#14b8a6',
            '#eab308',
            '#3b82f6',
            '#ef4444',
            '#22c55e',
        ];

        $datasets = $campaigns
            ->values()
            ->map(function (Campaign $campaign, int $index) use ($dateKeys, $visitsByCampaignDate, $colors): array {
                $color = $colors[$index % count($colors)];
                $viewsByDate = $visitsByCampaignDate->get($campaign->id, []);

                return [
                    'label' => $campaign->title,
                    'data' => array_map(
                        static fn (string $date): int => (int) ($viewsByDate[$date] ?? 0),
                        $dateKeys
                    ),
                    'borderColor' => $color,
                    'backgroundColor' => $color,
                    'pointBackgroundColor' => $color,
                    'pointBorderColor' => $color,
                    'fill' => false,
                    'tension' => 0.35,
                ];
            })
            ->all();

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                    ],
                ],
            ],
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
