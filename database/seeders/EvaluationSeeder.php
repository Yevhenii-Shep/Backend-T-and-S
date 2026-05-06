<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Project;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\Category;
use App\Models\User;


class EvaluationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $finishedProject = Project::where('name', 'Uber(finished)')->first();
        $mentorGoogle = User::where('email', 'google_admin@gmail.com')->first();

        Evaluation::create([
            'score' => 95,
            'comment' => 'Excellent project, very cool',
            'project_id' => $finishedProject->id,
            'evaluator_id' => $mentorGoogle->id,
        ]);
    }
}
