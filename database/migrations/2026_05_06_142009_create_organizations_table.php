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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('logo_path')->nullable();

            $table->text('description')->nullable(); // большой текст

            $table->string('website_url')->nullable();

            $table->string('ico', 20)->unique(); // уникальная 

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('sector')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
