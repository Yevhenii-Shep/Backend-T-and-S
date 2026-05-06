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
    Schema::create('audit_events', function (Blueprint $table) {
        $table->id();

        // связь с проектами
        $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

        // связь с user (главный аудитор)
        $table->foreignId('main_auditor')->constrained('users')->restrictOnDelete();

        $table->unsignedTinyInteger('result')->nullable();

        $table->dateTime('start_time');
        $table->dateTime('end_time');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
