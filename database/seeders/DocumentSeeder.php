<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $aiProject = Project::where('name', 'AI Platform')->first();
        $dbProject = Project::where('name', 'Big BD creation')->first();

        if (!$aiProject || !$dbProject) {
            $this->command?->warn('DocumentSeeder: projects not found, skipped.');

            return;
        }

        Document::create([
            'project_id' => $aiProject->id,
            'name' => 'Technical Specification',
            'description' => 'fkafksjfdkafk',
            'file_path' => 'documents/technical.pdf',
            'is_active' => true,
        ]);

        Document::create([
            'project_id' => $aiProject->id,
            'name' => 'Diagram',
            'description' => 'beautiful diagram',
            'file_path' => 'documents/diagram.svg',
            'is_active' => true,
        ]);

        Document::create([
            'project_id' => $dbProject->id,
            'name' => 'DB model',
            'description' => 'model',
            'file_path' => 'documents/model.mwb',
            'is_active' => true,
        ]);

        Document::create([
            'project_id' => $dbProject->id,
            'name' => 'SQL script',
            'description' => 'full sql script',
            'file_path' => 'documents/script.sql',
            'is_active' => true,
        ]);

        // Деактивированный документ (для проверки soft-delete через is_active).
        Document::create([
            'project_id' => $aiProject->id,
            'name' => 'Old draft (archived)',
            'description' => 'Removed from active list',
            'file_path' => 'documents/old-draft.pdf',
            'is_active' => false,
        ]);
    }
}
