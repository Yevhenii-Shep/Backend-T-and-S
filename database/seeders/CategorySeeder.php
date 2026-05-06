<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Web Development',
            'slug'=> 'web-development',
            'description' => 'Doing websites',
        ]);

        Category::create([
            'name' => 'AI',
            'slug'=> 'ai',
            'description' => 'Ask ChatGPT how to do Backend project',
        ]);

        Category::create([
            'name' => 'Softvere Engineering',
            'slug'=> 'softvere-engineering',
            'description' => 'Maybe I spell it incorrect',
        ]);

        Category::create([
            'name' => 'Databases',
            'slug'=> 'databases',
            'description' => 'A lot of SQL',
        ]);
    }
}