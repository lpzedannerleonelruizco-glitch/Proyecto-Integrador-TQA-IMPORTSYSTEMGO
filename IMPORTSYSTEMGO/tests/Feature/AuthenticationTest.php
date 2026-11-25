<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_post_requires_csrf_outside_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'not-a-credential'])->assertStatus(419);
    }

    public function test_login_page_and_protected_routes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Acceso corporativo')->assertDontSee('Entorno académico DEMO');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/sucursales/seleccionar')->assertRedirect('/login');
    }

    public function test_login_identifies_the_demo_environment_visibly(): void
    {
        config(['demo.retail_enabled' => true]);

        $this->get('/login')->assertOk()->assertSee('Entorno académico DEMO');
    }

    public function test_valid_login_regenerates_session_and_logout_clears_branch(): void
    {
        $password = bin2hex(random_bytes(24));
        $user = User::factory()->create(['password' => $password]);
        $this->withSession(['active_branch_id' => 999]);
        $oldId = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('branches.select'))->assertSessionMissing('active_branch_id');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('active_branch_id');
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $password = bin2hex(random_bytes(24));
        $user = User::factory()->create(['password' => $password, 'status' => 'inactive']);
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_five_failed_attempts_block_even_correct_password(): void
    {
        $password = bin2hex(random_bytes(24));
        $user = User::factory()->create(['password' => $password]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'invalid'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_branch_selector_filters_memberships_and_rejects_forged_branch(): void
    {
        $user = User::factory()->create();
        $allowed = Branch::factory()->create();
        $other = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($allowed)->create();
        $this->actingAs($user)->get(route('branches.select'))->assertOk()->assertSee($allowed->name)->assertDontSee($other->name);
        $this->post(route('branches.activate'), ['branch_id' => $other->id])->assertForbidden();
        $this->post(route('branches.activate'), ['branch_id' => $allowed->id])
            ->assertRedirect(route('dashboard'))->assertSessionHas('active_branch_id', $allowed->id);
        $this->assertNull(app(BranchContext::class)->current());
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_revoked_membership_is_denied_on_next_request(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $membership = BranchUserMembership::factory()->for($user)->for($branch)->create();
        $this->actingAs($user)->post(route('branches.activate'), ['branch_id' => $branch->id])->assertRedirect();
        $membership->update(['ends_at' => now()->subSecond()]);
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_deactivation_invalidates_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        User::whereKey($user->id)->update(['status' => 'inactive']);
        $this->get(route('branches.select'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_dashboard_does_not_expose_counts_without_permissions(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($branch)->create();
        $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard')->assertOk()->assertSee($branch->name)
            ->assertDontSee('Almacenes activos')->assertDontSee('Empleados activos');
    }
}
