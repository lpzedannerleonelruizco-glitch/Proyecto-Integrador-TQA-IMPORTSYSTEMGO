<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_noninteractive_initialization_is_refused(): void
    {
        $this->artisan('app:create-initial-administrator', ['--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_installation_is_never_overwritten(): void
    {
        User::factory()->create();
        $this->artisan('app:create-initial-administrator')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('branches', 0);
    }

    public function test_interactive_setup_creates_explicit_role_and_membership_without_default_password(): void
    {
        $password = 'Aa1!'.bin2hex(random_bytes(20));
        $this->artisan('app:create-initial-administrator')
            ->expectsConfirmation('¿Autorizas expresamente estos datos iniciales en esta base?', 'yes')
            ->expectsQuestion('Nombre del administrador', 'Administrador de prueba')
            ->expectsQuestion('Correo del administrador', 'admin@example.test')
            ->expectsQuestion('Código real del centro logístico', 'CENTRAL-TEST')
            ->expectsQuestion('Nombre real del centro logístico', 'Central de prueba')
            ->expectsQuestion('Contraseña nueva (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)', $password)
            ->expectsQuestion('Repite la contraseña', $password)
            ->assertSuccessful();
        $user = User::firstOrFail();
        $this->assertDatabaseHas('global_user_roles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('branch_user_memberships', ['user_id' => $user->id, 'branch_id' => $user->primary_branch_id]);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect(route('branches.select'));
        $this->post(route('branches.activate'), ['branch_id' => $user->primary_branch_id])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee('Almacenes activos');
    }
}
