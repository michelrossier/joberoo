<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Pages\CandidateCompare;
use App\Models\Application;
use App\Models\ApplicationEvaluation;
use App\Models\Campaign;
use App\Models\CampaignScorecardCompetency;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CandidateComparePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_compare_page_builds_side_by_side_comparison_metrics(): void
    {
        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $secondReviewer = User::factory()->create();
        $secondReviewer->organizations()->attach($organization, ['role' => 'recruiter']);

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $competencyA = CampaignScorecardCompetency::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Fachkompetenz',
            'weight' => 3,
            'position' => 0,
        ]);
        $competencyB = CampaignScorecardCompetency::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Kommunikation',
            'weight' => 1,
            'position' => 1,
        ]);

        $candidateA = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
            'first_name' => 'Alice',
            'last_name' => 'Meyer',
            'source' => 'linkedin',
        ]);
        $candidateB = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
            'first_name' => 'Bob',
            'last_name' => 'Schmidt',
            'source' => 'direct',
        ]);

        ApplicationEvaluation::query()->create([
            'application_id' => $candidateA->id,
            'evaluator_id' => $recruiter->id,
            'scores' => [
                (string) $competencyA->id => 4,
                (string) $competencyB->id => 3,
            ],
            'rationale' => 'Gute Struktur und klare Kommunikation.',
        ]);
        ApplicationEvaluation::query()->create([
            'application_id' => $candidateA->id,
            'evaluator_id' => $secondReviewer->id,
            'scores' => [
                (string) $competencyA->id => 2,
                (string) $competencyB->id => 5,
            ],
            'rationale' => 'Technisch Luecken, aber starke Praesenz.',
        ]);
        ApplicationEvaluation::query()->create([
            'application_id' => $candidateB->id,
            'evaluator_id' => $recruiter->id,
            'scores' => [
                (string) $competencyA->id => 5,
                (string) $competencyB->id => 4,
            ],
            'rationale' => 'Sehr starkes Gesamtprofil.',
        ]);

        $candidateA->recordActivity('note_added', 'Stark in Architekturfragen.', [], $recruiter->id);
        $candidateA->recordActivity('status_changed', null, ['to' => ApplicationStatus::Interview->label()], $recruiter->id);
        $candidateB->recordActivity('note_added', 'Sehr praezise in Antworten.', [], $recruiter->id);

        $component = Livewire::test(CandidateCompare::class)
            ->set('campaignId', $campaign->id)
            ->set('applicationIds', [$candidateA->id, $candidateB->id]);

        $candidates = collect($component->instance()->comparisonCandidates)
            ->keyBy('application_id');

        $this->assertCount(2, $candidates);
        $this->assertSame(2, $candidates[$candidateA->id]['evaluation_count']);
        $this->assertSame(0.5, $candidates[$candidateA->id]['interviewer_variance']);
        $this->assertSame(3.0, $candidates[$candidateA->id]['competency_scores']['Fachkompetenz']);
        $this->assertSame(4.0, $candidates[$candidateA->id]['competency_scores']['Kommunikation']);
        $this->assertSame(4.5, $candidates[$candidateB->id]['overall_score']);

        $labels = $component->instance()->competencyLabels;
        $this->assertContains('Fachkompetenz', $labels);
        $this->assertContains('Kommunikation', $labels);

        $component->assertSee('Side-by-Side Vergleich');
        $component->assertSee('Alice Meyer');
        $component->assertSee('Bob Schmidt');
    }

    private function authenticateRecruiterForTenant(): array
    {
        $organization = Organization::factory()->create();
        $recruiter = User::factory()->create();
        $recruiter->organizations()->attach($organization, ['role' => 'recruiter']);

        $this->actingAs($recruiter);
        Filament::setTenant($organization, true);

        return [$organization, $recruiter];
    }
}
