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
            $table->string('id')->primary();
            $table->string('bracket_id');
            $table->foreign('bracket_id')->references('id')->on('brackets')->onDelete('cascade');
            $table->integer('round');
            $table->integer('match_number');
            $table->string('winner_id')->nullable();
            $table->string('loser_id')->nullable();
            $table->string('status')->default('pending'); // pending, completed
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('venue')->nullable();
            $table->string('bracket_type')->nullable(); // For double-elim: winners, losers, grand_finals
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
