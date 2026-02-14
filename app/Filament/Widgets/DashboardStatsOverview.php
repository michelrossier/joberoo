<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Models\Application;
use App\Models\Campaign;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [
                Stat::make('Aktive Jobs', 0)
                    ->description('Veroeffentlichte Jobs')
                    ->color('success')
                    ->icon('heroicon-o-megaphone'),
                Stat::make('Bewerbungen (24h)', 0)
                    ->description('Eingegangen in den letzten 24 Stunden')
                    ->color('primary')
                    ->icon('heroicon-o-inbox-arrow-down'),
                Stat::make('Bewerbungen (7 Tage)', 0)
                    ->description('Eingegangen in den letzten 7 Tagen')
                    ->color('info')
                    ->icon('heroicon-o-calendar-days'),
            ];
        }

        $campaignBaseQuery = Campaign::query()
            ->where('organization_id', $tenant->id);

        $activeJobs = (clone $campaignBaseQuery)
            ->where('status', CampaignStatus::Published->value)
            ->count();

        $campaignIds = (clone $campaignBaseQuery)->pluck('id');

        if ($campaignIds->isEmpty()) {
            return [
                Stat::make('Aktive Jobs', $activeJobs)
                    ->description('Veroeffentlichte Jobs')
                    ->color('success')
                    ->icon('heroicon-o-megaphone'),
                Stat::make('Bewerbungen (24h)', 0)
                    ->description('Eingegangen in den letzten 24 Stunden')
                    ->color('primary')
                    ->icon('heroicon-o-inbox-arrow-down'),
                Stat::make('Bewerbungen (7 Tage)', 0)
                    ->description('Eingegangen in den letzten 7 Tagen')
                    ->color('info')
                    ->icon('heroicon-o-calendar-days'),
            ];
        }

        $applicationsLast24Hours = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $applicationsLast7Days = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Aktive Jobs', $activeJobs)
                ->description('Veroeffentlichte Jobs')
                ->color('success')
                ->icon('heroicon-o-megaphone'),
            Stat::make('Bewerbungen (24h)', $applicationsLast24Hours)
                ->description('Eingegangen in den letzten 24 Stunden')
                ->color('primary')
                ->icon('heroicon-o-inbox-arrow-down'),
            Stat::make('Bewerbungen (7 Tage)', $applicationsLast7Days)
                ->description('Eingegangen in den letzten 7 Tagen')
                ->color('info')
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
