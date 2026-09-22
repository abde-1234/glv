<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AgencyPasswordResetLink extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('auth.reset_mail.subject'))
            ->greeting(__('auth.reset_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('auth.reset_mail.approved'))
            ->line(__('auth.reset_mail.instructions'))
            ->action(__('auth.reset_mail.action'), $url)
            ->line(__('auth.reset_mail.expiration', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('auth.reset_mail.warning'));
    }
}
