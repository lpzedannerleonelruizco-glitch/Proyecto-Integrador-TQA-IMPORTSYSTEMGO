<?php

namespace App\Console\Commands;

use App\Services\Qa\QaCredentialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetQaDemoPassword extends Command
{
    protected $signature = 'qa:reset-demo-password {--from-environment : Lee QA_E2E_PASSWORD, definido por un prompt oculto del script local}';

    protected $description = 'Restablece de forma local y auditada la cuenta de demostración del QA Center.';

    public function handle(QaCredentialService $credentials): int
    {
        if (! $this->isExpectedQaDatabase()) {
            $this->error('Operación rechazada: la conexión efectiva no es database/qa.sqlite.');

            return self::FAILURE;
        }

        $fromEnvironment = (bool) $this->option('from-environment');
        if (! $fromEnvironment && ! $this->input->isInteractive()) {
            $this->error('Se requiere una terminal interactiva o --from-environment desde el script local seguro.');

            return self::FAILURE;
        }

        $password = $fromEnvironment ? getenv('QA_E2E_PASSWORD') : $this->secret('Contraseña QA nueva');
        $confirmation = $fromEnvironment ? $password : $this->secret('Repite la contraseña QA nueva');
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => ['required', 'confirmed', 'max:128', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            unset($password, $confirmation);
            $this->error('La contraseña no cumple la política o las entradas no coinciden. No se realizaron cambios.');

            return self::FAILURE;
        }

        $credentials->reset((string) $password);
        unset($password, $confirmation);
        $this->info('Credencial QA actualizada; las sesiones persistentes anteriores fueron invalidadas.');

        return self::SUCCESS;
    }

    private function isExpectedQaDatabase(): bool
    {
        if (config('database.default') !== 'sqlite') {
            return false;
        }

        $expected = realpath(database_path('qa.sqlite'));
        $actual = realpath((string) DB::connection()->getDatabaseName());

        return $expected !== false && $actual !== false && strcasecmp($expected, $actual) === 0;
    }
}
