<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Category;
use App\Models\User;

class MilestoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // получаем проекты
        $aiProject = Project::where('name', 'AI Platform')->first();
        $dbProject = Project::where('name', 'Big BD creation')->first();

        // Milestones для AI проекта
        Milestone::create([
            'project_id' => $aiProject->id,
            'name' => 'Planning',
            'status' => Milestone::STATUS_IN_PROGRESS,
            'deadline' => now()->addWeeks(3),
            'description' => 'Project planning and requirements gathering',
        ]);
        Milestone::create([
            'project_id' => $aiProject->id,
            'name' => 'Proggraming',
            'status' => Milestone::STATUS_PENDING,
            'deadline' => now()->addWeeks(8),
            'description' => 'write code',
        ]);

        // Milestones для DB проекта
        Milestone::create([
            'project_id' => $dbProject->id,
            'name' => 'Nothing',
            'status' => Milestone::STATUS_DONE,
            'deadline' => now()->addWeeks(6),
            'description' => 'Just chill guys',
        ]);
        Milestone::create([
            'project_id' => $dbProject->id,
            'name' => 'Call mom and ask her to help you',
            'status' => Milestone::STATUS_IN_PROGRESS,
            'deadline' => now()->addWeeks(12),
            'description' => 'Do u rememeber your mom number?',
        ]);
    }
}
