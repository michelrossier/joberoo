<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_OPENED = 'opened';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_SPAM_COMPLAINT = 'spam_complaint';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'campaign_id',
        'application_id',
        'actor_id',
        'notification_id',
        'notification_type',
        'recipient_email',
        'recipient_name',
        'subject',
        'mailer',
        'message_stream',
        'provider',
        'provider_message_id',
        'status',
        'sent_at',
        'delivered_at',
        'first_opened_at',
        'last_opened_at',
        'bounced_at',
        'spam_reported_at',
        'last_event_at',
        'failure_reason',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'bounced_at' => 'datetime',
            'spam_reported_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmailMessageEvent::class)->latest('occurred_at')->latest('id');
    }

    /**
     * @return list<string>
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_OPENED,
            self::STATUS_BOUNCED,
            self::STATUS_SPAM_COMPLAINT,
            self::STATUS_FAILED,
        ];
    }

    public static function statusOptions(): array
    {
        return collect(self::allStatuses())
            ->mapWithKeys(fn (string $status): array => [$status => self::labelForStatus($status)])
            ->all();
    }

    public static function labelForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_SENT => 'Gesendet',
            self::STATUS_DELIVERED => 'Zugestellt',
            self::STATUS_OPENED => 'Geoeffnet',
            self::STATUS_BOUNCED => 'Bounce',
            self::STATUS_SPAM_COMPLAINT => 'Spam-Beschwerde',
            self::STATUS_FAILED => 'Fehlgeschlagen',
            default => $status,
        };
    }

    public static function colorForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_SENT => 'gray',
            self::STATUS_DELIVERED => 'success',
            self::STATUS_OPENED => 'info',
            self::STATUS_BOUNCED => 'danger',
            self::STATUS_SPAM_COMPLAINT => 'danger',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::labelForStatus((string) $this->status);
    }
}
