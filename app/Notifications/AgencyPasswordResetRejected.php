<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencyPasswordResetRejected extends Notification
{
    use Queueable;

    private readonly string $reason;

    public function __construct(string $reason)
    {
        $this->reason = trim(strip_tags($reason));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.reset_rejected_mail.subject'))
            ->greeting(__('auth.reset_rejected_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('auth.reset_rejected_mail.message'))
            ->line(__('auth.reset_rejected_mail.reason', ['reason' => $this->reason]))
            ->line(__('auth.reset_rejected_mail.help'));
    }
}
