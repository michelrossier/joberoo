<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\FirstJobCallout;
use App\Filament\Widgets\JobViewsLastSevenDaysChart;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Bewerbungsmanagement';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->organizations()
            ->whereKey($tenant)
            ->wherePivotIn('role', ['admin', 'recruiter'])
            ->exists();
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            FirstJobCallout::class,
            DashboardStatsOverview::class,
            JobViewsLastSevenDaysChart::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 1;
    }
}
