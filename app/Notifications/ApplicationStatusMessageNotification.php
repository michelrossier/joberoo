<?php

namespace App\Notifications;

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
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine)
            ->view('emails.applications.status-message', [
                'messageHtml' => $this->messageHtml,
            ]);
    }
}
