<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class FunnelAnalyticsOverviewStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    /**
     * @var array<string, mixed>
     */
    #[Reactive]
    public array $totals = [];

    /**
     * @var array<string, mixed>
     */
    #[Reactive]
    public array $kpis = [];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $views = (int) ($this->totals['views'] ?? 0);
        $sources = (int) ($this->totals['sources'] ?? 0);
        $overallConversion = (float) ($this->totals['conversion'] ?? 0);
        $applications = (int) ($this->kpis['applications'] ?? 0);
        $active = (int) ($this->kpis['active'] ?? 0);
        $hired = (int) ($this->kpis['hired'] ?? 0);
        $avgReviewHours = (float) ($this->kpis['avg_time_to_review_hours'] ?? 0);
        $avgHireHours = (float) ($this->kpis['avg_time_to_hire_hours'] ?? 0);

        return [
            Stat::make('Quellen', number_format($sources))
                ->description('Aktive Traffic-Quellen')
                ->icon('heroicon-o-globe-alt')
                ->color('gray'),
            Stat::make('Aufrufe', number_format($views))
                ->description('Landingpage-Views im Zeitraum')
                ->icon('heroicon-o-eye')
                ->color('gray'),
            Stat::make('Bewerbungen', number_format($applications))
                ->description('Eingaenge im Zeitraum')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('primary'),
            Stat::make('Gesamtkonversion', number_format($overallConversion, 1) . '%')
                ->description('Aufruf zu Bewerbung')
                ->icon('heroicon-o-chart-bar')
                ->color('success'),
            Stat::make('Aktive Pipeline', number_format($active))
                ->description('Nicht angenommen oder abgelehnt')
                ->icon('heroicon-o-queue-list')
                ->color('warning'),
            Stat::make('Einstellungen', number_format($hired))
                ->description('Angenommene Kandidaten')
                ->icon('heroicon-o-check-badge')
                ->color('success'),
            Stat::make('Zeit bis Pruefung', number_format($avgReviewHours, 1) . 'h')
                ->description('Durchschnitt bis erste Pruefung')
                ->icon('heroicon-o-clock')
                ->color('info'),
            Stat::make('Zeit bis Einstellung', number_format($avgHireHours, 1) . 'h')
                ->description('Durchschnitt bis Zusage')
                ->icon('heroicon-o-clock')
                ->color('info'),
        ];
    }
}

