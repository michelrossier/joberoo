<?php

namespace App\Support\MailTracking;

use App\Models\Application;
use App\Models\EmailMessage;
use App\Notifications\ApplicationStatusMessageNotification;
use App\Notifications\NewApplicationNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Throwable;

class OutboundMailLogger
{
    public function handleSent(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        try {
            [$recipientEmail, $recipientName] = $this->resolveRecipient($event->notifiable, $event->notification);

            if (! filled($recipientEmail)) {
                return;
            }

            $providerMessageId = $this->normalizeMessageId($this->extractMessageId($event->response));
            $notificationId = $event->notification->id ? (string) $event->notification->id : null;
            $subject = $this->resolveSubject($event->response, $event->notifiable, $event->notification);
            $context = $this->resolveDomainContext($event->notification);
            $sentAt = now();
            $messageStream = config('mail.mailers.postmark.message_stream_id');

            $payload = [
                'organization_id' => $context['organization_id'],
                'campaign_id' => $context['campaign_id'],
                'application_id' => $context['application_id'],
                'actor_id' => $context['actor_id'],
                'notification_id' => $notificationId,
                'notification_type' => $event->notification::class,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
                'mailer' => $this->resolveMailer($event->notifiable, $event->notification),
                'message_stream' => filled($messageStream) ? (string) $messageStream : null,
                'provider' => 'postmark',
                'provider_message_id' => $providerMessageId,
                'status' => EmailMessage::STATUS_SENT,
                'sent_at' => $sentAt,
                'last_event_at' => $sentAt,
                'context' => [
                    'channel' => $event->channel,
                    'notification_type' => $event->notification::class,
                    'notifiable_type' => is_object($event->notifiable) ? $event->notifiable::class : gettype($event->notifiable),
                    'notifiable_id' => method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : null,
                    'mail_default' => config('mail.default'),
                ],
            ];

            $existing = $this->findExistingMessage($providerMessageId, $notificationId, $recipientEmail);

            if ($existing) {
                $existing->fill(array_filter($payload, fn (mixed $value): bool => $value !== null));
                $existing->sent_at = $existing->sent_at ?? $sentAt;
                $existing->last_event_at = $sentAt;

                if (! in_array($existing->status, [
                    EmailMessage::STATUS_DELIVERED,
                    EmailMessage::STATUS_OPENED,
                    EmailMessage::STATUS_BOUNCED,
                    EmailMessage::STATUS_SPAM_COMPLAINT,
                ], true)) {
                    $existing->status = EmailMessage::STATUS_SENT;
                }

                $existing->save();

                return;
            }

            EmailMessage::query()->create($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        try {
            [$recipientEmail, $recipientName] = $this->resolveRecipient($event->notifiable, $event->notification);

            if (! filled($recipientEmail)) {
                return;
            }

            $notificationId = $event->notification->id ? (string) $event->notification->id : null;
            $context = $this->resolveDomainContext($event->notification);
            $failedAt = now();
            $reason = $this->resolveFailureReason($event->data ?? []);
            $messageStream = config('mail.mailers.postmark.message_stream_id');

            $existing = $this->findExistingMessage(null, $notificationId, $recipientEmail);

            if ($existing) {
                $existing->fill([
                    'organization_id' => $existing->organization_id ?? $context['organization_id'],
                    'campaign_id' => $existing->campaign_id ?? $context['campaign_id'],
                    'application_id' => $existing->application_id ?? $context['application_id'],
                    'actor_id' => $existing->actor_id ?? $context['actor_id'],
                    'notification_type' => $existing->notification_type ?? $event->notification::class,
                    'recipient_name' => $existing->recipient_name ?? $recipientName,
                    'failure_reason' => $reason,
                    'last_event_at' => $failedAt,
                ]);

                if (! in_array($existing->status, [EmailMessage::STATUS_BOUNCED, EmailMessage::STATUS_SPAM_COMPLAINT], true)) {
                    $existing->status = EmailMessage::STATUS_FAILED;
                }

                $existing->save();

                return;
            }

            EmailMessage::query()->create([
                'organization_id' => $context['organization_id'],
                'campaign_id' => $context['campaign_id'],
                'application_id' => $context['application_id'],
                'actor_id' => $context['actor_id'],
                'notification_id' => $notificationId,
                'notification_type' => $event->notification::class,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => null,
                'mailer' => $this->resolveMailer($event->notifiable, $event->notification),
                'message_stream' => filled($messageStream) ? (string) $messageStream : null,
                'provider' => 'postmark',
                'provider_message_id' => null,
                'status' => EmailMessage::STATUS_FAILED,
                'failure_reason' => $reason,
                'last_event_at' => $failedAt,
                'context' => [
                    'channel' => $event->channel,
                    'notification_type' => $event->notification::class,
                    'notifiable_type' => is_object($event->notifiable) ? $event->notifiable::class : gettype($event->notifiable),
                    'notifiable_id' => method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : null,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function resolveFailureReason(array $data): ?string
    {
        $exception = $data['exception'] ?? null;

        if ($exception instanceof Throwable) {
            return $exception->getMessage();
        }

        if (is_string($exception)) {
            return $exception;
        }

        return null;
    }

    private function extractMessageId(mixed $response): ?string
    {
        if (! is_object($response) || ! method_exists($response, 'getMessageId')) {
            return null;
        }

        $messageId = $response->getMessageId();

        return is_string($messageId) ? $messageId : null;
    }

    private function normalizeMessageId(?string $messageId): ?string
    {
        if (! filled($messageId)) {
            return null;
        }

        return trim((string) $messageId, " <>\t\n\r\0\x0B");
    }

    private function resolveSubject(mixed $response, mixed $notifiable, object $notification): ?string
    {
        if (is_object($response) && method_exists($response, 'getOriginalMessage')) {
            $original = $response->getOriginalMessage();

            if (is_object($original) && method_exists($original, 'getSubject')) {
                $subject = $original->getSubject();

                if (is_string($subject) && filled(trim($subject))) {
                    return trim($subject);
                }
            }
        }

        try {
            if (method_exists($notification, 'toMail')) {
                $mailMessage = $notification->toMail($notifiable);

                if ($mailMessage instanceof MailMessage) {
                    $subject = trim((string) ($mailMessage->subject ?? ''));

                    return $subject !== '' ? $subject : null;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function resolveMailer(mixed $notifiable, object $notification): string
    {
        try {
            if (method_exists($notification, 'toMail')) {
                $mailMessage = $notification->toMail($notifiable);

                if ($mailMessage instanceof MailMessage && filled($mailMessage->mailer)) {
                    return (string) $mailMessage->mailer;
                }
            }
        } catch (Throwable) {
            // Fall back to configured default when notification rendering fails.
        }

        return (string) config('mail.default', 'postmark');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveRecipient(mixed $notifiable, object $notification): array
    {
        $route = null;

        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            try {
                $route = $notifiable->routeNotificationFor('mail', $notification);
            } catch (\ArgumentCountError) {
                $route = $notifiable->routeNotificationFor('mail');
            }
        }

        if (is_string($route)) {
            return [$this->normalizeEmail($route), $this->resolveNotifiableName($notifiable)];
        }

        if (is_array($route) && $route !== []) {
            $firstKey = array_key_first($route);

            if ($firstKey === null) {
                return [null, null];
            }

            if (is_int($firstKey)) {
                $first = $route[$firstKey];

                if (is_string($first)) {
                    return [$this->normalizeEmail($first), $this->resolveNotifiableName($notifiable)];
                }

                if (is_object($first) && property_exists($first, 'email')) {
                    return [
                        $this->normalizeEmail((string) $first->email),
                        property_exists($first, 'name') ? (string) $first->name : null,
                    ];
                }
            }

            if (is_string($firstKey)) {
                return [
                    $this->normalizeEmail($firstKey),
                    is_scalar($route[$firstKey]) ? (string) $route[$firstKey] : null,
                ];
            }
        }

        if (is_object($notifiable) && property_exists($notifiable, 'email')) {
            return [
                $this->normalizeEmail((string) $notifiable->email),
                $this->resolveNotifiableName($notifiable),
            ];
        }

        return [null, null];
    }

    private function resolveNotifiableName(mixed $notifiable): ?string
    {
        if (is_object($notifiable) && property_exists($notifiable, 'name') && is_scalar($notifiable->name)) {
            $name = trim((string) $notifiable->name);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function normalizeEmail(string $email): ?string
    {
        $normalized = strtolower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{organization_id: ?int, campaign_id: ?int, application_id: ?int, actor_id: ?int}
     */
    private function resolveDomainContext(object $notification): array
    {
        $application = null;
        $actorId = null;

        if ($notification instanceof ApplicationStatusMessageNotification) {
            $application = $this->resolveApplicationFromStatusNotification($notification);
            $actorId = $this->resolveActorIdFromStatusNotification($notification);
        } elseif ($notification instanceof NewApplicationNotification) {
            $application = $notification->application;
        } elseif (property_exists($notification, 'application') && $notification->application instanceof Application) {
            $application = $notification->application;
        }

        if (! $application instanceof Application) {
            return [
                'organization_id' => null,
                'campaign_id' => null,
                'application_id' => null,
                'actor_id' => $actorId,
            ];
        }

        $campaign = $application->relationLoaded('campaign')
            ? $application->campaign
            : $application->campaign()->first();

        return [
            'organization_id' => $campaign?->organization_id,
            'campaign_id' => $campaign?->id ?? (filled($application->campaign_id) ? (int) $application->campaign_id : null),
            'application_id' => (int) $application->id,
            'actor_id' => $actorId,
        ];
    }

    private function findExistingMessage(?string $providerMessageId, ?string $notificationId, string $recipientEmail): ?EmailMessage
    {
        if (filled($providerMessageId)) {
            $message = EmailMessage::query()
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($message) {
                return $message;
            }
        }

        if (! filled($notificationId)) {
            return null;
        }

        return EmailMessage::query()
            ->where('notification_id', $notificationId)
            ->where('recipient_email', $recipientEmail)
            ->latest('id')
            ->first();
    }

    private function resolveApplicationFromStatusNotification(
        ApplicationStatusMessageNotification $notification,
    ): ?Application {
        if (property_exists($notification, 'application') && isset($notification->application) && $notification->application instanceof Application) {
            return $notification->application;
        }

        $applicationId = property_exists($notification, 'applicationId')
            ? ($notification->applicationId ?? null)
            : null;

        if (! filled($applicationId)) {
            return null;
        }

        return Application::query()
            ->with('campaign')
            ->find((int) $applicationId);
    }

    private function resolveActorIdFromStatusNotification(
        ApplicationStatusMessageNotification $notification,
    ): ?int {
        if (! isset($notification->actorId)) {
            return null;
        }

        $actorId = $notification->actorId;

        return filled($actorId) ? (int) $actorId : null;
    }
}
