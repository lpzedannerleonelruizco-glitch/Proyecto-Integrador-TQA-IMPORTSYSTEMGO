<?php

namespace App\Services;

use App\Models\User;
use App\Support\DemoCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoCredentialService
{
    /** @return array<int, array{email: string, password: string}> */
    public function rotate(): array
    {
        return DB::transaction(function (): array {
            $catalog = DemoCatalog::identities();
            $users = User::query()->whereIn('email', array_keys($catalog))->lockForUpdate()->get()->keyBy('email');
            if ($users->count() !== count($catalog)) {
                throw new \RuntimeException('Faltan cuentas DEMO. Ejecute primero el aprovisionamiento autorizado.');
            }
            $admin = $users->get('admin.demo@'.DemoCatalog::DOMAIN);
            $credentials = [];
            foreach ($catalog as $email => $definition) {
                $user = $users->get($email);
                if (! $user?->is_demo || ! str_ends_with($user->email, '@'.DemoCatalog::DOMAIN)) {
                    throw new \RuntimeException('Rotación detenida: una cuenta objetivo no está marcada como DEMO.');
                }
                do {
                    $password = $this->password();
                } while (in_array($password, array_column($credentials, 'password'), true));
                $user->forceFill(['password' => Hash::make($password), 'password_changed_at' => now(), 'remember_token' => null])->save();
                if (! Hash::check($password, $user->password)) {
                    throw new \RuntimeException('No fue posible verificar una credencial DEMO recien generada.');
                }
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $admin->id, 'branch_id' => $user->primary_branch_id,
                    'action' => 'demo.credentials.rotated', 'resource_type' => 'users', 'resource_id' => $user->id,
                    'before_values' => null, 'after_values' => json_encode(['demo' => true], JSON_THROW_ON_ERROR), 'occurred_at' => now(),
                ]);
                $credentials[] = ['email' => $email, 'password' => $password];
            }

            return $credentials;
        }, 3);
    }

    private function password(): string
    {
        $characters = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 23)],
            'abcdefghijkmnopqrstuvwxyz'[random_int(0, 24)],
            '23456789'[random_int(0, 7)],
            '!@#$%&*+-=?'[random_int(0, 10)],
        ];
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-=?';
        while (count($characters) < 20) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }
}
