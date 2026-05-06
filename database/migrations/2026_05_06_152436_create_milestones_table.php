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
    Schema::create('milestones', function (Blueprint $table) {
        $table->id();

        // Связь с проектом
        $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

        $table->string('name');
        $table->text('description')->nullable();

        // Статус этапа
        $table->unsignedTinyInteger('status');

        // Дедлайн этапа
        $table->dateTime('deadline')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
