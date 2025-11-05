<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tags')->insert([
            ['name' => 'Essay Writing', 'category_id' => 1, 'description' => 'sulatt', 'archived' => false],
            ['name' => 'Foundation Day', 'category_id' => 2, 'description' => 'For Events under Foundation Day', 'archived' => false],
            ['name' => 'Palakasan', 'category_id' => 3, 'description' => 'Sports and Competitive Events', 'archived' => false],
            ['name' => 'Buwan ng Wika', 'category_id' => 1, 'description' => 'Events under Buwan ng Wika', 'archived' => false],
            ['name' => 'Jolen Tournament', 'category_id' => 3, 'description' => 'Test', 'archived' => false],
            ['name' => 'Graduation', 'category_id' => 1, 'description' => 'Events under Graduation', 'archived' => false],
            ['name' => 'Holiday', 'category_id' => 6, 'description' => 'No class', 'archived' => false], // category_id "5e80" = Seasonal & Community Events -> becomes 6
            ['name' => 'Nutrition Month', 'category_id' => 2, 'description' => null, 'archived' => false],
            ['name' => 'Awareness', 'category_id' => 7, 'description' => 'For awareness events', 'archived' => false],
            ['name' => 'Basketball', 'category_id' => 3, 'description' => null, 'archived' => false],
            ['name' => 'Bagyo', 'category_id' => 8, 'description' => null, 'archived' => true], // Category = Cultural & Arts Events
        ]);
    }
}
