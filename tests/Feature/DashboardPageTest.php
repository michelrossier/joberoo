<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\FirstJobCallout;
use App\Filament\Widgets\JobViewsLastSevenDaysChart;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\CampaignVisit;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_registers_expected_widgets(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $component = Livewire::test(Dashboard::class);
        $widgets = $component->instance()->getWidgets();

        $this->assertSame([
            FirstJobCallout::class,
            DashboardStatsOverview::class,
            JobViewsLastSevenDaysChart::class,
        ], $widgets);
        $this->assertTrue(Dashboard::canAccess());
        $this->assertSame($organization->id, Filament::getTenant()?->id);
    }

    public function test_dashboard_stats_and_chart_use_tenant_scoped_data(): void
    {
        Carbon::setTestNow('2026-02-12 12:00:00');

        try {
            [$organization] = $this->authenticateRecruiterForTenant();

            $campaignAlpha = Campaign::factory()->create([
                'organization_id' => $organization->id,
                'title' => 'Alpha Job',
                'status' => CampaignStatus::Published,
            ]);
            $campaignBeta = Campaign::factory()->create([
                'organization_id' => $organization->id,
                'title' => 'Beta Job',
                'status' => CampaignStatus::Published,
            ]);

            Campaign::factory()->create([
                'status' => CampaignStatus::Published,
            ]);

            Application::factory()->create([
                'campaign_id' => $campaignAlpha->id,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ]);
            Application::factory()->create([
                'campaign_id' => $campaignAlpha->id,
                'created_at' => now()->subHours(20),
                'updated_at' => now()->subHours(20),
            ]);
            Application::factory()->create([
                'campaign_id' => $campaignBeta->id,
                'created_at' => now()->subHours(23),
                'updated_at' => now()->subHours(23),
            ]);
            Application::factory()->create([
                'campaign_id' => $campaignAlpha->id,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
            Application::factory()->create([
                'campaign_id' => $campaignBeta->id,
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(6),
            ]);
            Application::factory()->create([
                'campaign_id' => $campaignBeta->id,
                'created_at' => now()->subDays(8),
                'updated_at' => now()->subDays(8),
            ]);

            $this->createVisits($campaignAlpha, daysAgo: 6, count: 2);
            $this->createVisits($campaignAlpha, daysAgo: 4, count: 1);
            $this->createVisits($campaignAlpha, daysAgo: 0, count: 3);
            $this->createVisits($campaignBeta, daysAgo: 5, count: 4);
            $this->createVisits($campaignBeta, daysAgo: 1, count: 1);
            $this->createVisits($campaignBeta, daysAgo: 0, count: 2);

            $statsComponent = Livewire::test(DashboardStatsOverview::class)->instance();
            $stats = $this->invokeProtectedMethod($statsComponent, 'getStats');

            $this->assertSame(2, (int) $stats[0]->getValue());
            $this->assertSame(3, (int) $stats[1]->getValue());
            $this->assertSame(5, (int) $stats[2]->getValue());

            $chartComponent = Livewire::test(JobViewsLastSevenDaysChart::class)->instance();
            $data = $this->invokeProtectedMethod($chartComponent, 'getData');

            $this->assertSame(['06.02', '07.02', '08.02', '09.02', '10.02', '11.02', '12.02'], $data['labels']);
            $this->assertCount(2, $data['datasets']);
            $this->assertSame('Alpha Job', $data['datasets'][0]['label']);
            $this->assertSame([2, 0, 1, 0, 0, 0, 3], $data['datasets'][0]['data']);
            $this->assertSame('Beta Job', $data['datasets'][1]['label']);
            $this->assertSame([0, 4, 0, 0, 0, 1, 2], $data['datasets'][1]['data']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_shows_first_job_callout_until_a_job_is_active(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        Livewire::test(Dashboard::class)
            ->assertSee('Erstelle nun deine erste Jobanzeige')
            ->assertSee('Zu Jobs');

        Campaign::factory()->create([
            'organization_id' => $organization->id,
            'status' => CampaignStatus::Published,
        ]);

        Livewire::test(Dashboard::class)
            ->assertDontSee('Erstelle nun deine erste Jobanzeige');
    }

    private function createVisits(Campaign $campaign, int $daysAgo, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            CampaignVisit::query()->forceCreate([
                'campaign_id' => $campaign->id,
                'source' => 'direct',
                'session_id' => "{$campaign->id}-{$daysAgo}-{$index}-" . Str::random(8),
                'created_at' => now()->subDays($daysAgo)->setTime(9, 0)->addMinutes($index),
                'updated_at' => now()->subDays($daysAgo)->setTime(9, 0)->addMinutes($index),
            ]);
        }
    }

    private function invokeProtectedMethod(object $instance, string $method): mixed
    {
        $reflection = new ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($instance);
    }

    private function authenticateRecruiterForTenant(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => 'recruiter']);

        $this->actingAs($user);
        Filament::setTenant($organization, true);

        return [$organization, $user];
    }
}
