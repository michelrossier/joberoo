<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_update_persists(): void
    {
        $application = Application::factory()->create([
            'status' => ApplicationStatus::New,
        ]);

        $application->update(['status' => ApplicationStatus::Interview]);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Interview->value,
        ]);
    }
}
