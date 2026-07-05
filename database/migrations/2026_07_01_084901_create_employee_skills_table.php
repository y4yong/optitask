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
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();
            
            // foreign key user_id referencing users.user_id
            $table->string('user_id', 50);
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            
            // foreign key skill_id referencing skills.skill_id
            $table->unsignedBigInteger('skill_id');
            $table->foreign('skill_id')->references('skill_id')->on('skills')->onDelete('cascade');

            $table->integer('proficiency_level'); // 1 to 5
            
            // Unique index across user_id & skill_id
            $table->unique(['user_id', 'skill_id'], 'emp_skill_unique');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
