<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\Pages\ViewApplication;
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

class ScorecardEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_application_blocks_final_status_without_complete_evaluation(): void
    {
        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'evaluation_stage_kits' => [
                'reviewed' => [
                    'rubric_prompt' => 'Pruefen Sie Grundqualifikation und Potenzial.',
                    'questions' => [
                        'Welche fachlichen Signale sind sichtbar?',
                    ],
                ],
                'interview' => [
                    'rubric_prompt' => 'Pruefen Sie Verhaltensbeispiele und Team-Fit.',
                    'questions' => [
                        'Welche Interviewbeispiele stuetzen Ihre Empfehlung?',
                    ],
                ],
            ],
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
            'weight' => 2,
            'position' => 1,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
        ]);

        Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
            ->set('data.status', ApplicationStatus::Accepted->value)
            ->call('save')
            ->assertHasErrors(['data.status']);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Interview->value,
        ]);

        ApplicationEvaluation::query()->create([
            'application_id' => $application->id,
            'evaluator_id' => $recruiter->id,
            'stage' => ApplicationStatus::Reviewed->value,
            'scores' => [
                (string) $competencyA->id => 5,
                (string) $competencyB->id => 4,
            ],
            'question_responses' => [
                [
                    'question' => 'Welche fachlichen Signale sind sichtbar?',
                    'answer' => 'Saubere Architekturentscheidungen und hohe Ownership.',
                ],
            ],
            'rationale' => 'Kandidat zeigt exzellente Qualitaet in den Kernkriterien.',
        ]);

        Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
            ->set('data.status', ApplicationStatus::Accepted->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Accepted->value,
        ]);
    }

    public function test_view_application_evaluation_action_creates_or_updates_evaluation(): void
    {
        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'evaluation_stage_kits' => [
                'reviewed' => [
                    'rubric_prompt' => 'Pruefen Sie Grundqualifikation und Potenzial.',
                    'questions' => [
                        'Welche fachlichen Signale sind sichtbar?',
                    ],
                ],
                'interview' => [
                    'rubric_prompt' => 'Pruefen Sie Verhaltensbeispiele und Team-Fit.',
                    'questions' => [
                        'Welche Interviewbeispiele stuetzen Ihre Empfehlung?',
                    ],
                ],
            ],
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
            'weight' => 2,
            'position' => 1,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
        ]);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->call('mountAction', 'submit_evaluation')
            ->set('mountedActionsData.0.stage', ApplicationStatus::Reviewed->value)
            ->set("mountedActionsData.0.scores.{$competencyA->id}", 4)
            ->set("mountedActionsData.0.scores.{$competencyB->id}", 5)
            ->set('mountedActionsData.0.question_responses.0.answer', 'Zeigt starke Fachsignale im Screening und Lebenslauf.')
            ->set('mountedActionsData.0.rationale', 'Sehr starke Kommunikations- und Fachsignale.')
            ->call('callMountedAction')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('application_evaluations', [
            'application_id' => $application->id,
            'evaluator_id' => $recruiter->id,
            'stage' => ApplicationStatus::Reviewed->value,
            'rationale' => 'Sehr starke Kommunikations- und Fachsignale.',
        ]);
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
