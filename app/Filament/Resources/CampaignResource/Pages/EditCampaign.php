<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\Campaign;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['evaluation_stage_kits'] = $this->normalizeEvaluationStageKitsForForm(
            $data['evaluation_stage_kits'] ?? []
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['evaluation_stage_kits'] = $this->normalizeEvaluationStageKitsForPersistence(
            $data['evaluation_stage_kits'] ?? []
        );

        return $data;
    }

    /**
     * @param  mixed  $value
     * @return array<string, array{rubric_prompt: string, questions: array<int, array{question: string}>}>
     */
    protected function normalizeEvaluationStageKitsForForm(mixed $value): array
    {
        $kits = is_array($value) ? $value : [];
        $defaults = Campaign::defaultEvaluationStageKits();
        $normalized = [];

        foreach (['reviewed', 'interview'] as $stage) {
            $kit = is_array($kits[$stage] ?? null) ? $kits[$stage] : [];
            $defaultKit = $defaults[$stage];
            $rubricPrompt = trim((string) ($kit['rubric_prompt'] ?? $defaultKit['rubric_prompt']));
            $questions = collect($kit['questions'] ?? $defaultKit['questions'])
                ->map(function (mixed $question): array {
                    if (is_array($question)) {
                        return ['question' => trim((string) ($question['question'] ?? ''))];
                    }

                    return ['question' => trim((string) $question)];
                })
                ->filter(fn (array $question): bool => $question['question'] !== '')
                ->values()
                ->all();

            $normalized[$stage] = [
                'rubric_prompt' => $rubricPrompt !== '' ? $rubricPrompt : $defaultKit['rubric_prompt'],
                'questions' => $questions === []
                    ? array_map(
                        static fn (string $question): array => ['question' => $question],
                        $defaultKit['questions']
                    )
                    : $questions,
            ];
        }

        return $normalized;
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
