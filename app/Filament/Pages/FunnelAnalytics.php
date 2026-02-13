<?php

namespace App\Filament\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Widgets\FunnelAnalyticsCampaignPerformanceChart;
use App\Filament\Widgets\FunnelAnalyticsOverviewStatsWidget;
use App\Filament\Widgets\FunnelAnalyticsRecruiterThroughputChart;
use App\Filament\Widgets\FunnelAnalyticsSourcesChart;
use App\Filament\Widgets\FunnelAnalyticsStageFunnelChart;
use App\Models\ApplicationActivity;
use App\Models\Application;
use App\Models\Campaign;
use App\Models\CampaignVisit;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FunnelAnalytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Bewerbungsmanagement';

    protected static ?string $navigationLabel = 'Analyse-Dashboard';

    protected static ?string $title = 'Erweitertes Analyse-Dashboard';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.funnel-analytics';

    public int | string | null $campaignId = null;

    public int $days = 30;

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    protected function getFooterWidgets(): array
    {
        return [
            FunnelAnalyticsOverviewStatsWidget::class,
            FunnelAnalyticsStageFunnelChart::class,
            FunnelAnalyticsSourcesChart::class,
            FunnelAnalyticsRecruiterThroughputChart::class,
            FunnelAnalyticsCampaignPerformanceChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'totals' => $this->totals,
            'kpis' => $this->kpis,
            'stageFunnel' => $this->stageFunnel,
            'rows' => $this->rows,
            'recruiterThroughput' => $this->recruiterThroughput,
            'campaignPerformance' => $this->campaignPerformance,
        ];
    }

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

    public function getCampaignOptionsProperty(): array
    {
        return $this->getScopedCampaignQuery()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    public function getRowsProperty(): array
    {
        $campaignIds = $this->getSelectedCampaignIds();

        if ($campaignIds === []) {
            return [];
        }

        $from = $this->getFromDate();

        $viewsBySource = CampaignVisit::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'direct') AS source_key, COUNT(*) AS views_count")
            ->groupBy('source_key')
            ->pluck('views_count', 'source_key');

        $submissionsBySource = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'direct') AS source_key, COUNT(*) AS submissions_count")
            ->groupBy('source_key')
            ->pluck('submissions_count', 'source_key');

        $sourceKeys = Collection::make($viewsBySource->keys())
            ->merge($submissionsBySource->keys())
            ->unique()
            ->values();

        return $sourceKeys
            ->map(function (string $sourceKey) use ($viewsBySource, $submissionsBySource): array {
                $views = (int) ($viewsBySource[$sourceKey] ?? 0);
                $submissions = (int) ($submissionsBySource[$sourceKey] ?? 0);

                return [
                    'source' => $sourceKey,
                    'views' => $views,
                    'submissions' => $submissions,
                    'conversion' => $views > 0
                        ? round(($submissions / $views) * 100, 1)
                        : 0.0,
                ];
            })
            ->sortByDesc('submissions')
            ->values()
            ->all();
    }

    public function getTotalsProperty(): array
    {
        $rows = $this->rows;
        $views = array_sum(array_map(fn (array $row): int => $row['views'], $rows));
        $submissions = array_sum(array_map(fn (array $row): int => $row['submissions'], $rows));

        return [
            'views' => $views,
            'submissions' => $submissions,
            'conversion' => $views > 0 ? round(($submissions / $views) * 100, 1) : 0.0,
            'sources' => count($rows),
        ];
    }

    public function getKpisProperty(): array
    {
        $applications = $this->analyticsApplications;

        if ($applications->isEmpty()) {
            return [
                'applications' => 0,
                'active' => 0,
                'hired' => 0,
                'avg_time_to_review_hours' => 0.0,
                'avg_time_to_hire_hours' => 0.0,
            ];
        }

        $reviewDurations = [];
        $hireDurations = [];

        foreach ($applications as $application) {
            $firstReviewAt = $application->activities
                ->first(fn (ApplicationActivity $activity): bool => $this->activityMovesToAny(
                    $activity,
                    [ApplicationStatus::Reviewed->value, ApplicationStatus::Interview->value, ApplicationStatus::Accepted->value, ApplicationStatus::Dismissed->value]
                ))?->created_at;

            if ($firstReviewAt) {
                $reviewDurations[] = $application->created_at->diffInMinutes($firstReviewAt, true) / 60;
            }

            $hiredAt = $application->activities
                ->first(fn (ApplicationActivity $activity): bool => $this->activityMovesToAny(
                    $activity,
                    [ApplicationStatus::Accepted->value]
                ))?->created_at;

            if (! $hiredAt && $this->getApplicationStatusValue($application) === ApplicationStatus::Accepted->value) {
                $hiredAt = $application->updated_at;
            }

            if ($hiredAt) {
                $hireDurations[] = $application->created_at->diffInMinutes($hiredAt, true) / 60;
            }
        }

        return [
            'applications' => $applications->count(),
            'active' => $applications->filter(function (Application $application): bool {
                return ! in_array($this->getApplicationStatusValue($application), [
                    ApplicationStatus::Accepted->value,
                    ApplicationStatus::Dismissed->value,
                ], true);
            })->count(),
            'hired' => $applications->filter(
                fn (Application $application): bool => $this->getApplicationStatusValue($application) === ApplicationStatus::Accepted->value
            )->count(),
            'avg_time_to_review_hours' => $this->average($reviewDurations),
            'avg_time_to_hire_hours' => $this->average($hireDurations),
        ];
    }

    public function getStageFunnelProperty(): array
    {
        $applications = $this->analyticsApplications;
        $submitted = $applications->count();

        if ($submitted === 0) {
            return [
                ['label' => 'Eingegangen', 'count' => 0, 'conversion' => 0.0],
                ['label' => 'In Bearbeitung', 'count' => 0, 'conversion' => 0.0],
                ['label' => 'Interview', 'count' => 0, 'conversion' => 0.0],
                ['label' => 'Angenommen', 'count' => 0, 'conversion' => 0.0],
            ];
        }

        $reviewed = $applications->filter(fn (Application $application): bool => $this->applicationReachedStage($application, ApplicationStatus::Reviewed->value))->count();
        $interview = $applications->filter(fn (Application $application): bool => $this->applicationReachedStage($application, ApplicationStatus::Interview->value))->count();
        $accepted = $applications->filter(fn (Application $application): bool => $this->applicationReachedStage($application, ApplicationStatus::Accepted->value))->count();

        return [
            ['label' => 'Eingegangen', 'count' => $submitted, 'conversion' => 100.0],
            ['label' => 'In Bearbeitung', 'count' => $reviewed, 'conversion' => $this->conversion($reviewed, $submitted)],
            ['label' => 'Interview', 'count' => $interview, 'conversion' => $this->conversion($interview, $reviewed)],
            ['label' => 'Angenommen', 'count' => $accepted, 'conversion' => $this->conversion($accepted, $interview)],
        ];
    }

    public function getRecruiterThroughputProperty(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $users = $tenant->users()
            ->wherePivotIn('role', ['admin', 'recruiter'])
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->all();
        $campaignIds = $this->getSelectedCampaignIds();
        $from = $this->getFromDate();

        if ($campaignIds === []) {
            return [];
        }

        $assignedByUser = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->whereIn('assigned_user_id', $userIds)
            ->selectRaw('assigned_user_id, COUNT(*) AS aggregate_count')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate_count', 'assigned_user_id');

        $hiresByUser = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->where('status', ApplicationStatus::Accepted->value)
            ->whereIn('assigned_user_id', $userIds)
            ->selectRaw('assigned_user_id, COUNT(*) AS aggregate_count')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate_count', 'assigned_user_id');

        $statusUpdatesByUser = ApplicationActivity::query()
            ->whereIn('actor_id', $userIds)
            ->where('type', 'status_changed')
            ->where('created_at', '>=', $from)
            ->whereHas('application', fn (Builder $query) => $query->whereIn('campaign_id', $campaignIds))
            ->selectRaw('actor_id, COUNT(*) AS aggregate_count')
            ->groupBy('actor_id')
            ->pluck('aggregate_count', 'actor_id');

        $notesByUser = ApplicationActivity::query()
            ->whereIn('actor_id', $userIds)
            ->where('type', 'note_added')
            ->where('created_at', '>=', $from)
            ->whereHas('application', fn (Builder $query) => $query->whereIn('campaign_id', $campaignIds))
            ->selectRaw('actor_id, COUNT(*) AS aggregate_count')
            ->groupBy('actor_id')
            ->pluck('aggregate_count', 'actor_id');

        return $users
            ->map(function (User $user) use ($assignedByUser, $hiresByUser, $statusUpdatesByUser, $notesByUser): array {
                $assigned = (int) ($assignedByUser[$user->id] ?? 0);
                $hires = (int) ($hiresByUser[$user->id] ?? 0);
                $statusUpdates = (int) ($statusUpdatesByUser[$user->id] ?? 0);
                $notes = (int) ($notesByUser[$user->id] ?? 0);

                return [
                    'name' => $user->name,
                    'assigned' => $assigned,
                    'status_updates' => $statusUpdates,
                    'notes' => $notes,
                    'hires' => $hires,
                    'hire_rate' => $this->conversion($hires, $assigned),
                    'throughput_score' => $statusUpdates + $notes + ($hires * 2),
                ];
            })
            ->sortByDesc('throughput_score')
            ->values()
            ->all();
    }

    public function getCampaignPerformanceProperty(): array
    {
        $campaigns = $this->getScopedCampaignQuery()
            ->whereIn('id', $this->getSelectedCampaignIds())
            ->get(['id', 'title']);
        $campaignIds = $campaigns->pluck('id')->all();

        if ($campaignIds === []) {
            return [];
        }

        $from = $this->getFromDate();

        $views = CampaignVisit::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('campaign_id, COUNT(*) AS aggregate_count')
            ->groupBy('campaign_id')
            ->pluck('aggregate_count', 'campaign_id');

        $submissions = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('campaign_id, COUNT(*) AS aggregate_count')
            ->groupBy('campaign_id')
            ->pluck('aggregate_count', 'campaign_id');

        $hires = Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $from)
            ->where('status', ApplicationStatus::Accepted->value)
            ->selectRaw('campaign_id, COUNT(*) AS aggregate_count')
            ->groupBy('campaign_id')
            ->pluck('aggregate_count', 'campaign_id');

        return $campaigns
            ->map(function (Campaign $campaign) use ($views, $submissions, $hires): array {
                $campaignViews = (int) ($views[$campaign->id] ?? 0);
                $campaignSubmissions = (int) ($submissions[$campaign->id] ?? 0);
                $campaignHires = (int) ($hires[$campaign->id] ?? 0);

                return [
                    'title' => $campaign->title,
                    'views' => $campaignViews,
                    'submissions' => $campaignSubmissions,
                    'hires' => $campaignHires,
                    'conversion' => $this->conversion($campaignSubmissions, $campaignViews),
                    'hire_rate' => $this->conversion($campaignHires, $campaignSubmissions),
                ];
            })
            ->sortByDesc('submissions')
            ->values()
            ->all();
    }

    public function getAnalyticsApplicationsProperty(): Collection
    {
        $campaignIds = $this->getSelectedCampaignIds();

        if ($campaignIds === []) {
            return collect();
        }

        return Application::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', $this->getFromDate())
            ->with([
                'activities' => fn ($query) => $query
                    ->where('type', 'status_changed')
                    ->reorder('created_at', 'asc')
                    ->orderBy('created_at'),
            ])
            ->get([
                'id',
                'campaign_id',
                'status',
                'created_at',
                'updated_at',
            ]);
    }

    protected function getScopedCampaignQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return Campaign::query()
            ->when($tenant, fn (Builder $query) => $query->where('organization_id', $tenant->id));
    }

    protected function getSelectedCampaignIds(): array
    {
        $allCampaignIds = $this->getScopedCampaignQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($allCampaignIds === []) {
            return [];
        }

        $selectedCampaignId = is_numeric($this->campaignId) ? (int) $this->campaignId : null;

        if ($selectedCampaignId && in_array($selectedCampaignId, $allCampaignIds, true)) {
            return [$selectedCampaignId];
        }

        return $allCampaignIds;
    }

    protected function getFromDate(): Carbon
    {
        return now()->subDays(max(1, (int) $this->days));
    }

    protected function getApplicationStatusValue(Application $application): string
    {
        $status = $application->status;

        if ($status instanceof ApplicationStatus) {
            return $status->value;
        }

        return (string) $status;
    }

    protected function applicationReachedStage(Application $application, string $targetStatus): bool
    {
        $currentStatus = $this->getApplicationStatusValue($application);

        if ($currentStatus === $targetStatus) {
            return true;
        }

        if ($targetStatus === ApplicationStatus::Reviewed->value && in_array($currentStatus, [
            ApplicationStatus::Reviewed->value,
            ApplicationStatus::Interview->value,
            ApplicationStatus::Accepted->value,
            ApplicationStatus::Dismissed->value,
        ], true)) {
            return true;
        }

        if ($targetStatus === ApplicationStatus::Interview->value && in_array($currentStatus, [
            ApplicationStatus::Interview->value,
            ApplicationStatus::Accepted->value,
        ], true)) {
            return true;
        }

        return $application->activities
            ->contains(fn (ApplicationActivity $activity): bool => $this->activityMovesToAny($activity, [$targetStatus]));
    }

    /**
     * @param  array<string>  $statusValues
     */
    protected function activityMovesToAny(ApplicationActivity $activity, array $statusValues): bool
    {
        $toValue = $this->getActivityToStatusValue($activity);

        return $toValue !== null && in_array($toValue, $statusValues, true);
    }

    protected function getActivityToStatusValue(ApplicationActivity $activity): ?string
    {
        $rawToValue = data_get($activity->meta, 'to_value');

        if (is_string($rawToValue) && $rawToValue !== '') {
            return $rawToValue;
        }

        $rawTo = data_get($activity->meta, 'to');

        if (! is_string($rawTo) || $rawTo === '') {
            return null;
        }

        return match ($rawTo) {
            'Neu' => ApplicationStatus::New->value,
            'Geprueft', 'In Bearbeitung' => ApplicationStatus::Reviewed->value,
            'Interview' => ApplicationStatus::Interview->value,
            'Angenommen' => ApplicationStatus::Accepted->value,
            'Abgelehnt' => ApplicationStatus::Dismissed->value,
            default => null,
        };
    }

    protected function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return round(array_sum($values) / count($values), 1);
    }

    protected function conversion(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
