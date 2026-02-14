<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'slug',
        'views_count',
        'status',
        'title',
        'subtitle',
        'description',
        'evaluation_stage_kits',
        'location',
        'employment_type',
        'salary_range',
        'hero_image_path',
        'cta_text',
        'primary_color',
    ];

    protected $casts = [
        'status' => CampaignStatus::class,
        'views_count' => 'integer',
        'evaluation_stage_kits' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CampaignVisit::class);
    }

    public function scorecardCompetencies(): HasMany
    {
        return $this->hasMany(CampaignScorecardCompetency::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * @return array<string, array{rubric_prompt: string, questions: array<int, string>}>
     */
    public static function defaultEvaluationStageKits(): array
    {
        return [
            'reviewed' => [
                'rubric_prompt' => 'Fokus auf Grundqualifikation, Lernpotenzial und Relevanz fuer die Rolle.',
                'questions' => [
                    'Welche Staerken sehen Sie bezogen auf die Kernanforderungen?',
                    'Wo sehen Sie moegliche Risiken oder offene Punkte?',
                    'Welche Evidenz aus Unterlagen/Screening stuetzt Ihre Einschaetzung?',
                ],
            ],
            'interview' => [
                'rubric_prompt' => 'Fokus auf Verhaltensbeispiele, Problemlosung und Team-Fit aus Interviewbeobachtungen.',
                'questions' => [
                    'Welche konkreten Interview-Beispiele zeigen Kompetenz fuer die Rolle?',
                    'Wie beurteilen Sie Kommunikation und Zusammenarbeit?',
                    'Welche Empfehlung geben Sie fuer den naechsten Schritt und warum?',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{rubric_prompt: string, questions: array<int, string>}>
     */
    public function getEvaluationStageKitsOrDefault(): array
    {
        $kits = $this->evaluation_stage_kits;
        $defaults = static::defaultEvaluationStageKits();

        if (! is_array($kits) || $kits === []) {
            return $defaults;
        }

        $normalized = [];

        foreach (['reviewed', 'interview'] as $stage) {
            $kit = $kits[$stage] ?? [];
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
