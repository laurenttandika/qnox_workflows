<?php

namespace Qnox\Workflows\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Qnox\Workflows\Models\WorkflowInstance;
use Illuminate\Support\Facades\Route;

class NextApproverNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkflowInstance $instance)
    {
        $this->afterCommit();
    }

    public function via($notifiable): array
    {
        return config('workflows.notify_channels', ['mail']);
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = class_basename($this->instance->subject_type) . ' #' . $this->instance->subject_id . ' requires your action';
        $route = config('workflows.routes.web.name_prefix', 'workflows.').'inbox.show';
        $url = Route::has($route)
            ? route($route, $this->instance)
            : url('/workflows/inbox/item/'.$this->instance->id);
        $levelName = optional($this->instance->currentLevel)->name ?? 'current level';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Action required')
            ->line('A workflow item has moved to ' . $levelName . ' and awaits action.')
            ->action('Open item', $url)
            ->line('Thank you');
    }

    public function toArray($notifiable): array
    {
        return [
            'workflow_instance_id' => $this->instance->id,
            'workflow_id' => $this->instance->workflow_id,
            'subject_type' => $this->instance->subject_type,
            'subject_id' => $this->instance->subject_id,
            'current_level_id' => $this->instance->current_level_id,
            'status' => $this->instance->status,
        ];
    }
}
