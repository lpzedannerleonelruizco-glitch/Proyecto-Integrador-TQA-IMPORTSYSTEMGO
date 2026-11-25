<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $modelKey = $columns['model_morph_key'];
        $branchKey = $columns['team_foreign_key'];

        throw_if(! config('permission.teams'), 'Spatie Teams debe estar habilitado antes de crear sus tablas.');
        throw_if($branchKey !== 'branch_id', 'La clave de equipo de Spatie debe ser branch_id.');

        Schema::create($tables['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name', 125);
            $table->string('guard_name', 32)->default('web');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $table) use ($branchKey) {
            $table->id();
            $table->foreignId($branchKey)->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('name', 125);
            $table->string('guard_name', 32)->default('web');
            $table->string('scope_type', 16)->default('branch');
            $table->timestamps();

            $table->index([$branchKey, 'scope_type']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE {$tables['roles']} ADD CONSTRAINT roles_valid_scope CHECK (scope_type IN ('global', 'branch') AND (scope_type <> 'global' OR {$branchKey} IS NULL))");
        }

        $this->addRoleUniquenessConstraints($tables['roles'], $branchKey);

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($branchKey, $modelKey, $permissionKey, $tables) {
            $table->unsignedBigInteger($permissionKey);
            $table->string('model_type');
            $table->unsignedBigInteger($modelKey);
            $table->foreignId($branchKey)->constrained('branches')->restrictOnDelete();

            $table->index([$modelKey, 'model_type'], 'model_permissions_model_index');
            $table->foreign($permissionKey)->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->primary([$branchKey, $permissionKey, $modelKey, 'model_type'], 'model_permissions_primary');
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($branchKey, $modelKey, $roleKey, $tables) {
            $table->unsignedBigInteger($roleKey);
            $table->string('model_type');
            $table->unsignedBigInteger($modelKey);
            $table->foreignId($branchKey)->constrained('branches')->restrictOnDelete();

            $table->index([$modelKey, 'model_type'], 'model_roles_model_index');
            $table->foreign($roleKey)->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->primary([$branchKey, $roleKey, $modelKey, 'model_type'], 'model_roles_primary');
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $table) use ($permissionKey, $roleKey, $tables) {
            $table->unsignedBigInteger($permissionKey);
            $table->unsignedBigInteger($roleKey);
            $table->foreign($permissionKey)->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->foreign($roleKey)->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->primary([$permissionKey, $roleKey], 'role_permissions_primary');
        });

        app('cache')->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }

    private function addRoleUniquenessConstraints(string $table, string $branchKey): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE {$table} ADD COLUMN scope_branch_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE({$branchKey}, 0)) STORED");
            DB::statement("CREATE UNIQUE INDEX roles_scope_name_guard_unique ON {$table} (scope_branch_id, name, guard_name)");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX roles_global_name_guard_unique ON {$table} (name, guard_name) WHERE {$branchKey} IS NULL");
            DB::statement("CREATE UNIQUE INDEX roles_branch_name_guard_unique ON {$table} ({$branchKey}, name, guard_name) WHERE {$branchKey} IS NOT NULL");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($branchKey) {
            $blueprint->unique([$branchKey, 'name', 'guard_name'], 'roles_scope_name_guard_unique');
        });
    }
};
