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
        Schema::create('users', function (Blueprint $table) {
            $table->string('user_id', 50)->primary(); // e.g. EM001, MG001, AD001
            $table->string('username', 100);
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('role', 50); // Admin, Manager, Employee
            $table->string('account_status', 20)->default('Active'); // Active, Suspended
            $table->string('suspension_reason', 255)->nullable();
            
            // foreign key dept_id
            $table->unsignedBigInteger('dept_id')->nullable();
            $table->foreign('dept_id')->references('dept_id')->on('departments')->onDelete('set null');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
