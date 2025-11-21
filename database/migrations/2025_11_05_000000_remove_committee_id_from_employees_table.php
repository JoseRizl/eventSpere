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
        Schema::table('emport.employees', function (Blueprint $table) {
            // Drop the foreign key constraint first. Laravel's default naming convention is used.
            $table->dropForeign(['committee_id']);
            // Then drop the column.
            $table->dropColumn('committee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emport.employees', function (Blueprint $table) {
            $table->foreignId('committee_id')->nullable()->constrained(table: 'committees')->onDelete('set null');
        });
    }
};
