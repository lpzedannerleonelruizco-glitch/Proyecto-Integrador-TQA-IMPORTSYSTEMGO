<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\AuthorizationCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class QaCenterAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AuthorizationCatalog::permissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::where('name', 'administrador-general')->first();
        if ($admin) {
            $admin->syncPermissions(AuthorizationCatalog::permissions());
        }
    }
}
