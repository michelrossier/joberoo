<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_displays_new_lane_with_newest_first(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $olderApplication = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'first_name' => 'Older',
            'last_name' => 'Candidate',
            'created_at' => now()->subDay(),
        ]);

        $newerApplication = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'first_name' => 'Newer',
            'last_name' => 'Candidate',
            'created_at' => now(),
        ]);

        Livewire::test(ListApplications::class)
            ->assertSee('Neu')
            ->assertSee('Abgelehnt')
            ->assertSee($campaign->title)
            ->assertSeeInOrder([
                $newerApplication->full_name,
                $olderApplication->full_name,
            ]);
    }

    public function test_dragging_application_to_new_lane_updates_status(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
        ]);

        Livewire::test(ListApplications::class)
            ->call('moveApplication', $application->id, ApplicationStatus::Interview->value);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Interview->value,
        ]);
    }

    private function authenticateRecruiterForTenant(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => 'recruiter']);

        $this->actingAs($user);
        Filament::setTenant($organization, true);

        return [$organization, $user];
    }
}
