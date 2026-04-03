<?php

return [
    // Fully qualified class name of your User model
    'user_model' => App\Models\User::class,

    // Bind a custom resolver here if you replace the default
    'assignment_resolver' => Qnox\Workflows\Services\DefaultAssignmentResolver::class,

    // Notification channels for next approvers
    'notify_channels' => ['mail'],

    // Default action labels used by the IOO-WEB-V2 inspired workflow flow.
    'action_labels' => [
        'submit' => 'Submit',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'return' => 'Return',
        'hold' => 'Hold',
        'resume' => 'Resume',
        'recall' => 'Recall',
        'complete' => 'Complete',
    ],
];
