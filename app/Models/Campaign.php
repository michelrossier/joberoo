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
}
