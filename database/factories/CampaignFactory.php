<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'organization_id' => Organization::factory(),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numerify('###'),
            'views_count' => 0,
            'status' => CampaignStatus::Published,
            'title' => $title,
            'subtitle' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'location' => fake()->city(),
            'employment_type' => fake()->randomElement(['Full-time', 'Part-time', 'Contract']),
            'salary_range' => fake()->randomElement(['$70k-$90k', '$90k-$120k', '$120k-$150k']),
            'cta_text' => 'Apply now',
            'primary_color' => '#1f2937',
        ];
    }
}
