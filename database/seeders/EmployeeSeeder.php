<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            ['name' => 'Glene'],
            ['name' => 'Miko'],
            ['name' => 'Bryan'],
            ['name' => 'Jose Pr'],
            ['name' => 'Kim Jong Un'],
            ['name' => 'Melvin'],
            ['name' => 'Lee Ar'],
            ['name' => 'Jungkook'],
            ['name' => 'Edsel'],
            ['name' => 'Salazar'],
            ['name' => 'Jaudian'],
            ['name' => 'Xi Jinping'],
            ['name' => 'Bato'],
        ];


        DB::table('emport.employees')->insert($employees);
    }
}
