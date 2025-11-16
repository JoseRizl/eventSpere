<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emport.tasks', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->foreignId('event_id')->constrained(table: 'events')->onDelete('cascade');
            $table->foreignId('committee_id')->nullable()->constrained(table: 'committees')->onDelete('set null');
            $table->timestamps();
        });
    }
};
