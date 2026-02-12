<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $role = $data['role'] ?? 'recruiter';
        unset($data['role']);

        $user = User::create($data);

        if ($tenant) {
            $user->organizations()->attach($tenant->id, ['role' => $role]);
        }

        return $user;
    }
}
