<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\AuditParticipant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditEventSeeder extends Seeder
{
    public function run(): void
    {
        $aiProject = Project::where('name', 'AI Platform')->first();
        $dbProject = Project::where('name', 'Big BD creation')->first();
        $finishedProject = Project::where('name', 'Uber(finished)')->first();

        $mentorApple = User::where('email', 'apple_employee@apple.com')->first();
        $mentorGoogle = User::where('email', 'google_admin@gmail.com')->first();
        $ntiEmployee = User::where('email', 'livia@email.com')->first();
        $student1 = User::where('email', 'student1@test.com')->first();
        $student2 = User::where('email', 'student2@test.com')->first();
        $student3 = User::where('email', 'student3@test.com')->first();

        if (
            !$aiProject || !$dbProject || !$finishedProject
            || !$mentorApple || !$mentorGoogle || !$ntiEmployee
            || !$student1 || !$student2 || !$student3
        ) {
            $this->command?->warn('AuditEventSeeder: required records not found, skipped.');

            return;
        }

        $audit1 = AuditEvent::create([
            'project_id' => $aiProject->id,
            'result' => null,
            'main_auditor' => $mentorApple->id,
            'start_time' => now()->addMonths(2),
            'end_time' => now()->addMonths(2)->addHours(2),
        ]);

        AuditParticipant::create([
            'user_id' => $mentorApple->id,
            'audit_event_id' => $audit1->id,
            'role' => AuditParticipant::ROLE_AUDITOR,
        ]);
        AuditParticipant::create([
            'user_id' => $ntiEmployee->id,
            'audit_event_id' => $audit1->id,
            'role' => AuditParticipant::ROLE_AUDITOR,
        ]);
        AuditParticipant::create([
            'user_id' => $student1->id,
            'audit_event_id' => $audit1->id,
            'role' => AuditParticipant::ROLE_CONTRIBUTOR,
        ]);

        $audit2 = AuditEvent::create([
            'project_id' => $dbProject->id,
            'result' => null,
            'main_auditor' => $mentorGoogle->id,
            'start_time' => now()->addMonths(3),
            'end_time' => now()->addMonths(3)->addHours(3),
        ]);

        AuditParticipant::create([
            'user_id' => $mentorGoogle->id,
            'audit_event_id' => $audit2->id,
            'role' => AuditParticipant::ROLE_AUDITOR,
        ]);
        AuditParticipant::create([
            'user_id' => $ntiEmployee->id,
            'audit_event_id' => $audit2->id,
            'role' => AuditParticipant::ROLE_AUDITOR,
        ]);
        AuditParticipant::create([
            'user_id' => $student2->id,
            'audit_event_id' => $audit2->id,
            'role' => AuditParticipant::ROLE_CONTRIBUTOR,
        ]);
        AuditParticipant::create([
            'user_id' => $student3->id,
            'audit_event_id' => $audit2->id,
            'role' => AuditParticipant::ROLE_CONTRIBUTOR,
        ]);

        $audit3 = AuditEvent::create([
            'project_id' => $finishedProject->id,
            'result' => AuditEvent::RESULT_ACCEPTED,
            'main_auditor' => $mentorGoogle->id,
            'start_time' => now()->addMonths(4),
            'end_time' => now()->addMonths(4)->addHours(4),
        ]);

        AuditParticipant::create([
            'user_id' => $mentorGoogle->id,
            'audit_event_id' => $audit3->id,
            'role' => AuditParticipant::ROLE_AUDITOR,
        ]);
        AuditParticipant::create([
            'user_id' => $ntiEmployee->id,
            'audit_event_id' => $audit3->id,
            'role' => AuditParticipant::ROLE_AUDITOR,
        ]);
        AuditParticipant::create([
            'user_id' => $student1->id,
            'audit_event_id' => $audit3->id,
            'role' => AuditParticipant::ROLE_CONTRIBUTOR,
        ]);
        AuditParticipant::create([
            'user_id' => $student2->id,
            'audit_event_id' => $audit3->id,
            'role' => AuditParticipant::ROLE_CONTRIBUTOR,
        ]);
        AuditParticipant::create([
            'user_id' => $student3->id,
            'audit_event_id' => $audit3->id,
            'role' => AuditParticipant::ROLE_CONTRIBUTOR,
        ]);
    }
}
