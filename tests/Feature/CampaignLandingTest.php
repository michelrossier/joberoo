<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_campaign_is_visible(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
        ]);

        $response = $this->get("/o/{$campaign->organization->slug}/c/{$campaign->slug}");

        $response->assertOk();
        $response->assertSee($campaign->title);
    }

    public function test_published_campaign_visit_increments_views_count(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
            'views_count' => 0,
        ]);

        $this->get("/o/{$campaign->organization->slug}/c/{$campaign->slug}")
            ->assertOk();
        $this->get("/o/{$campaign->organization->slug}/c/{$campaign->slug}")
            ->assertOk();

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'views_count' => 2,
        ]);
    }

    public function test_draft_campaign_is_not_visible(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft,
            'views_count' => 0,
        ]);

        $response = $this->get("/o/{$campaign->organization->slug}/c/{$campaign->slug}");

        $response->assertNotFound();
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'views_count' => 0,
        ]);
    }

    public function test_draft_campaign_is_visible_for_logged_in_organization_member(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft,
            'views_count' => 0,
        ]);

        $user = User::factory()->create();
        $campaign->organization->users()->attach($user->id, ['role' => 'admin']);

        $response = $this->actingAs($user)
            ->get("/o/{$campaign->organization->slug}/c/{$campaign->slug}");

        $response->assertOk();
        $response->assertSee($campaign->title);
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'views_count' => 0,
        ]);
    }
}
