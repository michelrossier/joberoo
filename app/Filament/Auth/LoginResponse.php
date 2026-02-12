<?php

namespace App\Filament\Auth;

use App\Filament\Resources\CampaignResource;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $panel = Filament::getCurrentPanel();
        $user = Filament::auth()->user();

        if (! $panel || ! $user) {
            return redirect()->to(Filament::getUrl() ?? '/');
        }

        $tenant = $panel->hasTenancy()
            ? (Filament::getTenant() ?? Filament::getUserDefaultTenant($user))
            : null;

        if ($panel->hasTenancy() && ! $tenant) {
            return redirect()->to(Filament::getUrl() ?? '/');
        }

        return redirect()->to(CampaignResource::getUrl(panel: $panel->getId(), tenant: $tenant));
    }
}
