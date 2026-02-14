<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'assigned_user_id',
        'status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'linkedin_url',
        'portfolio_url',
        'cover_letter_text',
        'source',
        'source_medium',
        'source_campaign',
        'referrer_url',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ApplicationActivity::class)->latest('created_at');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ApplicationEvaluation::class)->latest('created_at');
    }

    public function resumeAttachment(): HasOne
    {
        return $this->hasOne(Attachment::class)->where('type', 'resume');
    }

    public function coverLetterAttachment(): HasOne
    {
        return $this->hasOne(Attachment::class)->where('type', 'cover_letter');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function recordActivity(
        string $type,
        ?string $note = null,
        array $meta = [],
        ?int $actorId = null,
    ): ApplicationActivity {
        return $this->activities()->create([
            'actor_id' => $actorId,
            'type' => $type,
            'note' => $note,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    public function getSourceLabelAttribute(): string
    {
        return filled($this->source) ? $this->source : 'Direkt';
    }

    public static function statusRequiresEvaluation(string $status): bool
    {
        return in_array($status, [
            ApplicationStatus::Accepted->value,
            ApplicationStatus::Dismissed->value,
        ], true);
    }

    /**
     * @return Collection<int, CampaignScorecardCompetency>
     */
    public function getScorecardCompetencies(): Collection
    {
        $campaign = $this->relationLoaded('campaign')
            ? $this->campaign
            : $this->campaign()->with('scorecardCompetencies')->first();

        if (! $campaign) {
            return collect();
        }

        if ($campaign->relationLoaded('scorecardCompetencies')) {
            return $campaign->scorecardCompetencies;
        }

        return $campaign->scorecardCompetencies()->get();
    }

    public function hasCompleteEvaluation(): bool
    {
        $competencies = $this->getScorecardCompetencies();

        if ($competencies->isEmpty()) {
            return false;
        }

        $stageKits = $this->campaign?->getEvaluationStageKitsOrDefault()
            ?? $this->campaign()->first()?->getEvaluationStageKitsOrDefault()
            ?? [];

        $evaluations = $this->relationLoaded('evaluations')
            ? $this->evaluations
            : $this->evaluations()->get();

        return $evaluations->contains(function (ApplicationEvaluation $evaluation) use ($competencies, $stageKits): bool {
            $stage = (string) ($evaluation->stage ?? '');
            $requiredQuestions = collect($stageKits[$stage]['questions'] ?? [])
                ->map(fn (mixed $question): string => trim((string) $question))
                ->filter(fn (string $question): bool => $question !== '')
                ->values()
                ->all();

            return $evaluation->isCompleteForCompetencies($competencies, $requiredQuestions);
        });
    }
}
