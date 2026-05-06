<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Team;
use App\Models\Organization;
use App\Models\Category;
use App\Models\User;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::where('name', 'Test Team')->first();

        $apple = Organization::where('name', 'Apple')->first();
        $google = Organization::where('name', 'Google')->first();

        $mentorNTI = User::where('role', User::ROLE_NTI_EMPLOYEE)->first();

        $category1 = Category::where('name','AI')->first();
        $category2 = Category::where('name','Databases')->first();

        $mentorApple = User::where('email', 'apple_employee@apple.com')->first();
        $mentorGoogle = User::where('email', 'google_admin@gmail.com')->first();

        Project::create([
            'name' => 'AI Platform',
            'slug' => 'ai-platform',
            'team_id' => $team?->id,
            'organization_id' => $apple?->id,
            'program_type' => Project::PROGRAM_TYPE_A,
            'mentor_from_nti' => $mentorNTI?->id,
            'category_id' => $category1->id,
            'mentor_from_organization' => $mentorApple?->id,
            'status' => Project::STATUS_ACTIVE,
            'description' => 'AI-based platform for automation',
            'deadline' => now()->addMonths(3),
        ]);

        Project::create([
            'name' => 'Big BD creation',
            'slug' => 'big-db-creation',
            'team_id' => $team?->id,
            'organization_id' => $google?->id,
            'program_type' => Project::PROGRAM_TYPE_B,
            'mentor_from_nti' => $mentorNTI?->id,
            'category_id' => $category2->id,
            'mentor_from_organization' => $mentorGoogle?->id,
            'status' => Project::STATUS_ACTIVE,
            'description' => 'SQLLLLLLLLLLLLLLLL',
            'deadline' => now()->addMonths(4),
        ]);

        Project::create([
            'name' => 'Uber(finished)',
            'slug' => 'uber',
            'team_id' => $team?->id,
            'organization_id' => $google?->id,
            'program_type' => Project::PROGRAM_TYPE_A,
            'mentor_from_nti' => $mentorNTI?->id,
            'category_id' => $category2->id,
            'mentor_from_organization' => $mentorGoogle?->id,
            'status' => Project::STATUS_DONE,
            'description' => 'Finished project',
            'deadline' => now()->subMonth(),
        ]);

    }
}
