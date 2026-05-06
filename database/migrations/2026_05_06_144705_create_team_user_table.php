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

    Schema::create('team_user', function (Blueprint $table) {

        // Связь с командой 1:N
        $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

        // Связь с пользователем 1:N
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

        $table->dateTime('join_date');
        $table->dateTime('leave_date')->nullable();

        $table->boolean('is_leader')->default(false);

        // Ключ (pivot всегда лучше делать составным)
        $table->primary(['team_id', 'user_id']);

        $table->timestamps();
    });

}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
