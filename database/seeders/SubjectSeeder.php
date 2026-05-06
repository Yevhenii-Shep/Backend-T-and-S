<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Subject::create([
            'name' => 'Programming',
            'description' => 'Basics of programming, algorithms, and logic',
        ]);

        Subject::create([
            'name' => 'Frontend Technologies',
            'description' => 'Vue js and more',
        ]);

        Subject::create([
            'name' => 'Databases',
            'description' => 'SQL, database design and optimization',
        ]);

        Subject::create([
            'name' => 'USI',
            'description' => 'Planning, teamwork and agile methodologies',
        ]);

        Subject::create([
            'name' => 'Mathematics 1',
            'description' => 'Mathematical foundations for IT',
        ]);

    }

}