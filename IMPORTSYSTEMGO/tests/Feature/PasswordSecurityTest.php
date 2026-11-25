<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_requires_current_password_and_logs_out(): void
    {
        $old = 'Aa1!'.bin2hex(random_bytes(20));
        $new = 'Bb2!'.bin2hex(random_bytes(20));
        $user = User::factory()->create(['password' => $old]);
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'incorrect', 'password' => $new, 'password_confirmation' => $new,
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check($old, $user->fresh()->password));
        $this->put(route('password.update'), [
            'current_password' => $old, 'password' => $new, 'password_confirmation' => $new,
        ])->assertRedirect('/login');
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_log_and_failover_mailers_cannot_receive_reset_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        foreach (['log', 'failover'] as $mailer) {
            config(['security.password_reset_mailer' => $mailer]);
            $this->post(route('password.email'), ['email' => $user->email])->assertStatus(503);
        }
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_link_is_single_use_and_does_not_enumerate_accounts(): void
    {
        Notification::fake();
        config(['security.password_reset_mailer' => 'array']);
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        $message = session('status');
        $this->post(route('password.email'), ['email' => 'absent@example.test'])->assertSessionHas('status', $message);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $notification = Notification::sent($user, ResetPasswordNotification::class)->first();
        $password = 'Cc3!'.bin2hex(random_bytes(20));
        $payload = ['email' => $user->email, 'token' => $notification->token, 'password' => $password, 'password_confirmation' => $password];
        $this->post(route('password.reset.store'), $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->post(route('password.reset.store'), $payload)->assertSessionHasErrors('email');
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $password = 'Dd4!'.bin2hex(random_bytes(20));
        $this->post(route('password.reset.store'), ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password])
            ->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check($password, $user->fresh()->password));
    }
}
