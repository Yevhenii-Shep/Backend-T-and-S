<?php

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('project_id');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('project_id');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('project_id');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('project_id');
        });

        $this->backfillSlugs();
    }

    public function down(): void
    {
        Schema::table('audit_events', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('evaluations', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('milestones', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('slug'));
    }

    private function backfillSlugs(): void
    {
        User::query()->whereNull('slug')->each(function (User $user) {
            $user->updateQuietly([
                'slug' => User::generateUniqueSlug($user->slugBase(), $user->id),
            ]);
        });

        Document::query()->whereNull('slug')->each(function (Document $document) {
            $document->updateQuietly([
                'slug' => Document::generateUniqueSlug($document->slugBase(), $document->id),
            ]);
        });

        Milestone::query()->whereNull('slug')->each(function (Milestone $milestone) {
            $milestone->updateQuietly([
                'slug' => Milestone::generateUniqueSlug($milestone->slugBase(), $milestone->id),
            ]);
        });

        Evaluation::query()->whereNull('slug')->each(function (Evaluation $evaluation) {
            $evaluation->updateQuietly([
                'slug' => Evaluation::generateUniqueSlug($evaluation->slugBase(), $evaluation->id),
            ]);
        });

        AuditEvent::query()->whereNull('slug')->each(function (AuditEvent $auditEvent) {
            $auditEvent->updateQuietly([
                'slug' => AuditEvent::generateUniqueSlug($auditEvent->slugBase(), $auditEvent->id),
            ]);
        });
    }
};
