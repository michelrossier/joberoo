<?php

namespace Tests\Feature;

use App\Filament\Exports\ApplicationsExcelExport;
use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Models\Application;
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

    public function test_list_applications_page_shows_excel_export_action(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::test(ListApplications::class)
            ->assertSee('Excel exportieren');
    }

    public function test_excel_export_uses_all_tenant_applications_and_only_non_file_applicant_fields(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $tenantCampaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $otherCampaign = Campaign::factory()->create();

        $tenantApplicationOne = Application::factory()->create([
            'campaign_id' => $tenantCampaign->id,
            'first_name' => 'Lara',
            'last_name' => 'Mueller',
        ]);
        $tenantApplicationTwo = Application::factory()->create([
            'campaign_id' => $tenantCampaign->id,
            'first_name' => 'Mika',
            'last_name' => 'Schmidt',
        ]);
        $otherTenantApplication = Application::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'first_name' => 'Other',
            'last_name' => 'Tenant',
        ]);

        $livewire = Livewire::test(ListApplications::class)->instance();
        $export = ApplicationsExcelExport::make('all_applications')->hydrate($livewire);

        $exportedIds = $export->getQuery()->pluck('id')->all();
        $columnNames = array_keys($export->getColumns());

        $this->assertEqualsCanonicalizing(
            [$tenantApplicationOne->id, $tenantApplicationTwo->id],
            $exportedIds
        );
        $this->assertNotContains($otherTenantApplication->id, $exportedIds);

        $this->assertSame([
            'first_name',
            'last_name',
            'email',
            'phone',
            'linkedin_url',
            'portfolio_url',
            'cover_letter_text',
        ], $columnNames);
        $this->assertNotContains('resume', $columnNames);
        $this->assertNotContains('cover_letter', $columnNames);
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
