<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apple = Organization::where('name', 'Apple')->first();
        $google = Organization::where('name', 'Google')->first();

        // password — открытый текст; в User cast 'hashed' хеширует при сохранении
        // Admin
        User::create([
            'name'=> 'Super Admin',
            'role' => User::ROLE_ADMIN,
            'email'=> 'admin@admin.com',
            'organization_id'=> null,
            'birth_date' => '1999-01-01',
            'password' => 'password',
            'phone' => '+1111111',
            'avatar_path' => null,
        ]);

        // Students 
        User::create([
            'name'=> 'Yevhenii Shepetia',
            'role' => User::ROLE_STUDENT,
            'email'=> 'student1@test.com',
            'organization_id'=> null,
            'birth_date' => '2006-01-01',
            'password' => 'password',
            'phone' => '+565787271',
            'avatar_path' => null,
        ]);

        User::create([
            'name'=> 'Yevhenii Shepetia 2',
            'role' => User::ROLE_STUDENT,
            'email'=> 'student2@test.com',
            'organization_id'=> null,
            'birth_date' => '2006-02-02',
            'password' => 'password',
            'phone' => '+565787272',
            'avatar_path' => null,
        ]);

        User::create([
            'name'=> 'Yevhenii Shepetia 3',
            'role' => User::ROLE_STUDENT,
            'email'=> 'student3@test.com',
            'organization_id'=> null,
            'birth_date' => '2006-03-03',
            'password' => 'password',
            'phone' => '+565787273',
            'avatar_path' => null,
        ]);

        // Company employees
        User::create([
            'name'=> 'Google Admin',
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'email'=> 'google_admin@gmail.com',
            'organization_id'=> $google->id,
            'birth_date' => '2002-03-11',
            'password' => 'password',
            'phone' => '+222222',
            'avatar_path' => null,
        ]);

        User::create([
            'name'=> 'Test organization Admin',
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'email'=> 'test_org_admin@gmail.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-13',
            'password' => 'password',
            'phone' => '+222222',
            'avatar_path' => null,
        ]);

        User::create([
            'name'=> 'Test organization Admin 2',
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'email'=> 'test_org_admin_2@gmail.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-13',
            'password' => 'password',
            'phone' => '+222222',
            'avatar_path' => null,
        ]);

        User::create([
            'name'=> 'Test organization employee',
            'role' => User::ROLE_ORGANIZATION_EMPLOYEE,
            'email'=> 'test_org_employee@gmail.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-13',
            'password' => 'password',
            'phone' => '+222222',
            'avatar_path' => null,
        ]);

        User::create([
            'name'=> 'Apple Employee',
            'role' => User::ROLE_ORGANIZATION_EMPLOYEE,
            'email'=> 'apple_employee@apple.com',
            'organization_id'=> $apple?->id,
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'phone' => '+368989138',
            'avatar_path' => null,
        ]);

        // NTI employess
        User::create([
            'name'=> 'Livia Kelebercova',
            'role' => User::ROLE_NTI_EMPLOYEE,
            'email'=> 'livia@email.com',
            'organization_id'=> null,
            'birth_date' => '1998-04-10',
            'password' => 'password',
            'phone' => '+344444',
            'avatar_path' => null,
        ]);

        // Deleted user
        User::create([
            'name'=> 'Deleted User',
            'role' => User::ROLE_STUDENT,
            'email'=> 'delted_user@email.com',
            'organization_id'=> null,
            'birth_date' => '1999-01-01',
            'password' => 'password',
            'phone' => '+000000',
            'avatar_path' => null,
            'deleted_at' => now()->addMicrosecond()
        ]);

        // Студенты для теста создания команд
        User::create([
            'name'=> 'Team Leader',
            'role' => User::ROLE_STUDENT,
            'email'=> 'teamleader@test.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'phone' => '+1111111111',
            'avatar_path' => null,
        ]);
        User::create([
            'name'=> 'Team Member 1',
            'role' => User::ROLE_STUDENT,
            'email'=> 'teammember1@test.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'phone' => '+1111111111',
            'avatar_path' => null,
        ]);
        User::create([
            'name'=> 'Team Member 2',
            'role' => User::ROLE_STUDENT,
            'email'=> 'teammember2@test.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'phone' => '+1111111111',
            'avatar_path' => null,
        ]);
        User::create([
            'name'=> 'Team Member 3',
            'role' => User::ROLE_STUDENT,
            'email'=> 'teammember3@test.com',
            'organization_id'=> null,
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'phone' => '+1111111111',
            'avatar_path' => null,
        ]);
    }
}
