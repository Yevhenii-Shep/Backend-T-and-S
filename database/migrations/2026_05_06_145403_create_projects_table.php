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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Связь с командой (может быть NULL)
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

            // Связь с организацией (может быть NULL)
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            $table->unsignedTinyInteger('program_type');

            // Связь с users(ментор из NTI) (может быть NULL)
            $table->foreignId('mentor_from_nti')->nullable()->constrained('users')->nullOnDelete();

            // Связь с users(ментор из организации) (может быть NULL)
            $table->foreignId('mentor_from_organization')->nullable()->constrained('users')->nullOnDelete();

            // Связь с категориями
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            $table->unsignedTinyInteger('status');

            $table->text('description')->nullable();

            $table->dateTime('deadline')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
