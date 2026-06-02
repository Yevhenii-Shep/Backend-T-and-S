<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;
use App\Http\Resources\TeamResource;
use App\Models\User;
use App\Models\TeamUser;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class TeamController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::query()
            ->select(["id", "name", "description", "is_active"]);

        // фильтр по статусу
        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        $teams = $query->get();
        return TeamResource::collection($teams);
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

            'invite_code' => strtoupper(Str::random(8)),
        ]);

        TeamUser::create([
            'team_id' => $team->id,
            'user_id' => $leaderId,
            'join_date' => now(),
            'leave_date' => null,
            'is_leader' => true,
        ]);

        return new TeamResource(
            $team->load('users')
        );
    }

    public function show(Request $request, Team $team)
    {
        abort_unless($this->canAccessTeam($request->user(), $team), 
            403, 'Access denied'
        );

        $team->load(['users', 'projects']);
        return new TeamResource($team);
    }

    // Отдельный endpoint который показывет invite_code(только админу или участникам комадны)
    public function inviteCode(Request $request, Team $team)
    {
        abort_unless($this->canManageTeam($request->user(), $team),
            403, 'Access denied'
        );

        return response()->json([
            'invite_code' => $team->invite_code,
        ]);
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
        ]);

        $team->update($data);

        return new TeamResource(
            $team->load(['users', 'projects'])
        );
    }

    // Отдельный endppoint для ре-генерации invite_code 
    public function regenerateInviteCode(Request $request, Team $team)
    {
        $user = $request->user();

        // команда должна быть активной
        abort_if(!$team->is_active,
            422, 'Team is inactive'
        );

        // Только admin и student могут обновлять команды
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STUDENT], true),
            403, 'Access denied'
        );

        // Проверка доступа к конкретной команде
        abort_unless($this->canManageTeam($user, $team),
            403, 'Access denied'
        );

        // генерация нового кода
        $team->update([
            'invite_code' => strtoupper(Str::random(8)),
        ]);

        return response()->json([
            'message' => 'Invite code regenerated successfully',
            'invite_code' => $team->invite_code,
        ]);
    }

    // Endpoint для вступления в команду по invite_code
    public function join(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_STUDENT,], true),
            403, 'Only students can join teams'
        );

        $data = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $team = Team::where('invite_code', $data['invite_code'])
            ->where('is_active', true)
            ->firstOrFail();

        // Уже в этой же команде
        abort_if(
            $team->users()->where('users.id', $user->id)->exists(),
            422, 'You are already in this team'
        );

        // В другой активной команде
        abort_if($user->teams()->where('teams.is_active', true)->exists(),
            422, 'You are already in another active team'
        );

        // Рроверка лимита участников в одной команде(4)
        $currentMembersCount = $team->users()->count();
        abort_if($currentMembersCount >= Team::MAX_PARTICIPANTS,
            422, 'Team is already full'
        );

        TeamUser::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'join_date' => now(),
            'leave_date' => null,
            'is_leader' => false,
        ]);

        return response()->json([
            'message' => 'Joined team successfully',
            'team' => new TeamResource($team->load('users'))
        ]);
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
