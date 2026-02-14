<?php

namespace App\Support\MailTracking;

use App\Models\Application;
use App\Models\EmailMessage;
use App\Models\EmailMessageEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Throwable;

class PostmarkWebhookProcessor
{
    public function process(array $payload): void
    {
        try {
            $event = $this->resolveEvent($payload);

            if (! $event) {
                return;
            }

            $providerMessageId = $this->normalizeMessageId(
                Arr::get($payload, 'MessageID')
                ?? Arr::get($payload, 'MessageId')
            );

            $recipientEmail = $this->normalizeEmail(
                Arr::get($payload, 'Recipient')
                ?? Arr::get($payload, 'Email')
            );

            $occurredAt = $this->resolveOccurredAt($payload);
            $fingerprint = $this->buildFingerprint($event, $providerMessageId, $recipientEmail, $occurredAt, $payload);

            if (EmailMessageEvent::query()->where('fingerprint', $fingerprint)->exists()) {
                return;
            }

            $metadata = Arr::get($payload, 'Metadata');
            $metadata = is_array($metadata) ? $metadata : [];

            $emailMessage = $this->resolveEmailMessage($providerMessageId, $recipientEmail, $metadata);

            EmailMessageEvent::query()->create([
                'email_message_id' => $emailMessage?->id,
                'event' => $event,
                'fingerprint' => $fingerprint,
                'provider_message_id' => $providerMessageId,
                'recipient_email' => $recipientEmail,
                'occurred_at' => $occurredAt,
                'payload' => $payload,
            ]);

            if (! $emailMessage) {
                return;
            }

            $eventTime = $occurredAt ?? now();
            $shouldRecordActivity = $this->applyEventToMessage($emailMessage, $event, $eventTime, $payload);

            if ($shouldRecordActivity) {
                $this->recordApplicationActivity($emailMessage, $event, $eventTime, $payload, $recipientEmail);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function resolveEvent(array $payload): ?string
    {
        $recordType = strtolower((string) Arr::get($payload, 'RecordType', ''));

        return match ($recordType) {
            'delivery' => EmailMessageEvent::EVENT_DELIVERY,
            'open' => EmailMessageEvent::EVENT_OPEN,
            'bounce' => EmailMessageEvent::EVENT_BOUNCE,
            'spamcomplaint' => EmailMessageEvent::EVENT_SPAM_COMPLAINT,
            default => null,
        };
    }

    private function resolveOccurredAt(array $payload): ?CarbonInterface
    {
        $candidates = [
            Arr::get($payload, 'DeliveredAt'),
            Arr::get($payload, 'BouncedAt'),
            Arr::get($payload, 'ReportedAt'),
            Arr::get($payload, 'FirstOpen'),
            Arr::get($payload, 'ReceivedAt'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function buildFingerprint(
        string $event,
        ?string $providerMessageId,
        ?string $recipientEmail,
        ?CarbonInterface $occurredAt,
        array $payload,
    ): string {
        $timestamp = $occurredAt?->toIso8601String();

        return hash('sha256', implode('|', [
            $event,
            $providerMessageId ?? '-',
            $recipientEmail ?? '-',
            $timestamp ?? '-',
            (string) Arr::get($payload, 'ID', ''),
            (string) Arr::get($payload, 'MessageStream', ''),
        ]));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveEmailMessage(?string $providerMessageId, ?string $recipientEmail, array $metadata): ?EmailMessage
    {
        if (filled($providerMessageId)) {
            $message = EmailMessage::query()
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($message) {
                return $message;
            }
        }

        $notificationId = isset($metadata['notification_id'])
            ? trim((string) $metadata['notification_id'])
            : null;

        if (filled($notificationId) && filled($recipientEmail)) {
            $message = EmailMessage::query()
                ->where('notification_id', $notificationId)
                ->where('recipient_email', $recipientEmail)
                ->latest('id')
                ->first();

            if ($message) {
                if (! filled($message->provider_message_id) && filled($providerMessageId)) {
                    $message->provider_message_id = $providerMessageId;
                    $message->save();
                }

                return $message;
            }
        }

        $applicationId = isset($metadata['application_id'])
            ? (int) $metadata['application_id']
            : null;

        if ($applicationId && filled($recipientEmail)) {
            $message = EmailMessage::query()
                ->where('application_id', $applicationId)
                ->where('recipient_email', $recipientEmail)
                ->latest('id')
                ->first();

            if ($message) {
                if (! filled($message->provider_message_id) && filled($providerMessageId)) {
                    $message->provider_message_id = $providerMessageId;
                    $message->save();
                }

                return $message;
            }
        }

        return null;
    }

    private function applyEventToMessage(
        EmailMessage $emailMessage,
        string $event,
        CarbonInterface $eventTime,
        array $payload,
    ): bool {
        $previousState = [
            'delivered_at' => $emailMessage->delivered_at,
            'first_opened_at' => $emailMessage->first_opened_at,
            'bounced_at' => $emailMessage->bounced_at,
            'spam_reported_at' => $emailMessage->spam_reported_at,
        ];

        $newStatus = $this->statusForEvent($event);

        if ($this->statusRank($newStatus) > $this->statusRank((string) $emailMessage->status)) {
            $emailMessage->status = $newStatus;
        }

        if (! $emailMessage->last_event_at || $eventTime->greaterThan($emailMessage->last_event_at)) {
            $emailMessage->last_event_at = $eventTime;
        }

        switch ($event) {
            case EmailMessageEvent::EVENT_DELIVERY:
                $emailMessage->delivered_at = $emailMessage->delivered_at ?? $eventTime;
                break;

            case EmailMessageEvent::EVENT_OPEN:
                if (! $emailMessage->first_opened_at || $eventTime->lessThan($emailMessage->first_opened_at)) {
                    $emailMessage->first_opened_at = $eventTime;
                }

                if (! $emailMessage->last_opened_at || $eventTime->greaterThan($emailMessage->last_opened_at)) {
                    $emailMessage->last_opened_at = $eventTime;
                }
                break;

            case EmailMessageEvent::EVENT_BOUNCE:
                $emailMessage->bounced_at = $emailMessage->bounced_at ?? $eventTime;
                $emailMessage->failure_reason = $this->resolveBounceReason($payload);
                break;

            case EmailMessageEvent::EVENT_SPAM_COMPLAINT:
                $emailMessage->spam_reported_at = $emailMessage->spam_reported_at ?? $eventTime;
                break;
        }

        $emailMessage->save();

        return match ($event) {
            EmailMessageEvent::EVENT_DELIVERY => $previousState['delivered_at'] === null,
            EmailMessageEvent::EVENT_OPEN => $previousState['first_opened_at'] === null,
            EmailMessageEvent::EVENT_BOUNCE => $previousState['bounced_at'] === null,
            EmailMessageEvent::EVENT_SPAM_COMPLAINT => $previousState['spam_reported_at'] === null,
            default => false,
        };
    }

    private function resolveBounceReason(array $payload): ?string
    {
        $detail = trim((string) Arr::get($payload, 'Description', Arr::get($payload, 'Details', '')));

        return $detail !== '' ? $detail : null;
    }

    private function statusForEvent(string $event): string
    {
        return match ($event) {
            EmailMessageEvent::EVENT_DELIVERY => EmailMessage::STATUS_DELIVERED,
            EmailMessageEvent::EVENT_OPEN => EmailMessage::STATUS_OPENED,
            EmailMessageEvent::EVENT_BOUNCE => EmailMessage::STATUS_BOUNCED,
            EmailMessageEvent::EVENT_SPAM_COMPLAINT => EmailMessage::STATUS_SPAM_COMPLAINT,
            default => EmailMessage::STATUS_SENT,
        };
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            EmailMessage::STATUS_SPAM_COMPLAINT,
            EmailMessage::STATUS_BOUNCED => 4,
            EmailMessage::STATUS_OPENED => 3,
            EmailMessage::STATUS_DELIVERED => 2,
            EmailMessage::STATUS_SENT => 1,
            EmailMessage::STATUS_FAILED => 0,
            default => 0,
        };
    }

    private function recordApplicationActivity(
        EmailMessage $emailMessage,
        string $event,
        CarbonInterface $eventTime,
        array $payload,
        ?string $recipientEmail,
    ): void {
        if (! filled($emailMessage->application_id)) {
            return;
        }

        $application = $emailMessage->relationLoaded('application')
            ? $emailMessage->application
            : Application::query()->find($emailMessage->application_id);

        if (! $application) {
            return;
        }

        $type = match ($event) {
            EmailMessageEvent::EVENT_DELIVERY => 'email_delivered',
            EmailMessageEvent::EVENT_OPEN => 'email_opened',
            EmailMessageEvent::EVENT_BOUNCE => 'email_bounced',
            EmailMessageEvent::EVENT_SPAM_COMPLAINT => 'email_spam_reported',
            default => null,
        };

        if (! $type) {
            return;
        }

        $meta = array_filter([
            'recipient' => $recipientEmail ?? $emailMessage->recipient_email,
            'subject' => $emailMessage->subject,
            'provider_message_id' => $emailMessage->provider_message_id,
            'event_at' => $eventTime->toDateTimeString(),
            'details' => $this->resolveBounceReason($payload),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $application->recordActivity($type, null, $meta);
    }

    private function normalizeMessageId(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value, " <>\t\n\r\0\x0B");
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtolower(trim($value));
    }
}
