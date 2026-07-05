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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            
            // foreign key user_id referencing users.user_id
            $table->string('user_id', 50);
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->string('notification_type', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('unread'); // unread, read
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
