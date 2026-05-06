<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            CategorySeeder::class,
            SubjectSeeder::class,
            UserSeeder::class,
            TeamSeeder::class,
            ProjectSeeder::class,
            MilestoneSeeder::class,
            EvaluationSeeder::class,
            DocumentSeeder::class,
            AuditEventSeeder::class,
            StudentSubjectSeeder::class,
            CategorySubjectSeeder::class,
        ]);
    }
}
