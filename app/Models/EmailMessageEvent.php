<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessageEvent extends Model
{
    use HasFactory;

    public const EVENT_DELIVERY = 'delivery';

    public const EVENT_OPEN = 'open';

    public const EVENT_BOUNCE = 'bounce';

    public const EVENT_SPAM_COMPLAINT = 'spam_complaint';

    protected $fillable = [
        'email_message_id',
        'event',
        'fingerprint',
        'provider_message_id',
        'recipient_email',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    /**
     * @return list<string>
     */
    public static function allEvents(): array
    {
        return [
            self::EVENT_DELIVERY,
            self::EVENT_OPEN,
            self::EVENT_BOUNCE,
            self::EVENT_SPAM_COMPLAINT,
        ];
    }

    public static function eventOptions(): array
    {
        return collect(self::allEvents())
            ->mapWithKeys(fn (string $event): array => [$event => self::labelForEvent($event)])
            ->all();
    }

    public static function labelForEvent(string $event): string
    {
        return match ($event) {
            self::EVENT_DELIVERY => 'Zustellung',
            self::EVENT_OPEN => 'Oeffnung',
            self::EVENT_BOUNCE => 'Bounce',
            self::EVENT_SPAM_COMPLAINT => 'Spam-Beschwerde',
            default => $event,
        };
    }
}
