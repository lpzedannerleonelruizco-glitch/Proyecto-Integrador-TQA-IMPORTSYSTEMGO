<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\GlobalUserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateInitialAdministrator extends Command
{
    protected $signature = 'app:create-initial-administrator';

    protected $description = 'Inicialización interactiva explícita del administrador y su centro logístico; no sobrescribe cuentas.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Se requiere una terminal interactiva. No se aceptan contraseñas como argumentos.');

            return self::FAILURE;
        }
        // Serialize this local bootstrap command without depending on a populated SQL table.
        $lock = fopen(storage_path('framework/initial-administrator.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->error('Otra inicialización está en curso.');

            return self::FAILURE;
        }
        try {
            if (User::exists() || Branch::exists() || Role::exists()) {
                $this->error('La inicialización exige usuarios, sucursales y roles vacíos. Usa administración controlada para una instalación existente.');

                return self::FAILURE;
            }
            $this->warn('Esta operación creará catálogo de permisos, administrador, centro logístico y membresía en la conexión configurada.');
            $this->line('Base: '.DB::connection()->getDatabaseName());
            if (! $this->confirm('¿Autorizas expresamente estos datos iniciales en esta base?', false)) {
                return self::FAILURE;
            }
            $data = [
                'name' => $this->ask('Nombre del administrador'),
                'email' => $this->ask('Correo del administrador'),
                'code' => $this->ask('Código real del centro logístico'),
                'branch_name' => $this->ask('Nombre real del centro logístico'),
                'password' => $this->secret('Contraseña nueva (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)'),
                'password_confirmation' => $this->secret('Repite la contraseña'),
            ];
            $validation = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'code' => ['required', 'alpha_dash:ascii', 'max:32'],
                'branch_name' => ['required', 'string', 'max:150'],
                'password' => ['required', 'confirmed', 'max:128', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]);
            if ($validation->fails()) {
                // Do not print validation payloads or values, including secrets.
                $this->error('Datos inválidos o contraseñas no coincidentes. No se creó ninguna cuenta.');

                return self::FAILURE;
            }
            $id = DB::transaction(function () use ($data) {
                abort_if(User::exists() || Branch::exists() || Role::exists(), 409);
                app(AuthorizationSeeder::class)->run();
                $branch = Branch::create(['code' => $data['code'], 'name' => $data['branch_name'], 'type' => Branch::TYPE_CENTRAL, 'timezone' => 'America/La_Paz', 'is_active' => true]);
                $user = new User;
                $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'status' => User::STATUS_ACTIVE, 'primary_branch_id' => $branch->id])->save();
                BranchUserMembership::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'starts_at' => now(), 'granted_by' => $user->id]);
                GlobalUserRole::create(['user_id' => $user->id, 'role_id' => Role::where('name', 'administrador-general')->where('scope_type', Role::SCOPE_GLOBAL)->where('guard_name', 'web')->firstOrFail()->id, 'starts_at' => now(), 'granted_by' => $user->id]);

                return $user->id;
            });
            unset($data);
            Log::info('security.initial_administrator_created', ['user_id' => $id]);
            $this->info('Administrador y centro logístico creados. Accede por /login.');

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
