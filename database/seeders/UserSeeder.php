<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /** ADMIN USER */
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('123'),
        ]);

        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminUser->roles()->attach($adminRole->id);
        }

        /** TOURNAMENT MANAGERS */
        $tmUsers = [
            [
                'name' => 'TM User',
                'email' => 'sports1@example.com',
                'password' => '123',
            ],
            [
                'name' => 'TM User2',
                'email' => 'sports2@example.com',
                'password' => '123',
            ],
        ];

        $tmRole = Role::where('name', 'TournamentManager')->first();

        foreach ($tmUsers as $userData) {
            $tmUser = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
            ]);

            if ($tmRole) {
                $tmUser->roles()->attach($tmRole->id);
            }
        }
    }
}
