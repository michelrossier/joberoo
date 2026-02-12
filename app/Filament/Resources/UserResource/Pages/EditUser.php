<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $membership = $this->record->organizations()->whereKey($tenant)->first();
            $data['role'] = $membership?->pivot?->role;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $tenant = Filament::getTenant();
        $role = $data['role'] ?? null;
        unset($data['role']);

        $record->update($data);

        if ($tenant && $role) {
            $record->organizations()->syncWithoutDetaching([
                $tenant->id => ['role' => $role],
            ]);
        }

        return $record;
    }
}
