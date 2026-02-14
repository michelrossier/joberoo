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

    public ?int $applicationId = null;

    public function __construct(
        public string $subjectLine,
        public string $messageHtml,
        public Application $application,
        public ?int $actorId = null,
    ) {
        $this->applicationId = $application->id;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->resolveApplication();
        $actorId = $this->resolveActorId();
        $campaign = $application
            ? ($application->relationLoaded('campaign')
                ? $application->campaign
                : $application->campaign()->first())
            : null;

        $mailMessage = (new MailMessage)
            ->subject($this->subjectLine)
            ->view('emails.applications.status-message', [
                'messageHtml' => $this->messageHtml,
            ])
            ->tag('application-status');

        if ($application) {
            $mailMessage
                ->metadata('application_id', (string) $application->id)
                ->metadata('campaign_id', (string) ($campaign?->id ?? $application->campaign_id));
        }

        if ($this->id) {
            $mailMessage->metadata('notification_id', (string) $this->id);
        }

        if ($campaign?->organization_id) {
            $mailMessage->metadata('organization_id', (string) $campaign->organization_id);
        }

        if ($actorId) {
            $mailMessage->metadata('actor_id', (string) $actorId);
        }

        return $mailMessage;
    }

    private function resolveApplication(): ?Application
    {
        if (isset($this->application) && $this->application instanceof Application) {
            return $this->application;
        }

        $applicationId = $this->applicationId;

        if (! filled($applicationId)) {
            return null;
        }

        return Application::query()
            ->with('campaign')
            ->find((int) $applicationId);
    }

    private function resolveActorId(): ?int
    {
        if (! property_exists($this, 'actorId')) {
            return null;
        }

        $actorId = $this->actorId ?? null;

        return filled($actorId) ? (int) $actorId : null;
    }
}
