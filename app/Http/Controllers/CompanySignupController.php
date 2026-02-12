<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanySignupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'company_name.required' => 'Bitte geben Sie den Firmennamen ein.',
            'name.required' => 'Bitte geben Sie Ihren vollstaendigen Namen ein.',
            'email.required' => 'Bitte geben Sie Ihre geschaeftliche E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.',
            'email.unique' => 'Es existiert bereits ein Konto mit dieser E-Mail-Adresse.',
            'password.required' => 'Bitte vergeben Sie ein Passwort.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
            'password.confirmed' => 'Die Passwort-Bestaetigung stimmt nicht ueberein.',
        ]);

        $organization = null;
        $user = null;

        DB::transaction(function () use ($validated, &$organization, &$user): void {
            $organization = Organization::create([
                'name' => $validated['company_name'],
                'slug' => $this->generateUniqueOrganizationSlug($validated['company_name']),
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $organization->users()->attach($user->id, ['role' => 'admin']);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('filament.admin.home', [
            'tenant' => $organization->slug,
        ]);
    }

    private function generateUniqueOrganizationSlug(string $companyName): string
    {
        $baseSlug = Str::slug($companyName);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'firma';
        $slug = $baseSlug;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
