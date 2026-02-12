<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function afterCreate(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->record->users()->syncWithoutDetaching([
            $user->id => ['role' => 'admin'],
        ]);
    }
}
