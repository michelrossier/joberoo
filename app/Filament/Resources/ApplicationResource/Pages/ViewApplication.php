<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationEvaluation;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_note')
                ->label('Notiz hinzufuegen')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Textarea::make('note')
                        ->label('Notiz')
                        ->required()
                        ->rows(5)
                        ->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    $record->recordActivity('note_added', $data['note'], [], auth()->id());
                    $this->record = $record->fresh();
                }),
            Actions\Action::make('submit_evaluation')
                ->label('Bewertung erfassen')
                ->icon('heroicon-o-clipboard-document-check')
                ->mountUsing(function (Schema $form): void {
                    /** @var Application $record */
                    $record = $this->getRecord();
                    $existingEvaluation = $record->evaluations()
                        ->where('evaluator_id', auth()->id())
                        ->first();
                    $defaultStage = $this->resolveDefaultEvaluationStage($record);
                    $stage = (string) ($existingEvaluation?->stage ?? $defaultStage);

                    $form->fill([
                        'stage' => $stage,
                        'scores' => $existingEvaluation?->scores ?? [],
                        'question_responses' => $this->buildQuestionResponseRowsForStage(
                            $record,
                            $stage,
                            is_array($existingEvaluation?->question_responses) ? $existingEvaluation->question_responses : []
                        ),
                        'rationale' => $existingEvaluation?->rationale ?? '',
                    ]);
                })
                ->disabled(fn (): bool => $this->getRecord()->getScorecardCompetencies()->isEmpty())
                ->tooltip(fn (): ?string => $this->getRecord()->getScorecardCompetencies()->isEmpty()
                    ? 'Bitte hinterlegen Sie zuerst Scorecard-Kompetenzen im Job.'
                    : null)
                ->form(fn (): array => $this->getEvaluationFormSchema())
                ->action(function (array $data): void {
                    /** @var Application $record */
                    $record = $this->getRecord();
                    $userId = auth()->id();
                    $competencies = $record->getScorecardCompetencies();
                    $stage = (string) ($data['stage'] ?? $this->resolveDefaultEvaluationStage($record));
                    $allowedStages = array_keys($this->getEvaluationStageOptions());

                    if (! in_array($stage, $allowedStages, true)) {
                        $stage = $this->resolveDefaultEvaluationStage($record);
                    }

                    if (! $userId || $competencies->isEmpty()) {
                        return;
                    }

                    $inputScores = is_array($data['scores'] ?? null) ? $data['scores'] : [];
                    $normalizedScores = [];

                    foreach ($competencies as $competency) {
                        $rawScore = $inputScores[(string) $competency->id]
                            ?? $inputScores[$competency->id]
                            ?? null;
                        $score = is_numeric($rawScore) ? (int) $rawScore : 0;
                        $normalizedScores[(string) $competency->id] = max(1, min(5, $score));
                    }

                    $questionResponses = $this->normalizeQuestionResponses(
                        is_array($data['question_responses'] ?? null) ? $data['question_responses'] : []
                    );
                    $requiredQuestions = $this->getStageQuestions($record, $stage);
                    $answersByQuestion = collect($questionResponses)
                        ->mapWithKeys(fn (array $row): array => [
                            $row['question'] => $row['answer'],
                        ])
                        ->all();

                    foreach ($requiredQuestions as $question) {
                        if (blank(trim((string) ($answersByQuestion[$question] ?? '')))) {
                            throw ValidationException::withMessages([
                                'question_responses' => 'Bitte beantworten Sie alle Leitfragen fuer diese Stage.',
                            ]);
                        }
                    }

                    $evaluation = ApplicationEvaluation::query()->updateOrCreate(
                        [
                            'application_id' => $record->id,
                            'evaluator_id' => $userId,
                        ],
                        [
                            'stage' => $stage,
                            'scores' => $normalizedScores,
                            'question_responses' => $questionResponses,
                            'rationale' => trim((string) ($data['rationale'] ?? '')),
                        ]
                    );

                    $record->recordActivity('evaluation_submitted', null, [
                        'stage' => ApplicationStatus::tryFrom($stage)?->label() ?? $stage,
                        'overall_score' => $evaluation->weightedScore($competencies),
                    ], $userId);

                    $this->record = $record->fresh([
                        'campaign.scorecardCompetencies',
                        'evaluations.evaluator',
                        'activities.actor',
                    ]);
                }),
            Actions\EditAction::make(),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function getEvaluationFormSchema(): array
    {
        /** @var Application $record */
        $record = $this->getRecord();
        $competencies = $record->getScorecardCompetencies();

        if ($competencies->isEmpty()) {
            return [
                Placeholder::make('scorecard_missing')
                    ->label('Scorecard')
                    ->content('Dieser Job hat noch keine Kompetenzen. Bitte in der Job-Konfiguration hinterlegen.'),
            ];
        }

        $schema = [
            Select::make('stage')
                ->label('Bewertungsstage')
                ->options($this->getEvaluationStageOptions())
                ->default($this->resolveDefaultEvaluationStage($record))
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($record): void {
                    $stage = is_string($state) ? $state : $this->resolveDefaultEvaluationStage($record);
                    $set('question_responses', $this->buildQuestionResponseRowsForStage(
                        $record,
                        $stage,
                        is_array($get('question_responses')) ? $get('question_responses') : []
                    ));
                }),
            Placeholder::make('rubric_prompt')
                ->label('Rubrik-Prompt')
                ->content(function (Get $get) use ($record): string {
                    $stage = (string) ($get('stage') ?? $this->resolveDefaultEvaluationStage($record));

                    return $this->getStageRubricPrompt($record, $stage)
                        ?? 'Keine Rubrik fuer diese Stage hinterlegt.';
                }),
            Repeater::make('question_responses')
                ->label('Leitfragen')
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->default($this->buildQuestionResponseRowsForStage($record, $this->resolveDefaultEvaluationStage($record)))
                ->schema([
                    Textarea::make('question')
                        ->label('Frage')
                        ->disabled()
                        ->dehydrated()
                        ->rows(2),
                    Textarea::make('answer')
                        ->label('Antwort')
                        ->required()
                        ->rows(3)
                        ->maxLength(5000),
                ])
                ->columnSpanFull(),
        ];

        $scoreFields = $competencies
            ->map(function ($competency): Select {
                return Select::make("scores.{$competency->id}")
                    ->label(sprintf('%s (Gewichtung %d)', $competency->name, max(1, (int) $competency->weight)))
                    ->options([
                        1 => '1 - Schwach',
                        2 => '2 - Ausreichend',
                        3 => '3 - Gut',
                        4 => '4 - Sehr gut',
                        5 => '5 - Exzellent',
                    ])
                    ->required()
                    ->native(false);
            })
            ->all();

        $schema = array_merge($schema, $scoreFields);

        $schema[] = Textarea::make('rationale')
            ->label('Begruendung')
            ->rows(5)
            ->required()
            ->maxLength(5000)
            ->columnSpanFull();

        return $schema;
    }

    /**
     * @return array<string, string>
     */
    protected function getEvaluationStageOptions(): array
    {
        return [
            ApplicationStatus::Reviewed->value => ApplicationStatus::Reviewed->label(),
            ApplicationStatus::Interview->value => ApplicationStatus::Interview->label(),
        ];
    }

    protected function resolveDefaultEvaluationStage(Application $application): string
    {
        $currentStatus = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        if (in_array($currentStatus, array_keys($this->getEvaluationStageOptions()), true)) {
            return $currentStatus;
        }

        return ApplicationStatus::Reviewed->value;
    }

    protected function getStageRubricPrompt(Application $application, string $stage): ?string
    {
        $kits = $application->campaign?->getEvaluationStageKitsOrDefault()
            ?? $application->campaign()->first()?->getEvaluationStageKitsOrDefault()
            ?? [];
        $prompt = trim((string) ($kits[$stage]['rubric_prompt'] ?? ''));

        return $prompt !== '' ? $prompt : null;
    }

    /**
     * @return list<string>
     */
    protected function getStageQuestions(Application $application, string $stage): array
    {
        $kits = $application->campaign?->getEvaluationStageKitsOrDefault()
            ?? $application->campaign()->first()?->getEvaluationStageKitsOrDefault()
            ?? [];

        return collect($kits[$stage]['questions'] ?? [])
            ->map(function (mixed $question): string {
                if (is_array($question)) {
                    return trim((string) ($question['question'] ?? ''));
                }

                return trim((string) $question);
            })
            ->filter(fn (string $question): bool => $question !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $existingRows
     * @return array<int, array{question: string, answer: string}>
     */
    protected function buildQuestionResponseRowsForStage(
        Application $application,
        string $stage,
        array $existingRows = []
    ): array {
        $existingByQuestion = collect($existingRows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapWithKeys(function (array $row): array {
                $question = trim((string) ($row['question'] ?? ''));
                $answer = trim((string) ($row['answer'] ?? ''));

                if ($question === '') {
                    return [];
                }

                return [$question => $answer];
            })
            ->all();

        return collect($this->getStageQuestions($application, $stage))
            ->map(fn (string $question): array => [
                'question' => $question,
                'answer' => (string) ($existingByQuestion[$question] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{question: string, answer: string}>
     */
    protected function normalizeQuestionResponses(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): array {
                return [
                    'question' => trim((string) ($row['question'] ?? '')),
                    'answer' => trim((string) ($row['answer'] ?? '')),
                ];
            })
            ->filter(fn (array $row): bool => $row['question'] !== '')
            ->values()
            ->all();
    }
}
