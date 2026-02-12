<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $org = Organization::create([
            'name' => 'Acme Recruiting',
            'slug' => 'acme',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $admin->organizations()->attach($org->id, ['role' => 'admin']);

        Campaign::create([
            'organization_id' => $org->id,
            'slug' => 'senior-product-designer',
            'status' => CampaignStatus::Published,
            'title' => 'Senior Produktdesigner',
            'subtitle' => 'Gestalten Sie die naechste Generation unserer Recruiting-Plattform.',
            'description' => 'Wir suchen eine erfahrene Produktdesignerin oder einen erfahrenen Produktdesigner, um das Bewerbungserlebnis zu fuehren. Sie arbeiten eng mit Recruiting und Engineering zusammen, um herausragende Workflows zu bauen.',
            'location' => 'Remote (EU)',
            'employment_type' => 'Vollzeit',
            'salary_range' => '$120k-$150k',
            'cta_text' => 'Jetzt bewerben',
            'primary_color' => '#0f172a',
        ]);
    }
}
