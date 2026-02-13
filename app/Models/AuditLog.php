<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public const EVENT_AUTH_LOGIN = 'auth.login';

    public const EVENT_AUTH_LOGOUT = 'auth.logout';

    public const EVENT_CAMPAIGN_CREATED = 'campaign.created';

    public const EVENT_CAMPAIGN_UPDATED = 'campaign.updated';

    public const EVENT_CAMPAIGN_DELETED = 'campaign.deleted';

    public const EVENT_APPLICATION_CREATED = 'application.created';

    public const EVENT_APPLICATION_UPDATED = 'application.updated';

    public const EVENT_APPLICATION_DELETED = 'application.deleted';

    protected $fillable = [
        'actor_id',
        'organization_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'changes',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'context' => 'array',
        ];
    }

    public static function eventOptions(): array
    {
        return collect(static::allEventKeys())
            ->mapWithKeys(fn (string $event): array => [$event => static::labelForEvent($event)])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function allEventKeys(): array
    {
        return [
            static::EVENT_AUTH_LOGIN,
            static::EVENT_AUTH_LOGOUT,
            static::EVENT_CAMPAIGN_CREATED,
            static::EVENT_CAMPAIGN_UPDATED,
            static::EVENT_CAMPAIGN_DELETED,
            static::EVENT_APPLICATION_CREATED,
            static::EVENT_APPLICATION_UPDATED,
            static::EVENT_APPLICATION_DELETED,
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function labelForEvent(string $event): string
    {
        return match ($event) {
            static::EVENT_AUTH_LOGIN => 'Login',
            static::EVENT_AUTH_LOGOUT => 'Logout',
            static::EVENT_CAMPAIGN_CREATED => 'Job erstellt',
            static::EVENT_CAMPAIGN_UPDATED => 'Job aktualisiert',
            static::EVENT_CAMPAIGN_DELETED => 'Job geloescht',
            static::EVENT_APPLICATION_CREATED => 'Bewerbung erstellt',
            static::EVENT_APPLICATION_UPDATED => 'Bewerbung aktualisiert',
            static::EVENT_APPLICATION_DELETED => 'Bewerbung geloescht',
            default => $event,
        };
    }

    public function changesSummary(int $maxFields = 3): string
    {
        $changeSet = $this->getAttribute('changes');

        if (! is_array($changeSet) || $changeSet === []) {
            return '-';
        }

        $allParts = [];

        foreach ($changeSet as $field => $change) {
            if (! is_array($change) || (! array_key_exists('before', $change) && ! array_key_exists('after', $change))) {
                continue;
            }

            $before = $this->formatChangeValue($change['before'] ?? null);
            $after = $this->formatChangeValue($change['after'] ?? null);
            $allParts[] = sprintf('%s: %s -> %s', static::fieldLabel((string) $field), $before, $after);
        }

        if ($allParts === []) {
            return '-';
        }

        $visibleParts = array_slice($allParts, 0, $maxFields);
        $remaining = count($allParts) - count($visibleParts);

        if ($remaining > 0) {
            $visibleParts[] = "+{$remaining} weitere";
        }

        return implode(' | ', $visibleParts);
    }

    public function getChangesSummaryAttribute(): string
    {
        return $this->changesSummary();
    }

    protected static function fieldLabel(string $field): string
    {
        return match ($field) {
            'title' => 'Titel',
            'status' => 'Status',
            'description' => 'Beschreibung',
            'assigned_user_id' => 'Zustaendig',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'email' => 'E-Mail',
            default => str_replace('_', ' ', $field),
        };
    }

    protected function formatChangeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Ja' : 'Nein';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return (string) $value;
    }
}
