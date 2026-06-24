<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Project $project) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->project->loadMissing('organization');

        $projectUrl = rtrim((string) config('app.frontend_url'), '/').'/projects/'.$this->project->slug;
        $organizationName = $this->project->organization?->name ?? 'an organization';

        return (new MailMessage)
            ->subject('Project accepted: '.$this->project->name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your project "'.$this->project->name.'" has been accepted by '.$organizationName.'.')
            ->line('The project status is now active.')
            ->action('View project', $projectUrl);
    }
}
