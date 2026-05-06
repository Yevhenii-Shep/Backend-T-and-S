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
        Schema::create('student_subject', function (Blueprint $table) {

            // Связь с user
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Связь с предметом
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

            $table->decimal('grade', 3, 1)->nullable();

            $table->primary(['user_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_subject');
    }
};
