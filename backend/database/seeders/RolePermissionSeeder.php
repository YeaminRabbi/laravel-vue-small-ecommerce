<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $ownerPermissionNames = ['dashboard.view', 'shops.view', 'catalog.view', 'catalog.create', 'listings.manage'];
        $adminPermissionNames = [...$ownerPermissionNames, 'shops.manage', 'catalog.manage', 'owners.manage'];

        $permissions = collect($adminPermissionNames)
            ->map(fn (string $name): Permission => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->syncPermissions($permissions->all());
        Role::firstOrCreate(['name' => 'shop_owner', 'guard_name' => 'web'])
            ->syncPermissions($permissions->whereIn('name', $ownerPermissionNames)->all());
        Role::findOrCreate('user', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
