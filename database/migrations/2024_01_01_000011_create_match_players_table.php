<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('match_id')->constrained('matches')->onDelete('cascade');
            // Assuming player_id will reference a 'players' or 'users' table with UUIDs.
            // It's nullable to allow for 'TBD' or 'BYE' slots.
            $table->uuid('player_id')->nullable();
            $table->string('name'); // For player names, or 'TBD'/'BYE'
            $table->integer('slot'); // e.g., 1 or 2
            $table->integer('score')->default(0);
            $table->boolean('completed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
