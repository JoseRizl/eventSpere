<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brackets', function (Blueprint $table) {
            // Drop the existing primary key
            $table->dropPrimary('brackets_id_primary');
        });

        // Change id column type to VARCHAR
        DB::statement("ALTER TABLE brackets MODIFY id VARCHAR(64) NOT NULL;");

        Schema::table('brackets', function (Blueprint $table) {
            // Re-add the primary key constraint
            $table->primary('id');
        });
    }

    public function down(): void
    {
        Schema::table('brackets', function (Blueprint $table) {
            $table->dropPrimary('brackets_id_primary');
        });

        DB::statement("ALTER TABLE brackets MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;");

        Schema::table('brackets', function (Blueprint $table) {
            $table->primary('id');
        });
    }
};
