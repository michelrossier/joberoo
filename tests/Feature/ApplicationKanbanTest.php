<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Models\Application;
use App\Models\ApplicationEvaluation;
use App\Models\Campaign;
use App\Models\CampaignScorecardCompetency;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApplicationStatusMessageNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_displays_new_lane_with_newest_first(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $olderApplication = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'first_name' => 'Older',
            'last_name' => 'Candidate',
            'created_at' => now()->subDay(),
        ]);

        $newerApplication = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'first_name' => 'Newer',
            'last_name' => 'Candidate',
            'created_at' => now(),
        ]);

        Livewire::test(ListApplications::class)
            ->assertSee('Neu')
            ->assertSee('Abgelehnt')
            ->assertSee($campaign->title)
            ->assertSeeInOrder([
                $newerApplication->full_name,
                $olderApplication->full_name,
            ]);
    }

    public function test_dragging_application_to_new_lane_updates_status(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
        ]);

        Livewire::test(ListApplications::class)
            ->call('moveApplication', $application->id, ApplicationStatus::Interview->value);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Interview->value,
        ]);
    }

    public function test_status_transition_action_can_change_status_without_sending_mail(): void
    {
        Notification::fake();

        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
        ]);

        Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Reviewed->value,
            ])
            ->set('mountedActionsData.0.send_message', 0)
            ->call('callMountedAction');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Reviewed->value,
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'status_changed',
        ]);
        $this->assertDatabaseMissing('application_activities', [
            'application_id' => $application->id,
            'type' => 'applicant_message_sent',
        ]);

        Notification::assertNothingSent();
    }

    public function test_status_transition_action_can_send_customized_mail_and_log_activity(): void
    {
        Notification::fake();

        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'email' => 'applicant@example.com',
        ]);

        Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Interview->value,
            ])
            ->set('mountedActionsData.0.send_message', 1)
            ->set('mountedActionsData.0.template_key', '0')
            ->set('mountedActionsData.0.subject', 'Kurzes Update zu Ihrer Bewerbung')
            ->set('mountedActionsData.0.message_html', '<p>Vielen Dank fuer Ihre Geduld.</p>')
            ->call('callMountedAction');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Interview->value,
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'status_changed',
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'applicant_message_sent',
        ]);

        Notification::assertSentOnDemand(
            ApplicationStatusMessageNotification::class,
            function (ApplicationStatusMessageNotification $notification, array $channels, object $notifiable): bool {
                return ($notifiable->routes['mail'] ?? null) === 'applicant@example.com'
                    && $notification->subjectLine === 'Kurzes Update zu Ihrer Bewerbung';
            }
        );
    }

    public function test_status_transition_action_can_skip_message_from_message_mode_and_still_update_status(): void
    {
        Notification::fake();

        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'email' => 'applicant@example.com',
        ]);
        $competency = CampaignScorecardCompetency::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Fachkompetenz',
            'weight' => 3,
            'position' => 0,
        ]);
        ApplicationEvaluation::query()->create([
            'application_id' => $application->id,
            'evaluator_id' => $recruiter->id,
            'stage' => ApplicationStatus::Reviewed->value,
            'scores' => [
                (string) $competency->id => 4,
            ],
            'question_responses' => [
                [
                    'question' => 'Welche Staerken sehen Sie bezogen auf die Kernanforderungen?',
                    'answer' => 'Gute fachliche Basis und klare Beispiele.',
                ],
                [
                    'question' => 'Wo sehen Sie moegliche Risiken oder offene Punkte?',
                    'answer' => 'Leichte Luecke bei Stakeholder-Kommunikation.',
                ],
                [
                    'question' => 'Welche Evidenz aus Unterlagen/Screening stuetzt Ihre Einschaetzung?',
                    'answer' => 'Lebenslauf und Screening-Call zeigen relevante Projekterfahrung.',
                ],
            ],
            'rationale' => 'Klares Match fuer die Rolle.',
        ]);

        Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Dismissed->value,
            ])
            ->set('mountedActionsData.0.send_message', 1)
            ->set('mountedActionsData.0.subject', '')
            ->set('mountedActionsData.0.message_html', '')
            ->call('callMountedAction', ['force_without_message' => true]);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Dismissed->value,
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'status_changed',
        ]);
        $this->assertDatabaseMissing('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'applicant_message_sent',
        ]);

        Notification::assertNothingSent();
    }

    public function test_status_transition_action_can_still_send_mail_if_status_is_already_at_target(): void
    {
        Notification::fake();

        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
            'email' => 'applicant@example.com',
        ]);

        Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Interview->value,
            ])
            ->set('mountedActionsData.0.send_message', 1)
            ->set('mountedActionsData.0.template_key', '0')
            ->set('mountedActionsData.0.subject', 'Kurzes Update zu Ihrer Bewerbung')
            ->set('mountedActionsData.0.message_html', '<p>Vielen Dank fuer Ihre Geduld.</p>')
            ->call('callMountedAction');

        $this->assertDatabaseMissing('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'status_changed',
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'actor_id' => $recruiter->id,
            'type' => 'applicant_message_sent',
        ]);

        Notification::assertSentOnDemand(
            ApplicationStatusMessageNotification::class,
            function (ApplicationStatusMessageNotification $notification, array $channels, object $notifiable): bool {
                return ($notifiable->routes['mail'] ?? null) === 'applicant@example.com'
                    && $notification->subjectLine === 'Kurzes Update zu Ihrer Bewerbung';
            }
        );
    }

    public function test_final_status_transition_requires_complete_evaluation(): void
    {
        Notification::fake();

        [$organization, $recruiter] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
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

        Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Accepted->value,
            ])
            ->set('mountedActionsData.0.send_message', 0)
            ->call('callMountedAction');

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
                    'question' => 'Welche Staerken sehen Sie bezogen auf die Kernanforderungen?',
                    'answer' => 'Sehr gute Problemloesung und Priorisierung.',
                ],
                [
                    'question' => 'Wo sehen Sie moegliche Risiken oder offene Punkte?',
                    'answer' => 'Onboarding in neue Toolchain kann etwas dauern.',
                ],
                [
                    'question' => 'Welche Evidenz aus Unterlagen/Screening stuetzt Ihre Einschaetzung?',
                    'answer' => 'Konkrete Projektergebnisse im Screening vorgestellt.',
                ],
            ],
            'rationale' => 'Starke, konsistente Signale aus dem Interview.',
        ]);

        Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Accepted->value,
            ])
            ->set('mountedActionsData.0.send_message', 0)
            ->call('callMountedAction');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Accepted->value,
        ]);
    }

    public function test_status_transition_action_prefills_default_template_when_organization_has_none(): void
    {
        [$organization] = $this->authenticateRecruiterForTenant();

        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::New,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
        ]);

        $component = Livewire::test(ListApplications::class)
            ->call('mountAction', 'statusTransition', [
                'applicationId' => $application->id,
                'newStatus' => ApplicationStatus::Reviewed->value,
            ])
            ->assertSet('mountedActionsData.0.subject', 'Vielen Dank fuer Ihre Bewerbung')
            ->assertSet('mountedActionsData.0.send_message', 0);

        $messageHtml = (string) data_get($component->get('mountedActionsData'), '0.message_html');

        $this->assertStringContainsString('Guten Tag Max Mustermann', $messageHtml);
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
