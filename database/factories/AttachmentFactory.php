<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'type' => 'resume',
            'original_name' => 'resume.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_path' => 'applications/1/resume.pdf',
        ];
    }
}
