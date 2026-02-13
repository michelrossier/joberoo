<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\AuditLogResource;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout_events_are_logged_with_timestamps(): void
    {
        $user = User::factory()->create([
            'name' => 'Mira Admin',
        ]);

        event(new Login('web', $user, false));
        event(new Logout('web', $user));

        $loginLog = AuditLog::query()
            ->where('event', AuditLog::EVENT_AUTH_LOGIN)
            ->first();
        $logoutLog = AuditLog::query()
            ->where('event', AuditLog::EVENT_AUTH_LOGOUT)
            ->first();

        $this->assertNotNull($loginLog);
        $this->assertNotNull($logoutLog);
        $this->assertSame($user->id, $loginLog?->actor_id);
        $this->assertSame($user->id, $logoutLog?->actor_id);
        $this->assertNotNull($loginLog?->created_at);
        $this->assertNotNull($logoutLog?->created_at);
    }

    public function test_campaign_lifecycle_actions_are_logged_for_authenticated_user(): void
    {
        [$organization, $actor] = $this->authenticateAdminForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'title' => 'Backend Engineer',
        ]);

        $campaign->update([
            'title' => 'Senior Backend Engineer',
        ]);

        $campaign->delete();

        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_CAMPAIGN_CREATED,
            'actor_id' => $actor->id,
            'organization_id' => $organization->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_CAMPAIGN_UPDATED,
            'actor_id' => $actor->id,
            'organization_id' => $organization->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_CAMPAIGN_DELETED,
            'actor_id' => $actor->id,
            'organization_id' => $organization->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
        ]);
    }

    public function test_application_update_logs_before_after_changes_with_readable_values(): void
    {
        [$organization, $actor] = $this->authenticateAdminForTenant();

        $assignee = User::factory()->create([
            'name' => 'Robin Recruiter',
        ]);
        $assignee->organizations()->attach($organization, ['role' => 'recruiter']);

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'assigned_user_id' => null,
            'first_name' => 'Anna',
            'last_name' => 'Meyer',
        ]);

        $application->update([
            'status' => ApplicationStatus::Reviewed,
            'assigned_user_id' => $assignee->id,
        ]);

        $log = AuditLog::query()
            ->where('event', AuditLog::EVENT_APPLICATION_UPDATED)
            ->where('subject_type', Application::class)
            ->where('subject_id', $application->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame($organization->id, $log->organization_id);
        $this->assertSame('Neu', $log->changes['status']['before']);
        $this->assertSame('In Bearbeitung', $log->changes['status']['after']);
        $this->assertSame('Nicht zugewiesen', $log->changes['assigned_user_id']['before']);
        $this->assertSame('Robin Recruiter', $log->changes['assigned_user_id']['after']);
    }

    public function test_model_events_are_not_logged_when_no_user_is_authenticated(): void
    {
        auth()->logout();

        $campaign = Campaign::factory()->create();
        $campaign->update([
            'title' => 'Unauthenticated Update',
        ]);
        $campaign->delete();

        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
        ]);
    }

    public function test_audit_log_resource_is_super_admin_only_and_global_across_tenants(): void
    {
        $organizationA = Organization::factory()->create([
            'name' => 'Alpha Org',
            'slug' => 'alpha-org',
        ]);
        $organizationB = Organization::factory()->create([
            'name' => 'Beta Org',
            'slug' => 'beta-org',
        ]);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);
        $regularAdmin = User::factory()->create([
            'is_super_admin' => false,
        ]);
        $regularAdmin->organizations()->attach($organizationA, ['role' => 'admin']);

        $logA = AuditLog::query()->create([
            'actor_id' => $superAdmin->id,
            'organization_id' => $organizationA->id,
            'event' => AuditLog::EVENT_CAMPAIGN_UPDATED,
            'subject_type' => Campaign::class,
            'subject_id' => 1001,
            'description' => 'Alpha update',
            'changes' => ['title' => ['before' => 'A', 'after' => 'B']],
            'context' => ['tenant_id' => $organizationA->id],
        ]);

        AuditLog::query()->create([
            'actor_id' => $superAdmin->id,
            'organization_id' => $organizationB->id,
            'event' => AuditLog::EVENT_CAMPAIGN_UPDATED,
            'subject_type' => Campaign::class,
            'subject_id' => 1002,
            'description' => 'Beta update',
            'changes' => ['title' => ['before' => 'X', 'after' => 'Y']],
            'context' => ['tenant_id' => $organizationB->id],
        ]);

        $this->actingAs($superAdmin);
        Filament::setTenant($organizationA, true);

        $this->assertTrue(AuditLogResource::canViewAny());
        $this->assertTrue(AuditLogResource::canView($logA));

        $organizationIds = AuditLogResource::getEloquentQuery()
            ->pluck('organization_id')
            ->all();

        $this->assertContains($organizationA->id, $organizationIds);
        $this->assertContains($organizationB->id, $organizationIds);

        $filteredCount = AuditLogResource::getEloquentQuery()
            ->where('organization_id', $organizationB->id)
            ->where('event', AuditLog::EVENT_CAMPAIGN_UPDATED)
            ->count();

        $this->assertSame(1, $filteredCount);

        $this->actingAs($regularAdmin);
        Filament::setTenant($organizationA, true);

        $this->assertFalse(AuditLogResource::canViewAny());
        $this->assertFalse(AuditLogResource::canView($logA));
    }

    private function authenticateAdminForTenant(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => 'admin']);

        $this->actingAs($user);
        Filament::setTenant($organization, true);

        return [$organization, $user];
    }
}
