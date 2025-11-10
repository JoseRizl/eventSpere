<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            // Add the color column (nullable for older records)
            $table->string('color', 20)->nullable()->after('completed');
        });
    }

    public function down(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
