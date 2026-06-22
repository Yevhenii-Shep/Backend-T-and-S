<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Notifications\EvaluationReceivedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ProjectAcceptedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategory(): Category
    {
        return Category::create([
            'name' => 'AI',
            'slug' => 'ai',
            'description' => 'AI category',
        ]);
    }

    private function seedTeamWithMembers(): Team
    {
        $team = Team::create([
            'name' => 'Notify Team',
            'slug' => 'notify-team',
            'description' => 'Test team',
            'is_active' => true,
            'invite_code' => 'TESTCODE',
        ]);

        $student1 = User::create([
            'role' => User::ROLE_STUDENT,
            'name' => 'Student One',
            'email' => 'student1-notify@test.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
        ]);

        $student2 = User::create([
            'role' => User::ROLE_STUDENT,
            'name' => 'Student Two',
            'email' => 'student2-notify@test.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
        ]);

        $team->users()->attach($student1->id, [
            'join_date' => now(),
            'is_leader' => true,
        ]);
        $team->users()->attach($student2->id, [
            'join_date' => now(),
            'is_leader' => false,
        ]);

        return $team->load('users');
    }

    public function test_register_sends_welcome_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'New Student',
            'email' => 'newstudent@test.com',
            'birth_date' => '2001-05-05',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        Notification::assertSentTo(
            User::where('email', 'newstudent@test.com')->first(),
            WelcomeNotification::class
        );
    }

    public function test_change_password_sends_notification(): void
    {
        Notification::fake();

        $user = User::create([
            'role' => User::ROLE_STUDENT,
            'name' => 'Pwd User',
            'email' => 'pwd@test.com',
            'birth_date' => '2000-01-01',
            'password' => Hash::make('oldpassword'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk();

        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_accept_after_audit_notifies_team_members(): void
    {
        Notification::fake();

        $category = $this->seedCategory();
        $team = $this->seedTeamWithMembers();

        $org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'ico' => '12345678',
            'email' => 'org@test.com',
        ]);

        $orgAdmin = User::create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'name' => 'Org Admin',
            'email' => 'orgadmin@test.com',
            'birth_date' => '1990-01-01',
            'password' => 'password',
            'organization_id' => $org->id,
        ]);

        $project = Project::create([
            'name' => 'Pending Project',
            'slug' => 'pending-project',
            'team_id' => $team->id,
            'organization_id' => null,
            'program_type' => Project::PROGRAM_TYPE_A,
            'category_id' => $category->id,
            'status' => Project::STATUS_PENGING,
            'description' => 'Awaiting acceptance',
        ]);

        $auditor = User::create([
            'role' => User::ROLE_NTI_EMPLOYEE,
            'name' => 'Auditor',
            'email' => 'auditor@test.com',
            'birth_date' => '1990-01-01',
            'password' => 'password',
        ]);

        AuditEvent::create([
            'project_id' => $project->id,
            'result' => AuditEvent::RESULT_ACCEPTED,
            'main_auditor' => $auditor->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
        ]);

        Sanctum::actingAs($orgAdmin);

        $response = $this->patchJson('/api/projects/'.$project->slug.'/accept-after-audit');

        $response->assertOk();

        foreach ($team->users as $member) {
            Notification::assertSentTo($member, ProjectAcceptedNotification::class);
        }
    }

    public function test_evaluation_notifies_team_members(): void
    {
        Notification::fake();

        $category = $this->seedCategory();
        $team = $this->seedTeamWithMembers();

        $project = Project::create([
            'name' => 'Eval Project',
            'slug' => 'eval-project',
            'team_id' => $team->id,
            'organization_id' => null,
            'program_type' => Project::PROGRAM_TYPE_A,
            'category_id' => $category->id,
            'status' => Project::STATUS_ACTIVE,
            'description' => 'Active project',
        ]);

        $evaluator = User::create([
            'role' => User::ROLE_NTI_EMPLOYEE,
            'name' => 'Evaluator',
            'email' => 'evaluator@test.com',
            'birth_date' => '1990-01-01',
            'password' => 'password',
        ]);

        Sanctum::actingAs($evaluator);

        $response = $this->postJson('/api/evaluations', [
            'project_id' => $project->id,
            'score' => 88,
            'comment' => 'Solid progress',
        ]);

        $response->assertCreated();

        foreach ($team->users as $member) {
            Notification::assertSentTo($member, EvaluationReceivedNotification::class);
        }
    }
}
