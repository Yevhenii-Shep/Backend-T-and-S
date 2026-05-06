<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Document;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aiProject = Project::where('name', 'AI Platform')->first();
        $dbProject = Project::where('name', 'Big BD creation')->first();

        Document::create([
            'project_id' => $aiProject->id,
            'name' => 'Technical Specification',
            'description' => 'fkafksjfdkafk',
            'file_path' => 'documents/technical.pdf'
        ]);
        Document::create([
            'project_id' => $aiProject->id,
            'name' => 'Diagram',
            'description' => 'beautiful diagram',
            'file_path' => 'documents/diagram.svg'
        ]);

        Document::create([
            'project_id' => $dbProject->id,
            'name' => 'DB model',
            'description' => 'model',
            'file_path' => 'documents/model.mwb'
        ]);
        Document::create([
            'project_id' => $dbProject->id,
            'name' => 'SQL script',
            'description' => 'full sql script',
            'file_path' => 'documents/script.sql'
        ]);

    }
}
