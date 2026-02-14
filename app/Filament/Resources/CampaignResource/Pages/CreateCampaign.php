<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\Campaign;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $data['organization_id'] = $tenant->id;
        }

        $data['evaluation_stage_kits'] = $this->normalizeEvaluationStageKitsForPersistence(
            $data['evaluation_stage_kits'] ?? []
        );

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        $publicJobUrl = CampaignResource::getPublicJobUrl($this->getRecord());

        if (! $publicJobUrl) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->success()
            ->title('Job erfolgreich erstellt')
            ->body("Oeffentliche Job-URL: {$publicJobUrl}")
            ->actions([
                Action::make('open_job_url')
                    ->label('Job-URL oeffnen')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url($publicJobUrl, shouldOpenInNewTab: true),
            ]);
    }

    /**
     * @param  mixed  $value
     * @return array<string, array{rubric_prompt: string, questions: array<int, string>}>
     */
    protected function normalizeEvaluationStageKitsForPersistence(mixed $value): array
    {
        $kits = is_array($value) ? $value : [];
        $defaults = Campaign::defaultEvaluationStageKits();
        $normalized = [];

        foreach (['reviewed', 'interview'] as $stage) {
            $kit = is_array($kits[$stage] ?? null) ? $kits[$stage] : [];
            $defaultKit = $defaults[$stage];
            $rubricPrompt = trim((string) ($kit['rubric_prompt'] ?? $defaultKit['rubric_prompt']));
            $questions = collect($kit['questions'] ?? [])
                ->map(function (mixed $question): string {
                    if (is_array($question)) {
                        return trim((string) ($question['question'] ?? ''));
                    }

                    return trim((string) $question);
                })
                ->filter(fn (string $question): bool => $question !== '')
                ->values()
                ->all();

            if ($questions === []) {
                $questions = $defaultKit['questions'];
            }

            $normalized[$stage] = [
                'rubric_prompt' => $rubricPrompt !== '' ? $rubricPrompt : $defaultKit['rubric_prompt'],
                'questions' => $questions,
            ];
        }

        return $normalized;
    }
}
