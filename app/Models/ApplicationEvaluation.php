<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ApplicationEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'evaluator_id',
        'stage',
        'scores',
        'question_responses',
        'rationale',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'question_responses' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * @param  Collection<int, CampaignScorecardCompetency>  $competencies
     * @param  list<string>  $requiredQuestions
     */
    public function isCompleteForCompetencies(Collection $competencies, array $requiredQuestions = []): bool
    {
        if (blank(trim((string) $this->rationale))) {
            return false;
        }

        $scores = is_array($this->scores) ? $this->scores : [];

        foreach ($competencies as $competency) {
            $score = $scores[(string) $competency->id]
                ?? $scores[$competency->id]
                ?? null;

            if (! is_numeric($score)) {
                return false;
            }

            $score = (float) $score;

            if ($score < 1 || $score > 5) {
                return false;
            }
        }

        if ($requiredQuestions !== []) {
            $responses = collect(is_array($this->question_responses) ? $this->question_responses : [])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->mapWithKeys(function (array $row): array {
                    $question = trim((string) ($row['question'] ?? ''));
                    $answer = trim((string) ($row['answer'] ?? ''));

                    if ($question === '') {
                        return [];
                    }

                    return [$question => $answer];
                });

            foreach ($requiredQuestions as $question) {
                if (blank(trim((string) ($responses[$question] ?? '')))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, CampaignScorecardCompetency>  $competencies
     */
    public function weightedScore(Collection $competencies): ?float
    {
        $scores = is_array($this->scores) ? $this->scores : [];
        $weightedTotal = 0.0;
        $weightSum = 0.0;

        foreach ($competencies as $competency) {
            $score = $scores[(string) $competency->id]
                ?? $scores[$competency->id]
                ?? null;

            if (! is_numeric($score)) {
                continue;
            }

            $weight = max(1, (int) $competency->weight);
            $weightedTotal += ((float) $score) * $weight;
            $weightSum += $weight;
        }

        if ($weightSum <= 0) {
            return null;
        }

        return round($weightedTotal / $weightSum, 2);
    }
}
