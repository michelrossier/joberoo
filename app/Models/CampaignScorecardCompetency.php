<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignScorecardCompetency extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'weight',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'position' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
