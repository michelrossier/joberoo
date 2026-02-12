<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class ListApplications extends Page
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.list-applications';

    public function moveApplication(int $applicationId, string $newStatus): void
    {
        if (! in_array($newStatus, $this->getKanbanStatusValues(), true)) {
            return;
        }

        $application = $this->getBoardQuery()
            ->whereKey($applicationId)
            ->first();

        if (! $application) {
            return;
        }

        $currentStatus = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        if ($currentStatus === $newStatus) {
            return;
        }

        $application->update([
            'status' => $newStatus,
        ]);

        $application->recordActivity('status_changed', null, [
            'from' => ApplicationStatus::tryFrom($currentStatus)?->label() ?? $currentStatus,
            'to' => ApplicationStatus::tryFrom($newStatus)?->label() ?? $newStatus,
            'from_value' => $currentStatus,
            'to_value' => $newStatus,
        ], auth()->id());
    }

    public function getLanesProperty(): array
    {
        $statuses = $this->getKanbanStatuses();
        $applicationsByStatus = $this->getBoardQuery()
            ->whereIn('status', $this->getKanbanStatusValues())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Application $application): string => $application->status instanceof ApplicationStatus
                ? $application->status->value
                : (string) $application->status);

        return array_map(function (ApplicationStatus $status) use ($applicationsByStatus): array {
            return [
                'value' => $status->value,
                'label' => $status->label(),
                'applications' => $applicationsByStatus->get($status->value, collect()),
            ];
        }, $statuses);
    }

    protected function getBoardQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return Application::query()
            ->with(['campaign', 'assignedUser'])
            ->when($tenant, function (Builder $query) use ($tenant): void {
                $query->whereHas('campaign', fn (Builder $campaignQuery) => $campaignQuery->where('organization_id', $tenant->id));
            });
    }

    /**
     * @return list<ApplicationStatus>
     */
    protected function getKanbanStatuses(): array
    {
        return [
            ApplicationStatus::New,
            ApplicationStatus::Reviewed,
            ApplicationStatus::Interview,
            ApplicationStatus::Accepted,
            ApplicationStatus::Dismissed,
        ];
    }

    /**
     * @return list<string>
     */
    protected function getKanbanStatusValues(): array
    {
        return array_map(
            static fn (ApplicationStatus $status): string => $status->value,
            $this->getKanbanStatuses()
        );
    }
}
