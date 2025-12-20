<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CoreRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'User',
            'Admin',
            'Super Admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }
}
