<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanySignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_displays_sales_content_and_signup_form(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Bauen Sie Ihren Bewerbungsfunnel auf und besetzen Sie Stellen schneller.');
        $response->assertSee('Firmenkonto erstellen');
        $response->assertSee('name="company_name"', false);
        $response->assertSee('name="password_confirmation"', false);
    }

    public function test_company_signup_creates_admin_user_and_organization(): void
    {
        $response = $this->post(route('company-signup.store'), [
            'company_name' => 'Acme Hiring Group',
            'name' => 'Alex Recruiter',
            'email' => 'alex@acme.test',
            'password' => 'super-secret-password',
            'password_confirmation' => 'super-secret-password',
        ]);

        $organization = Organization::query()->first();
        $user = User::query()->where('email', 'alex@acme.test')->first();

        $this->assertNotNull($organization);
        $this->assertNotNull($user);

        $response->assertRedirect(
            Dashboard::getUrl(panel: 'admin', tenant: $organization)
        );

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('super-secret-password', $user->password));

        $this->assertDatabaseHas('organizations', [
            'name' => 'Acme Hiring Group',
            'slug' => 'acme-hiring-group',
        ]);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_company_slug_is_uniqued_when_name_already_exists(): void
    {
        Organization::factory()->create([
            'name' => 'Talent Rocket',
            'slug' => 'talent-rocket',
        ]);

        $response = $this->post(route('company-signup.store'), [
            'company_name' => 'Talent Rocket',
            'name' => 'Jordan Teamlead',
            'email' => 'jordan@rocket.test',
            'password' => 'super-secret-password',
            'password_confirmation' => 'super-secret-password',
        ]);

        $organization = Organization::query()
            ->where('name', 'Talent Rocket')
            ->where('slug', 'talent-rocket-2')
            ->first();

        $this->assertNotNull($organization);
        $response->assertRedirect(
            Dashboard::getUrl(panel: 'admin', tenant: $organization)
        );
    }
}
