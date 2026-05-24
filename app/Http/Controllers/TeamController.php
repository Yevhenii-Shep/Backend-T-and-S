<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamUser;
use App\Models\Project;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::query()->with('users');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STUDENT,], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:teams,slug'],
            'description' => ['nullable', 'string'],

            // admin может создать и неактивную команду
            'is_active' => ['nullable', 'boolean'],

            // admin может назначать leader
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Если студент уже в другой активной команде то нельзя создать новую
        if ($user->role === User::ROLE_STUDENT) {
            $alreadyInActiveTeam = $user->teams()
                ->where('teams.is_active', true)
                ->exists();

            abort_if($alreadyInActiveTeam,
                422, 'You are already in an active team'
            );

            // Студент сразу становиться лидером созданной команды
            $leaderId = $user->id;
            $isActive = true;
        } else {

            abort_unless(isset($data['leader_id']),
                422, 'Admin must specify leader_id'
            );

            $leaderUser = User::query()
                ->where('id', $data['leader_id'])
                ->whereNull('deleted_at')
                ->firstOrFail();

            // Админ может создать команду с любыми лидером(студентом)
            abort_unless($leaderUser->role === User::ROLE_STUDENT,
                422, 'Team leader must be a student'
            );

            // проверяем, что лидер не состоит в активной команде
            $leaderInActiveTeam = $leaderUser->teams()
                ->where('teams.is_active', true)
                ->exists();

            abort_if($leaderInActiveTeam,
                422, 'Selected leader already belongs to an active team'
            );

            $leaderId = $leaderUser->id;
            $isActive = $data['is_active'] ?? true;
        }

        $team = Team::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => $isActive,
        ]);

        TeamUser::create([
            'team_id' => $team->id,
            'user_id' => $leaderId,
            'join_date' => now(),
            'leave_date' => null,
            'is_leader' => true,
        ]);

        return response()->json(
            $team->load('users'),
            201
        );
    }

    public function show(Request $request, Team $team)
    {
        abort_unless($this->canAccessTeam($request->user(), $team), 
        403, 'Access denied');

        return response()->json(
            $team->load(['users', 'projects',])
        );
    }

    public function update(Request $request, Team $team)
    {
        $user = $request->user();

        // команда должна быть активной
        abort_if(!$team->is_active,
            422, 'Cannot update inactive team'
        );

        // Только admin и student могут обновлять команды
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STUDENT], true),
            403, 'Access denied'
        );

        // Проверка доступа к конкретной команде
        abort_unless($this->canManageTeam($user, $team),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teams', 'slug')->ignore($team->id)],
            'description' => ['nullable', 'string'],

            // Только admin может менять is_active
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Student не может менять is_active(для этого существует удадение команды)
        if ($user->role !== User::ROLE_ADMIN) {
            unset($data['is_active']);
        }

        $team->update($data);

        return response()->json(
            $team->load(['users', 'projects'])
        );
    }

    public function destroy(Request $request, Team $team)
    {
        $user = $request->user();

        abort_if(!$team->is_active,
            422, 'Team is already inactive'
        );

        // право на удаление(деактивацию)
        $canDisband =
            $user->role === User::ROLE_ADMIN
            || $this->isTeamMember($user, $team);

        abort_unless($canDisband,
            403, 'Only team members or admin can disband team'
        );

        // проверка активных проектов
        $hasActiveProjects = $team->projects()
            ->where('status', Project::STATUS_ACTIVE)
            ->exists();

        abort_if($hasActiveProjects,
            422, 'Cannot disband team with active projects'
        );

        // деактивация команды
        $team->update([
            'is_active' => false
        ]);

        // добаляем дату выхода для всех участников
        TeamUser::where('team_id', $team->id)
            ->whereNull('leave_date')
            ->update([
                'leave_date' => now()
        ]);

        return response()->json([
            'message' => 'Team disbanded successfully'
        ]);
    }

    private function canAccessTeam(User $user, Team $team): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_ADMIN ,User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        // студенты могут видеть только свои команды
        if ($user->role === User::ROLE_STUDENT) {
            return $this->isTeamMember($user, $team);
        }

        return false;
    }

    private function canManageTeam(User $user, Team $team): bool
    {
        if (!$team->is_active) {
            return false;
        }

        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        // только student leader может управлять
        if ($user->role === User::ROLE_STUDENT) {
            return $team->users()
                ->where('users.id', $user->id)
                ->wherePivot('is_leader', true)
                ->exists();
        }

        return false;
    }

    private function isTeamMember(User $user, Team $team): bool
    {
        return $team->users()
            ->where('users.id', $user->id)
            ->exists();
    }
}
