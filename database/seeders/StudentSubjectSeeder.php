<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Subject;

class StudentSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $student1 = User::where('email', 'student1@test.com')->first();
        $student2 = User::where('email', 'student2@test.com')->first();
        $student3 = User::where('email', 'student3@test.com')->first();

        // получаем предметы
        $programming = Subject::where('name', 'Programming')->first();
        $usi = Subject::where('name', 'USI')->first();
        $db = Subject::where('name', 'Databases')->first();

        // привязываем предметы к студенту
        $student1->subjects()->attach($programming->id, [
            'grade' => 1.5,
        ]);
        $student1->subjects()->attach($usi->id, [
            'grade' => 2.5,
        ]);

        $student2->subjects()->attach($db->id, [
            'grade' => 2.0,
        ]);
        $student2->subjects()->attach($programming->id, [
            'grade' => 1.0,
        ]);

        $student3->subjects()->attach($usi->id, [
            'grade' => 2.5,
        ]);
        $student3->subjects()->attach($db->id, [
            'grade' => 3.0,
        ]);
    }
}
