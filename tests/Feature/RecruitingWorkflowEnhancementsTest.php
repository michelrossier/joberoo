<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\CampaignStatus;
use App\Filament\Pages\FunnelAnalytics;
use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\CampaignVisit;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RecruitingWorkflowEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_tracking_and_submission_activity_are_recorded(): void
    {
        Storage::fake('local');
        Notification::fake();

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
        ]);

        $this->withHeaders(['referer' => 'https://linkedin.com/jobs'])
            ->get(route('campaign.show', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
                'utm_source' => 'LinkedIn',
                'utm_medium' => 'CPC',
                'utm_campaign' => 'spring-hiring',
            ]))
            ->assertOk();

        $this->assertDatabaseHas('campaign_visits', [
            'campaign_id' => $campaign->id,
            'source' => 'linkedin',
            'source_medium' => 'cpc',
            'source_campaign' => 'spring-hiring',
        ]);

        $this->post(route('campaign.apply', [
            'org_slug' => $campaign->organization->slug,
            'campaign_slug' => $campaign->slug,
        ]), [
            'first_name' => 'Alex',
            'last_name' => 'Meyer',
            'email' => 'alex@example.com',
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            'cover_letter' => UploadedFile::fake()->create('cover.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $application = Application::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'source' => 'linkedin',
            'source_medium' => 'cpc',
            'source_campaign' => 'spring-hiring',
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'type' => 'submitted',
        ]);
    }

    public function test_kanban_status_change_creates_activity_entry(): void
    {
        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
        ]);

        Livewire::test(ListApplications::class)
            ->call('moveApplication', $application->id, ApplicationStatus::Reviewed->value);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Reviewed->value,
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'status_changed',
        ]);
    }

    public function test_edit_assignment_creates_activity_entry(): void
    {
        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $assignee = User::factory()->create();
        $assignee->organizations()->attach($organization, ['role' => 'recruiter']);

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'assigned_user_id' => null,
        ]);

        Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
            ->set('data.assigned_user_id', $assignee->id)
            ->call('save');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'assigned_user_id' => $assignee->id,
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'assignment_changed',
        ]);
    }

    public function test_funnel_analytics_page_aggregates_by_source(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        CampaignVisit::query()->create([
            'campaign_id' => $campaign->id,
            'source' => 'linkedin',
            'source_medium' => 'cpc',
            'source_campaign' => 'spring-hiring',
            'session_id' => 'session-a',
        ]);
        CampaignVisit::query()->create([
            'campaign_id' => $campaign->id,
            'source' => 'linkedin',
            'source_medium' => 'cpc',
            'source_campaign' => 'spring-hiring',
            'session_id' => 'session-b',
        ]);
        CampaignVisit::query()->create([
            'campaign_id' => $campaign->id,
            'source' => 'direct',
            'session_id' => 'session-c',
        ]);

        Application::factory()->create([
            'campaign_id' => $campaign->id,
            'source' => 'linkedin',
        ]);
        Application::factory()->create([
            'campaign_id' => $campaign->id,
            'source' => 'direct',
        ]);

        $component = Livewire::test(FunnelAnalytics::class)
            ->set('days', 365);

        $rows = collect($component->instance()->rows);

        $this->assertSame(2, $rows->firstWhere('source', 'linkedin')['views']);
        $this->assertSame(1, $rows->firstWhere('source', 'linkedin')['submissions']);
        $this->assertSame(1, $rows->firstWhere('source', 'direct')['views']);
        $this->assertSame(1, $rows->firstWhere('source', 'direct')['submissions']);
    }

    public function test_advanced_dashboard_kpis_and_funnel_are_computed(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $reviewed = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Reviewed,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(2),
        ]);
        $reviewed->activities()->create([
            'type' => 'status_changed',
            'meta' => ['to' => ApplicationStatus::Reviewed->label(), 'to_value' => ApplicationStatus::Reviewed->value],
            'created_at' => $reviewed->created_at->copy()->addHours(5),
            'updated_at' => $reviewed->created_at->copy()->addHours(5),
        ]);

        $interview = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);
        $interview->activities()->createMany([
            [
                'type' => 'status_changed',
                'meta' => ['to' => ApplicationStatus::Reviewed->label(), 'to_value' => ApplicationStatus::Reviewed->value],
                'created_at' => $interview->created_at->copy()->addHours(3),
                'updated_at' => $interview->created_at->copy()->addHours(3),
            ],
            [
                'type' => 'status_changed',
                'meta' => ['to' => ApplicationStatus::Interview->label(), 'to_value' => ApplicationStatus::Interview->value],
                'created_at' => $interview->created_at->copy()->addHours(26),
                'updated_at' => $interview->created_at->copy()->addHours(26),
            ],
        ]);

        $accepted = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Accepted,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
        $accepted->activities()->createMany([
            [
                'type' => 'status_changed',
                'meta' => ['to' => ApplicationStatus::Reviewed->label(), 'to_value' => ApplicationStatus::Reviewed->value],
                'created_at' => $accepted->created_at->copy()->addHours(2),
                'updated_at' => $accepted->created_at->copy()->addHours(2),
            ],
            [
                'type' => 'status_changed',
                'meta' => ['to' => ApplicationStatus::Interview->label(), 'to_value' => ApplicationStatus::Interview->value],
                'created_at' => $accepted->created_at->copy()->addHours(10),
                'updated_at' => $accepted->created_at->copy()->addHours(10),
            ],
            [
                'type' => 'status_changed',
                'meta' => ['to' => ApplicationStatus::Accepted->label(), 'to_value' => ApplicationStatus::Accepted->value],
                'created_at' => $accepted->created_at->copy()->addHours(30),
                'updated_at' => $accepted->created_at->copy()->addHours(30),
            ],
        ]);

        $component = Livewire::test(FunnelAnalytics::class)
            ->set('days', 365);

        $kpis = $component->instance()->kpis;
        $funnel = $component->instance()->stageFunnel;

        $this->assertSame(3, $kpis['applications']);
        $this->assertSame(1, $kpis['hired']);
        $this->assertSame(2, $kpis['active']);
        $this->assertSame(3.3, $kpis['avg_time_to_review_hours']);
        $this->assertSame(30.0, $kpis['avg_time_to_hire_hours']);

        $this->assertSame(3, $funnel[0]['count']);
        $this->assertSame(3, $funnel[1]['count']);
        $this->assertSame(2, $funnel[2]['count']);
        $this->assertSame(1, $funnel[3]['count']);
    }

    public function test_advanced_dashboard_recruiter_throughput_is_computed(): void
    {
        [$organization, $recruiterA] = $this->authenticateRecruiterForTenant();

        $recruiterB = User::factory()->create();
        $recruiterB->organizations()->attach($organization, ['role' => 'recruiter']);

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $appA1 = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'assigned_user_id' => $recruiterA->id,
            'status' => ApplicationStatus::Accepted,
        ]);
        $appA2 = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'assigned_user_id' => $recruiterA->id,
            'status' => ApplicationStatus::Reviewed,
        ]);
        $appB = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'assigned_user_id' => $recruiterB->id,
            'status' => ApplicationStatus::Interview,
        ]);

        $appA1->recordActivity('status_changed', null, ['to' => ApplicationStatus::Accepted->label(), 'to_value' => ApplicationStatus::Accepted->value], $recruiterA->id);
        $appA2->recordActivity('note_added', 'Candidate is strong', [], $recruiterA->id);
        $appB->recordActivity('status_changed', null, ['to' => ApplicationStatus::Interview->label(), 'to_value' => ApplicationStatus::Interview->value], $recruiterB->id);

        $component = Livewire::test(FunnelAnalytics::class)
            ->set('days', 365);

        $rows = collect($component->instance()->recruiterThroughput);

        $rowA = $rows->firstWhere('name', $recruiterA->name);
        $rowB = $rows->firstWhere('name', $recruiterB->name);

        $this->assertSame(2, $rowA['assigned']);
        $this->assertSame(1, $rowA['status_updates']);
        $this->assertSame(1, $rowA['notes']);
        $this->assertSame(1, $rowA['hires']);

        $this->assertSame(1, $rowB['assigned']);
        $this->assertSame(1, $rowB['status_updates']);
        $this->assertSame(0, $rowB['notes']);
        $this->assertSame(0, $rowB['hires']);
    }

    private function authenticateRecruiterForTenant(): array
    {
        $organization = Organization::factory()->create();
        $recruiter = User::factory()->create();
        $recruiter->organizations()->attach($organization, ['role' => 'recruiter']);

        $this->actingAs($recruiter);
        Filament::setTenant($organization, true);

        return [$organization, $recruiter];
    }
}
