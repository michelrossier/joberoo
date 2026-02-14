<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Application $record */
        $oldStatus = $record->status instanceof ApplicationStatus ? $record->status->value : (string) $record->status;
        $oldAssignedUserId = $record->assigned_user_id;
        $newStatus = isset($data['status']) ? (string) $data['status'] : $oldStatus;

        if (
            $oldStatus !== $newStatus
            && Application::statusRequiresEvaluation($newStatus)
            && ! $record->hasCompleteEvaluation()
        ) {
            throw ValidationException::withMessages([
                'data.status' => 'Vor finalen Entscheidungen ist eine vollstaendige Stage-Bewertung mit Begruendung und Leitfragen noetig.',
            ]);
        }

        $record->update($data);

        $newStatus = $record->status instanceof ApplicationStatus ? $record->status->value : (string) $record->status;

        if ($oldStatus !== $newStatus) {
            $record->recordActivity('status_changed', null, [
                'from' => ApplicationStatus::tryFrom($oldStatus)?->label() ?? $oldStatus,
                'to' => ApplicationStatus::tryFrom($newStatus)?->label() ?? $newStatus,
                'from_value' => $oldStatus,
                'to_value' => $newStatus,
            ], auth()->id());
        }

        if ($oldAssignedUserId !== $record->assigned_user_id) {
            $oldAssigneeName = $oldAssignedUserId ? User::query()->find($oldAssignedUserId)?->name : null;
            $record->unsetRelation('assignedUser');
            $newAssigneeName = $record->assignedUser?->name;

            $record->recordActivity('assignment_changed', null, [
                'from' => $oldAssigneeName ?? 'Nicht zugewiesen',
                'to' => $newAssigneeName ?? 'Nicht zugewiesen',
            ], auth()->id());
        }

        return $record;
    }
}
