<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = rtrim((string) config('app.frontend_url'), '/').'/login';

        return (new MailMessage)
            ->subject('Welcome to NTI')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your account has been created successfully.')
            ->action('Log in', $loginUrl)
            ->line('If you did not register, please contact NTI support.');
    }
}
