<?php

namespace Qnox\Workflows\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Qnox\Workflows\Models\WorkflowInstance;

class NextApproverNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkflowInstance $instance) {}

    public function via($notifiable): array
    {
        return config('workflows.notify_channels', ['mail']);
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = class_basename($this->instance->subject_type) . ' #' . $this->instance->subject_id . ' requires your action';
        $url = url('/workflow/instances/' . $this->instance->id); // Adjust to your app route

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Action required')
            ->line('A workflow item has moved to your level and awaits action.')
            ->action('Open item', $url)
            ->line('Thank you');
    }
}
