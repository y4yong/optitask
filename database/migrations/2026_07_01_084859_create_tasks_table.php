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
        Schema::create('tasks', function (Blueprint $table) {
            $table->string('task_id', 50)->primary();
            $table->string('task_title', 255);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('task_status', 50)->default('To-Do');
            $table->string('priority', 20)->default('Medium');
            
            // foreign key employee_id referencing users.user_id
            $table->string('employee_id', 50)->nullable();
            $table->foreign('employee_id')->references('user_id')->on('users')->onDelete('set null');

            // foreign key manager_id referencing users.user_id
            $table->string('manager_id', 50)->nullable();
            $table->foreign('manager_id')->references('user_id')->on('users')->onDelete('set null');

            $table->text('manager_notes')->nullable();
            $table->string('task_type', 50)->default('Personal');
            $table->string('task_file', 255)->nullable();
            $table->string('submission_file', 255)->nullable();
            $table->string('evidence_link', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
