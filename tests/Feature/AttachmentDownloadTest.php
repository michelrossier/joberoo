<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attachment;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_filament_login_when_accessing_protected_attachment(): void
    {
        $attachment = Attachment::factory()->create();

        $this->get(route('attachments.download', $attachment))
            ->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_download_attachment_without_membership(): void
    {
        Storage::fake('local');

        $organization = Organization::factory()->create();
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id]);
        $application = Application::factory()->create(['campaign_id' => $campaign->id]);

        $attachment = Attachment::factory()->create([
            'application_id' => $application->id,
            'type' => 'resume',
            'original_name' => 'resume.pdf',
            'storage_path' => "applications/{$application->id}/resume.pdf",
        ]);

        Storage::disk('local')->put($attachment->storage_path, 'resume');

        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)
            ->get(route('attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('resume.pdf');
    }

    public function test_attachment_download_requires_membership(): void
    {
        Storage::fake('local');

        $organization = Organization::factory()->create();
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id]);
        $application = Application::factory()->create(['campaign_id' => $campaign->id]);

        $attachment = Attachment::factory()->create([
            'application_id' => $application->id,
            'type' => 'resume',
            'original_name' => 'resume.pdf',
            'storage_path' => "applications/{$application->id}/resume.pdf",
        ]);

        Storage::disk('local')->put($attachment->storage_path, 'resume');

        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'recruiter']);

        $this->actingAs($member)
            ->get(route('attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('resume.pdf');

        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrg->users()->attach($otherUser->id, ['role' => 'recruiter']);

        $this->actingAs($otherUser)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }
}
