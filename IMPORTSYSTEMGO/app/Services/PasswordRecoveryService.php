<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PasswordRecoveryService
{
    public function available(): bool
    {
        $mailer = config('security.password_reset_mailer');
        if (! is_string($mailer) || $mailer === '') {
            return false;
        }
        $transport = config("mail.mailers.{$mailer}.transport");

        // Reject log, failover/roundrobin (which can hide a log transport), and unknown drivers.
        return in_array($transport, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true)
            || (app()->environment('testing') && $transport === 'array');
    }

    public function send(string $email): void
    {
        abort_unless($this->available(), 503, 'La recuperación requiere un transporte de correo seguro configurado.');
        try {
            Password::sendResetLink(['email' => $email, 'status' => User::STATUS_ACTIVE], function (User $user, string $token) {
                $user->notify(new ResetPasswordNotification($token));
            });
        } catch (\Throwable) {
            // Transport exceptions can contain credentials or message bodies. Never report them.
            Log::warning('security.password_recovery_delivery_failed');
            // Keep the same public response for unknown accounts and delivery failures.
        }
    }

    public function reset(array $credentials): bool
    {
        $status = Password::reset($credentials + ['status' => User::STATUS_ACTIVE], function (User $user, string $password) {
            app(PasswordService::class)->replace($user, $password);
        });

        return $status === Password::PASSWORD_RESET;
    }
}
