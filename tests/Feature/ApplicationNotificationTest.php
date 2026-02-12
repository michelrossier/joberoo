<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Notifications\NewApplicationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_receive_notification_on_new_application(): void
    {
        Storage::fake('local');
        Notification::fake();

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Published,
        ]);

        $admin = User::factory()->create();
        $campaign->organization->users()->attach($admin->id, ['role' => 'admin']);

        $this->post(
            route('campaign.apply', [
                'org_slug' => $campaign->organization->slug,
                'campaign_slug' => $campaign->slug,
            ]),
            [
                'first_name' => 'Sam',
                'last_name' => 'Lee',
                'email' => 'sam@example.com',
                'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'cover_letter' => UploadedFile::fake()->create('cover.pdf', 120, 'application/pdf'),
            ]
        );

        Notification::assertSentTo(
            $admin,
            NewApplicationNotification::class
        );
    }
}
