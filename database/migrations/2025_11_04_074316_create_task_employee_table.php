<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emport.task_employee', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained(table: 'emport.tasks')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained(table: 'emport.employees')->onDelete('cascade');
            $table->primary(['task_id', 'employee_id']);
        });
    }
};
