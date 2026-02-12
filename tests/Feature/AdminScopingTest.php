<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\Organization;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_resource_scopes_to_current_tenant(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $campaignA = Campaign::factory()->create(['organization_id' => $orgA->id]);
        $campaignB = Campaign::factory()->create(['organization_id' => $orgB->id]);

        $appA = Application::factory()->create(['campaign_id' => $campaignA->id]);
        $appB = Application::factory()->create(['campaign_id' => $campaignB->id]);

        Filament::setTenant($orgA, true);

        $ids = ApplicationResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($appA->id, $ids);
        $this->assertNotContains($appB->id, $ids);
    }
}
