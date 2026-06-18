<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Organization;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'logo_path' => null,
            'description' => 'Demo organization',
            'website_url' => "https://www.apple.com",
            'ico' => '11111111',
            'phone' => "+11111111",
            'email' => 'apple@icloud.com',
            'sector' => 'Technology',
        ]);
        Organization::create([
            'name' => 'Google',
            'slug' => 'google',
            'logo_path' => null,
            'description' => 'Demo organization 2',
            'website_url' => "https://about.google",
            'ico' => '22222222',
            'phone' => "+22222222",
            'email' => 'google@gmail.com',
            'sector' => 'Technology',
        ]);
    }
}
