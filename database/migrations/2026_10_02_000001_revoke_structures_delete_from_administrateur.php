<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration de données : en production, RolePermissionSeeder ne tourne
 * qu'au premier déploiement. Le retrait de structures.delete du rôle
 * administrateur (voir le seeder) doit donc aussi être appliqué aux bases
 * déjà seedées. Idempotente : sans effet si le rôle ou la permission
 * n'existe pas, ou si le rôle ne l'a déjà plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('name', 'administrateur')->where('guard_name', 'sanctum')->first();
        $permission = Permission::query()->where('name', 'structures.delete')->where('guard_name', 'sanctum')->first();

        if ($role && $permission && $role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'administrateur')->where('guard_name', 'sanctum')->first();
        $permission = Permission::query()->where('name', 'structures.delete')->where('guard_name', 'sanctum')->first();

        if ($role && $permission) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
