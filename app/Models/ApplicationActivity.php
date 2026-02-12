<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'actor_id',
        'type',
        'note',
        'meta',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            'submitted' => 'Bewerbung eingegangen',
            'status_changed' => 'Status geaendert',
            'applicant_message_sent' => 'Bewerber-Nachricht gesendet',
            'assignment_changed' => 'Zustaendigkeit geaendert',
            'note_added' => 'Notiz hinzugefuegt',
            'attachment_downloaded' => 'Anhang heruntergeladen',
            default => $type,
        };
    }

    public function getDetailsAttribute(): ?string
    {
        if (! is_array($this->meta) || $this->meta === []) {
            return null;
        }

        return collect($this->meta)
            ->map(function ($value, $key): string {
                $value = is_scalar($value) ? (string) $value : json_encode($value);
                $label = match ((string) $key) {
                    'source' => 'Quelle',
                    'medium' => 'Medium',
                    'campaign' => 'Job',
                    'from' => 'Von',
                    'to' => 'Nach',
                    'recipient' => 'Empfaenger',
                    'subject' => 'Betreff',
                    'template' => 'Vorlage',
                    'status_to' => 'Status (Label)',
                    'status_to_value' => 'Status (Wert)',
                    'from_value' => 'Von (Wert)',
                    'to_value' => 'Nach (Wert)',
                    default => (string) $key,
                };

                return "{$label}: {$value}";
            })
            ->implode(' | ');
    }
}
