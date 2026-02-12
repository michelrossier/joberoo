<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_rejects_invalid_file_types(): void
    {
        Storage::fake('local');

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
        ]);

        $response = $this->post(
            route('campaign.apply', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
            ]),
            [
                'first_name' => 'Taylor',
                'last_name' => 'Smith',
                'email' => 'taylor@example.com',
                'resume' => UploadedFile::fake()->create('resume.exe', 10, 'application/octet-stream'),
                'cover_letter' => UploadedFile::fake()->create('cover.txt', 10, 'text/plain'),
            ]
        );

        $response->assertSessionHasErrors(['resume', 'cover_letter']);
    }
}
