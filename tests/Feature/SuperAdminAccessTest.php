<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FunnelAnalytics;
use App\Filament\Resources\ApplicationResource;
use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\UserResource;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_user_methods_bypass_tenant_membership(): void
    {
        $alpha = Organization::factory()->create([
            'name' => 'Alpha Org',
            'slug' => 'alpha-org',
        ]);
        $beta = Organization::factory()->create([
            'name' => 'Beta Org',
            'slug' => 'beta-org',
        ]);
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);
        $panel = Mockery::mock(Panel::class);

        $tenantIds = $superAdmin->getTenants($panel)->pluck('id')->all();

        $this->assertSame([$alpha->id, $beta->id], $tenantIds);
        $this->assertTrue($superAdmin->canAccessTenant($alpha));
        $this->assertTrue($superAdmin->canAccessTenant($beta));
        $this->assertTrue($superAdmin->canAccessPanel($panel));
        $this->assertTrue($superAdmin->isAdminForOrganization($alpha));
        $this->assertTrue($superAdmin->isAnyAdmin());
    }

    public function test_non_super_admin_user_methods_remain_membership_based(): void
    {
        $alpha = Organization::factory()->create();
        $beta = Organization::factory()->create();
        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);
        $panel = Mockery::mock(Panel::class);

        $user->organizations()->attach($alpha->id, ['role' => 'recruiter']);

        $this->assertTrue($user->canAccessTenant($alpha));
        $this->assertFalse($user->canAccessTenant($beta));
        $this->assertTrue($user->canAccessPanel($panel));
        $this->assertFalse($user->isAdminForOrganization($alpha));
        $this->assertFalse($user->isAnyAdmin());
    }

    public function test_super_admin_can_access_tenant_scoped_pages_and_resources_without_membership(): void
    {
        $tenant = Organization::factory()->create();
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin);
        Filament::setTenant($tenant, true);

        $this->assertTrue(Dashboard::canAccess());
        $this->assertTrue(FunnelAnalytics::canAccess());
        $this->assertTrue(CampaignResource::canViewAny());
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(ApplicationResource::canViewAny());
    }

    public function test_non_super_admin_without_membership_cannot_access_tenant_scoped_pages_and_resources(): void
    {
        $tenant = Organization::factory()->create();
        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $this->actingAs($user);
        Filament::setTenant($tenant, true);

        $this->assertFalse(Dashboard::canAccess());
        $this->assertFalse(FunnelAnalytics::canAccess());
        $this->assertFalse(CampaignResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(ApplicationResource::canViewAny());
    }

    public function test_organization_resource_query_returns_all_organizations_for_super_admin(): void
    {
        $alpha = Organization::factory()->create();
        $beta = Organization::factory()->create();
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin);

        $organizationIds = OrganizationResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($alpha->id, $organizationIds);
        $this->assertContains($beta->id, $organizationIds);
    }

    public function test_organization_resource_query_returns_only_admin_organizations_for_regular_admin(): void
    {
        $adminOrg = Organization::factory()->create();
        $nonAdminOrg = Organization::factory()->create();
        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $user->organizations()->attach($adminOrg->id, ['role' => 'admin']);
        $user->organizations()->attach($nonAdminOrg->id, ['role' => 'recruiter']);

        $this->actingAs($user);

        $organizationIds = OrganizationResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($adminOrg->id, $organizationIds);
        $this->assertNotContains($nonAdminOrg->id, $organizationIds);
    }
}
