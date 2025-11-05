<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->insert([
        ['title' => 'General Competitions', 'description' => 'General competitions outside of sporting Events', 'allow_brackets' => true],
        ['title' => 'Academics & Educational Events', 'description' => 'Academic Events', 'allow_brackets' => false],
        ['title' => 'Commemorative', 'description' => 'Honoring special Events', 'allow_brackets' => false],
        ['title' => 'Sports & Physical Activities', 'description' => 'Sporting Events and competitions', 'allow_brackets' => true],
        ['title' => 'Leisure', 'description' => 'Leisure, relaxation, and enjoyment', 'allow_brackets' => true],
        ['title' => 'Seasonal & Community Events', 'description' => 'Seasonal Events', 'allow_brackets' => false],
        ['title' => 'Awareness Campaign', 'description' => 'Public Awareness', 'allow_brackets' => false],
        ['title' => 'Cultural & Arts Events', 'description' => 'Hfosanfmb sid sad asasf sdgsdf sadkljcxnjuoj rcbnbmn etyretuyioyt dgag w eqwfs qw cwvh vscqw sfvv cdfs v ngfd bjd ascdsb...', 'allow_brackets' => false],
        ['title' => 'Career & Future-Oriented Events', 'description' => '1', 'allow_brackets' => false],
        ['title' => 'Student Leadership & Development Activities', 'description' => 'Student Leadership & Development Activities', 'allow_brackets' => false]
    ]);

    }
}
