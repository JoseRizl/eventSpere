<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommitteeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $committees = [
            ['name' => 'Logistics'],
            ['name' => 'Sports'],
            ['name' => 'Catering'],
            ['name' => 'Entertainment'],
            ['name' => 'Program'],
            ['name' => 'Public Relations'],
            ['name' => 'Literary'],
            ['name' => 'Technical'],
            ['name' => 'Rules and Compliance'],
        ];

    DB::table('committees')->insert($committees);

    }
}
