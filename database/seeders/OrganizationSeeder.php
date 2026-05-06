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
            'website_url' => null,
            'ico' => '11111111',
            'phone' => null,
            'email' => 'apple@test.com',
            'sector' => 'Technology',
        ]);
        Organization::create([
            'name' => 'Google',
            'slug' => 'google',
            'logo_path' => null,
            'description' => 'Demo organization 2',
            'website_url' => null,
            'ico' => '22222222',
            'phone' => null,
            'email' => 'google@demo.com',
            'sector' => 'Technology',
        ]);
    }
}
