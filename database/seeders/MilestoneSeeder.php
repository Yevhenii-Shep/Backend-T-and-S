<?php

namespace Database\Seeders;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Seeder;

class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        $aiProject = Project::where('name', 'AI Platform')->first();
        $dbProject = Project::where('name', 'Big BD creation')->first();

        if (!$aiProject || !$dbProject) {
            $this->command?->warn('MilestoneSeeder: projects not found, skipped.');

            return;
        }

        Milestone::create([
            'project_id' => $aiProject->id,
            'name' => 'Planning',
            'status' => Milestone::STATUS_IN_PROGRESS,
            'deadline' => now()->addWeeks(3),
            'description' => 'Project planning and requirements gathering',
            'is_active' => true,
        ]);

        Milestone::create([
            'project_id' => $aiProject->id,
            'name' => 'Programming',
            'status' => Milestone::STATUS_PENDING,
            'deadline' => now()->addWeeks(8),
            'description' => 'write code',
            'is_active' => true,
        ]);

        Milestone::create([
            'project_id' => $dbProject->id,
            'name' => 'Nothing',
            'status' => Milestone::STATUS_DONE,
            'deadline' => now()->addWeeks(6),
            'description' => 'Just chill guys',
            'is_active' => true,
        ]);

        Milestone::create([
            'project_id' => $dbProject->id,
            'name' => 'Call mom and ask her to help you',
            'status' => Milestone::STATUS_IN_PROGRESS,
            'deadline' => now()->addWeeks(12),
            'description' => 'Do you remember your mom number?',
            'is_active' => true,
        ]);
    }
}
