<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_applications_page_hides_excel_export_action_during_filament_upgrade(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::test(ListApplications::class)
            ->assertDontSee('Excel exportieren');
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
