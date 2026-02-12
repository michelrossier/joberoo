<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'source',
        'source_medium',
        'source_campaign',
        'referrer_url',
        'session_id',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
