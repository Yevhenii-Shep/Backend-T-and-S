<?php

namespace App\Notifications;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Evaluation $evaluation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->evaluation->loadMissing(['project', 'evaluator']);

        $project = $this->evaluation->project;
        $projectUrl = rtrim((string) config('app.frontend_url'), '/').'/projects/'.$project->slug;
        $evaluatorName = $this->evaluation->evaluator?->name ?? 'NTI staff';

        $message = (new MailMessage)
            ->subject('New evaluation for: '.$project->name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your project "'.$project->name.'" received a new evaluation.')
            ->line('Score: '.$this->evaluation->score.'/100')
            ->line('Evaluator: '.$evaluatorName);

        if ($this->evaluation->comment) {
            $message->line('Comment: '.$this->evaluation->comment);
        }

        return $message->action('View project', $projectUrl);
    }
}
