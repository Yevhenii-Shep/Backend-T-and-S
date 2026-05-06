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
        Schema::create('audit_participants', function (Blueprint $table) {

            // Связь с пользователем
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Связь с аудитот
            $table->foreignId('audit_event_id')->constrained('audit_events')->cascadeOnDelete();

            $table->unsignedTinyInteger('role');

            $table->primary(['user_id', 'audit_event_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_participants');
    }
};
