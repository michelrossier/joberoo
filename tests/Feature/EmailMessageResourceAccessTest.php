<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailMessageResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_global_access_and_tenant_admin_is_scoped_to_current_tenant(): void
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

        $tenantAdmin = User::factory()->create([
            'is_super_admin' => false,
        ]);
        $tenantAdmin->organizations()->attach($organizationA, ['role' => 'admin']);

        $recruiter = User::factory()->create();
        $recruiter->organizations()->attach($organizationA, ['role' => 'recruiter']);

        $messageA = EmailMessage::query()->create([
            'organization_id' => $organizationA->id,
            'recipient_email' => 'a@example.com',
            'subject' => 'Alpha subject',
            'status' => EmailMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $messageB = EmailMessage::query()->create([
            'organization_id' => $organizationB->id,
            'recipient_email' => 'b@example.com',
            'subject' => 'Beta subject',
            'status' => EmailMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($superAdmin);
        Filament::setTenant($organizationA, true);

        $this->assertTrue(EmailMessageResource::canViewAny());
        $this->assertTrue(EmailMessageResource::canView($messageA));

        $superAdminOrgIds = EmailMessageResource::getEloquentQuery()
            ->pluck('organization_id')
            ->all();

        $this->assertContains($organizationA->id, $superAdminOrgIds);
        $this->assertContains($organizationB->id, $superAdminOrgIds);

        $this->actingAs($tenantAdmin);
        Filament::setTenant($organizationA, true);

        $this->assertTrue(EmailMessageResource::canViewAny());
        $this->assertTrue(EmailMessageResource::canView($messageA));
        $this->assertFalse(EmailMessageResource::canView($messageB));

        $tenantOrgIds = EmailMessageResource::getEloquentQuery()
            ->pluck('organization_id')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$organizationA->id], $tenantOrgIds);

        $this->actingAs($recruiter);
        Filament::setTenant($organizationA, true);

        $this->assertFalse(EmailMessageResource::canViewAny());
        $this->assertFalse(EmailMessageResource::canView($messageA));
    }
}
