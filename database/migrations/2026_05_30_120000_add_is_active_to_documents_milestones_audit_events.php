<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('file_path');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('deadline');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
