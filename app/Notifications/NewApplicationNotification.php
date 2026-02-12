<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->application->campaign;
        $name = $this->application->full_name;

        return (new MailMessage)
            ->subject("Neue Bewerbung: {$campaign->title}")
            ->greeting('Neue Bewerbung erhalten')
            ->line("Bewerber: {$name}")
            ->line("E-Mail: {$this->application->email}")
            ->line("Job: {$campaign->title}")
            ->action('Im Adminbereich pruefen', url('/admin'))
            ->line('Bitte pruefen Sie die Bewerbung und aktualisieren Sie den Status.');
    }
}
