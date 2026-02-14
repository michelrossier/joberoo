<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Filament\Resources\CampaignResource;
use App\Models\Campaign;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class FirstJobCallout extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.first-job-callout';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        $hasActiveJob = Campaign::query()
            ->where('organization_id', $tenant->id)
            ->where('status', CampaignStatus::Published->value)
            ->exists();

        return ! $hasActiveJob;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'jobsUrl' => CampaignResource::getUrl('index', tenant: Filament::getTenant()),
        ];
    }
}
