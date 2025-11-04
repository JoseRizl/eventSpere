<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // List all the tables you want to move from 'public'
        $tables = [
            'users',
            'password_reset_tokens',
            'sessions',
            'personal_access_tokens',
            // Add your application-specific tables here kung naa pa
            // 'events',
            // 'tags',
            // 'categories',
            // etc...
            'migrations' // Crucial: Move the migrations table too!
        ];

        // Create the new schema if it doesn't exist (for safety)
        DB::statement('CREATE SCHEMA IF NOT EXISTS eventsphere');

        foreach ($tables as $table) {
            // Check if the table exists in the public schema before moving
            if (Schema::hasTable("public.{$table}")) {
                DB::statement("ALTER TABLE public.{$table} SET SCHEMA eventsphere");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This would be the reverse operation, moving tables back to public
        $tables = [
            'users',
            'password_reset_tokens',
            'sessions',
            'personal_access_tokens',
            'migrations'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable("eventsphere.{$table}")) {
                DB::statement("ALTER TABLE eventsphere.{$table} SET SCHEMA public");
            }
        }
    }
};
