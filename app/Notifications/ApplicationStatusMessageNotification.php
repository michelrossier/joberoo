<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $messageHtml,
        public Application $application,
        public ?int $actorId = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->application->relationLoaded('campaign')
            ? $this->application->campaign
            : $this->application->campaign()->first();

        $mailMessage = (new MailMessage)
            ->subject($this->subjectLine)
            ->view('emails.applications.status-message', [
                'messageHtml' => $this->messageHtml,
            ])
            ->tag('application-status')
            ->metadata('application_id', (string) $this->application->id)
            ->metadata('campaign_id', (string) ($campaign?->id ?? $this->application->campaign_id));

        if ($this->id) {
            $mailMessage->metadata('notification_id', (string) $this->id);
        }

        if ($campaign?->organization_id) {
            $mailMessage->metadata('organization_id', (string) $campaign->organization_id);
        }

        if ($this->actorId) {
            $mailMessage->metadata('actor_id', (string) $this->actorId);
        }

        return $mailMessage;
    }
}
