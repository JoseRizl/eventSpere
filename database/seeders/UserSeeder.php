<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@1',
            'password' => '123',
        ]);
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminUser->roles()->attach($adminRole);
        }


        $tmUser = User::create([
            'name' => 'TM User',
            'email' => 'sports@1',
            'password' => '123',
        ]);
        $tmRole = Role::where('name', 'TournamentManager')->first();
        if ($tmRole) {
            $tmUser->roles()->attach($tmRole);
        }
    }
}
