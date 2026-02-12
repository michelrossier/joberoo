<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'status' => ApplicationStatus::New,
            'assigned_user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'linkedin_url' => 'https://linkedin.com/in/' . fake()->userName(),
            'portfolio_url' => 'https://example.com/' . fake()->userName(),
            'cover_letter_text' => fake()->paragraph(),
            'source' => 'direct',
            'source_medium' => null,
            'source_campaign' => null,
            'referrer_url' => null,
        ];
    }
}
