<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamUser;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::create([
            'name' => 'Test Team',
            'slug' => 'test-team',
            'description' => 'jfsdfjkasjahslfhalkfal',
            'is_active' => 1,
        ]);

        $student1 = User::where('email', 'student1@test.com')->first();
        $student2 = User::where('email', 'student2@test.com')->first();
        $student3 = User::where('email', 'student3@test.com')->first();

        // связываем через pivot (team_user)
        $team->users()->attach($student1->id, [
            'join_date' => now(),
            'leave_date' => null,
            'is_leader' => 1,
        ]);
        $team->users()->attach($student2->id, [
            'join_date' => now(),
            'leave_date' => null,
            'is_leader' => 0,
        ]);
        
        // Так лучше делать(attach - прямой insert в таблицу, create - через модель)
        TeamUser::create([
            'team_id' => $team->id,
            'user_id' => $student3->id,
            'join_date' => now(),
            'leave_date' => null,
            'is_leader' => 0,
        ]);

        
    }
}
