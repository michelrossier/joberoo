<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $data['organization_id'] = $tenant->id;
        }

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        $publicJobUrl = CampaignResource::getPublicJobUrl($this->getRecord());

        if (! $publicJobUrl) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->success()
            ->title('Job erfolgreich erstellt')
            ->body("Oeffentliche Job-URL: {$publicJobUrl}")
            ->actions([
                Action::make('open_job_url')
                    ->label('Job-URL oeffnen')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url($publicJobUrl, shouldOpenInNewTab: true),
            ]);
    }
}
