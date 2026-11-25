<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable)
    {
        return parent::toMail($notifiable)->mailer(config('security.password_reset_mailer'));
    }
}
