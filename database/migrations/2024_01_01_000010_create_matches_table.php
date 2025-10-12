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
        Schema::create('matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('bracket_id')->constrained()->onDelete('cascade');
            $table->integer('round');
            $table->integer('match_number');
            $table->uuid('winner_id')->nullable();
            $table->uuid('loser_id')->nullable();
            $table->string('status')->default('pending'); // pending, completed
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('is_tie')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
