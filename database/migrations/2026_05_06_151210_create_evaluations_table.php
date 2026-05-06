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
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();

            // Связь с проектом
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            
            // Связь с user(тот кто оценил)
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();

            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();

            // Ограничение один пользователь = одна оценка на проект
            $table->unique(['project_id', 'evaluator_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
