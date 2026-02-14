<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\CampaignScorecardCompetency;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterviewKitEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_form_loads_stage_specific_question_pack(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'evaluation_stage_kits' => [
                'reviewed' => [
                    'rubric_prompt' => 'Review prompt',
                    'questions' => [
                        'Review question A',
                        'Review question B',
                    ],
                ],
                'interview' => [
                    'rubric_prompt' => 'Interview prompt',
                    'questions' => [
                        'Interview question A',
                    ],
                ],
            ],
        ]);

        CampaignScorecardCompetency::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Fachkompetenz',
            'weight' => 3,
            'position' => 0,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Reviewed,
        ]);

        $component = Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->call('mountAction', 'submit_evaluation')
            ->assertSet('mountedActionsData.0.stage', ApplicationStatus::Reviewed->value);

        $reviewedRows = collect((array) data_get($component->get('mountedActionsData'), '0.question_responses'))
            ->values();
        $this->assertSame('Review question A', $reviewedRows->first()['question'] ?? null);

        $component->set('mountedActionsData.0.stage', ApplicationStatus::Interview->value);

        $interviewRows = collect((array) data_get($component->get('mountedActionsData'), '0.question_responses'))
            ->values();
        $this->assertSame('Interview question A', $interviewRows->first()['question'] ?? null);
    }

    public function test_evaluation_submission_requires_stage_question_answers(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'evaluation_stage_kits' => [
                'reviewed' => [
                    'rubric_prompt' => 'Review prompt',
                    'questions' => [
                        'Review question A',
                    ],
                ],
                'interview' => [
                    'rubric_prompt' => 'Interview prompt',
                    'questions' => [
                        'Interview question A',
                    ],
                ],
            ],
        ]);

        $competency = CampaignScorecardCompetency::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Fachkompetenz',
            'weight' => 3,
            'position' => 0,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Reviewed,
        ]);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->call('mountAction', 'submit_evaluation')
            ->set('mountedActionsData.0.stage', ApplicationStatus::Reviewed->value)
            ->set("mountedActionsData.0.scores.{$competency->id}", 4)
            ->set('mountedActionsData.0.question_responses.0.answer', '')
            ->set('mountedActionsData.0.rationale', 'Starker Kandidat.')
            ->call('callMountedAction')
            ->assertHasErrors();

        $this->assertDatabaseMissing('application_evaluations', [
            'application_id' => $application->id,
        ]);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->call('mountAction', 'submit_evaluation')
            ->set('mountedActionsData.0.stage', ApplicationStatus::Reviewed->value)
            ->set("mountedActionsData.0.scores.{$competency->id}", 4)
            ->set('mountedActionsData.0.question_responses.0.answer', 'Antwort auf die Leitfrage.')
            ->set('mountedActionsData.0.rationale', 'Starker Kandidat.')
            ->call('callMountedAction')
            ->assertHasNoErrors();

        $evaluation = $application->evaluations()->firstOrFail();
        $this->assertSame(ApplicationStatus::Reviewed->value, $evaluation->stage);
        $this->assertSame('Review question A', $evaluation->question_responses[0]['question']);
        $this->assertSame('Antwort auf die Leitfrage.', $evaluation->question_responses[0]['answer']);
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
