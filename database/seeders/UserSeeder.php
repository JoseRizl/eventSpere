<?php

namespace Database\Seeders;

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
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@1',
            'password' => '123',
            'role' => 'Admin',
        ]);

        User::create([
            'name' => 'TM User',
            'email' => 'sports@1',
            'password' => '123',
            'role' => 'TournamentManager',
        ]);
    }
}
