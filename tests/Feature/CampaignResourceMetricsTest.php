<?php

namespace Tests\Feature;

use App\Filament\Resources\CampaignResource;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\Organization;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignResourceMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_resource_query_includes_application_counts(): void
    {
        $organization = Organization::factory()->create();
        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'views_count' => 10,
        ]);

        Application::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
        ]);

        Filament::setTenant($organization, true);

        $record = CampaignResource::getEloquentQuery()->firstWhere('id', $campaign->id);

        $this->assertNotNull($record);
        $this->assertSame(3, $record->applications_count);
    }
}
