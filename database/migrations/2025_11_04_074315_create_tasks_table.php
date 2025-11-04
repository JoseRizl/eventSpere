<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('committee_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }
};
