<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Subject;

class CategorySubjectSeeder extends Seeder
{
    /**
     * Запуск сидера
     */
    public function run(): void
    {
        // получаем категории
        $web = Category::where('name', 'Web Development')->first();
        $ai = Category::where('name', 'AI')->first();
        $dbCategory = Category::where('name', 'Databases')->first();

        // получаем предметы
        $programming = Subject::where('name', 'Programming')->first();
        $ft = Subject::where('name', 'Frontend Technologies')->first();
        $dbSubject = Subject::where('name', 'Databases')->first();
        $usi = Subject::where('name', 'USI')->first();
        $math = Subject::where('name', 'Mathematics 1')->first();

        // WEB категория
        $web->subjects()->attach([
            $programming->id,
            $ft->id,
            $usi->id,
        ]);

        // AI категория
        $ai->subjects()->attach([
            $dbSubject->id,
            $math->id,
        ]);

        // DB категория
        $dbCategory->subjects()->attach([
            $dbSubject->id,
            $usi->id,
        ]);
    }
}
