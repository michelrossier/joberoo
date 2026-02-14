<?php

namespace App\Filament\Pages;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationEvaluation;
use App\Models\Campaign;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CandidateCompare extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Bewerbungsmanagement';

    protected static ?string $navigationLabel = 'Kandidatenvergleich';

    protected static ?string $title = 'Kandidatenvergleich';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.candidate-compare';

    public int | string | null $campaignId = null;

    /**
     * @var array<int, int|string>
     */
    public array $applicationIds = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->organizations()
            ->whereKey($tenant)
            ->wherePivotIn('role', ['admin', 'recruiter'])
            ->exists();
    }

    public function updatedCampaignId(): void
    {
        $this->applicationIds = [];
    }

    /**
     * @return array<int, string>
     */
    public function getCampaignOptionsProperty(): array
    {
        return $this->getScopedCampaignQuery()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getApplicationOptionsProperty(): array
    {
        if (! filled($this->campaignId)) {
            return [];
        }

        return Application::query()
            ->where('campaign_id', (int) $this->campaignId)
            ->orderByDesc('created_at')
            ->get()
            ->mapWithKeys(function (Application $application): array {
                $statusValue = $application->status instanceof ApplicationStatus
                    ? $application->status->value
                    : (string) $application->status;
                $statusLabel = ApplicationStatus::tryFrom($statusValue)?->label() ?? $statusValue;

                return [
                    $application->id => sprintf(
                        '%s (%s)',
                        trim($application->full_name) !== '' ? $application->full_name : "Bewerbung {$application->id}",
                        $statusLabel
                    ),
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, Application>
     */
    public function getSelectedApplicationsProperty(): Collection
    {
        if (! filled($this->campaignId) || $this->applicationIds === []) {
            return collect();
        }

        $selectedIds = collect($this->applicationIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        $applications = Application::query()
            ->where('campaign_id', (int) $this->campaignId)
            ->whereIn('id', $selectedIds->all())
            ->with([
                'campaign.scorecardCompetencies',
                'evaluations.evaluator',
                'activities' => fn ($query) => $query
                    ->whereIn('type', ['note_added', 'status_changed'])
                    ->orderByDesc('created_at'),
            ])
            ->get()
            ->keyBy('id');

        return $selectedIds
            ->map(fn (int $id): ?Application => $applications->get($id))
            ->filter()
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getComparisonCandidatesProperty(): array
    {
        return $this->selectedApplications
            ->map(function (Application $application): array {
                $statusValue = $application->status instanceof ApplicationStatus
                    ? $application->status->value
                    : (string) $application->status;
                $statusLabel = ApplicationStatus::tryFrom($statusValue)?->label() ?? $statusValue;
                $competencyScores = $this->buildCompetencyAverages($application);
                $evaluatorOverallScores = $this->buildEvaluatorOverallScores($application);
                $latestStatusChange = $application->activities
                    ->first(fn ($activity): bool => $activity->type === 'status_changed');
                $keyNotes = $application->activities
                    ->filter(fn ($activity): bool => $activity->type === 'note_added' && filled($activity->note))
                    ->take(3)
                    ->values()
                    ->all();
                $overallScore = $competencyScores === []
                    ? null
                    : round(collect($competencyScores)->avg(), 2);

                return [
                    'application_id' => $application->id,
                    'name' => trim($application->full_name) !== ''
                        ? $application->full_name
                        : "Bewerbung {$application->id}",
                    'status' => $statusLabel,
                    'source' => $application->source_label,
                    'submitted_at' => $application->created_at?->format('d.m.Y H:i'),
                    'last_status_change' => $latestStatusChange?->created_at?->format('d.m.Y H:i'),
                    'evaluation_count' => $application->evaluations->count(),
                    'overall_score' => $overallScore,
                    'interviewer_variance' => $this->standardDeviation($evaluatorOverallScores),
                    'competency_scores' => $competencyScores,
                    'key_notes' => $keyNotes,
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    public function getCompetencyLabelsProperty(): array
    {
        return collect($this->comparisonCandidates)
            ->flatMap(fn (array $candidate): array => array_keys($candidate['competency_scores']))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, float>
     */
    protected function buildCompetencyAverages(Application $application): array
    {
        $competencies = $application->getScorecardCompetencies();

        if ($competencies->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($competencies as $competency) {
            $scores = $application->evaluations
                ->map(function (ApplicationEvaluation $evaluation) use ($competency): ?float {
                    $value = $evaluation->scores[(string) $competency->id]
                        ?? $evaluation->scores[$competency->id]
                        ?? null;

                    if (! is_numeric($value)) {
                        return null;
                    }

                    return (float) $value;
                })
                ->filter()
                ->values();

            if ($scores->isEmpty()) {
                continue;
            }

            $result[$competency->name] = round($scores->avg(), 2);
        }

        return $result;
    }

    /**
     * @return list<float>
     */
    protected function buildEvaluatorOverallScores(Application $application): array
    {
        $competencies = $application->getScorecardCompetencies();

        if ($competencies->isEmpty()) {
            return [];
        }

        return $application->evaluations
            ->map(fn (ApplicationEvaluation $evaluation): ?float => $evaluation->weightedScore($competencies))
            ->filter(fn (?float $score): bool => $score !== null)
            ->values()
            ->all();
    }

    /**
     * @param  list<float>  $values
     */
    protected function standardDeviation(array $values): ?float
    {
        $count = count($values);

        if ($count < 2) {
            return null;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(
            static fn (float $value): float => ($value - $mean) ** 2,
            $values
        )) / $count;

        return round(sqrt($variance), 2);
    }

    protected function getScopedCampaignQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return Campaign::query()
            ->when($tenant, fn (Builder $query) => $query->where('organization_id', $tenant->id));
    }
}
