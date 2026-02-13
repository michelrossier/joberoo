<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_submission_creates_records_and_attachments(): void
    {
        Storage::fake('local');
        Notification::fake();

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
        ]);

        $response = $this->post(
            route('campaign.apply', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
            ]),
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'phone' => '555-111-2222',
                'linkedin_url' => 'https://linkedin.com/in/janedoe',
                'portfolio_url' => 'https://example.com/portfolio',
                'cover_letter_text' => 'Excited to apply!',
                'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'cover_letter' => UploadedFile::fake()->create('cover.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]
        );

        $response->assertRedirect(route('campaign.thanks', [
            'org_slug' => $campaign->organization->slug,
            'campaign_slug' => $campaign->slug,
        ]));

        $this->assertDatabaseHas('applications', [
            'email' => 'jane@example.com',
            'campaign_id' => $campaign->id,
        ]);

        $application = Application::first();

        $this->assertDatabaseCount('attachments', 2);
        Storage::disk('local')->assertExists("applications/{$application->id}/resume.pdf");
        Storage::disk('local')->assertExists("applications/{$application->id}/cover-letter.docx");
    }

    public function test_application_submission_without_files_is_allowed(): void
    {
        Storage::fake('local');
        Notification::fake();

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
        ]);

        $response = $this->post(
            route('campaign.apply', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
            ]),
            [
                'first_name' => 'Jesse',
                'last_name' => 'Miles',
                'email' => 'jesse@example.com',
                'cover_letter_text' => 'Freue mich auf das Gespraech.',
            ]
        );

        $response->assertRedirect(route('campaign.thanks', [
            'org_slug' => $campaign->organization->slug,
            'campaign_slug' => $campaign->slug,
        ]));

        $this->assertDatabaseHas('applications', [
            'email' => 'jesse@example.com',
            'campaign_id' => $campaign->id,
        ]);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_organization_member_can_submit_to_draft_campaign_preview(): void
    {
        Storage::fake('local');
        Notification::fake();

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft,
        ]);

        $user = User::factory()->create();
        $campaign->organization->users()->attach($user->id, ['role' => 'admin']);

        $response = $this->actingAs($user)->post(
            route('campaign.apply', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
            ]),
            [
                'first_name' => 'Robin',
                'last_name' => 'Weller',
                'email' => 'robin@example.com',
            ]
        );

        $response->assertRedirect(route('campaign.thanks', [
            'org_slug' => $campaign->organization->slug,
            'campaign_slug' => $campaign->slug,
        ]));

        $this->assertDatabaseHas('applications', [
            'campaign_id' => $campaign->id,
            'email' => 'robin@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('campaign.thanks', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
            ]))
            ->assertOk();
    }
}
