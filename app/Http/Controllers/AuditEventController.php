<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = AuditEvent::query()->with(['project', 'mainAuditor', 'participants.user']);

        // Внутренние роли видят все аудиты.
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return response()->json($query->get());
        }

        // Админ организации видит только аудиты своих проектов.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return response()->json(
                $query->whereHas('project', fn ($projects) => $projects->where('organization_id', $user->organization_id))->get()
            );
        }

        // Студент видит только аудиты проектов своей команды.
        if ($user->role === User::ROLE_STUDENT) {
            return response()->json(
                $query->whereHas('project.team.users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))->get()
            );
        }

        return response()->json(collect());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'result' => ['nullable', 'integer', 'in:1,2'],
            'main_auditor' => ['required', 'integer', 'exists:users,id'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ]);

        // Временной интервал валидируется на входе.
        $auditEvent = AuditEvent::create($data);

        return response()->json(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user']),
            201
        );
    }

    public function show(Request $request, AuditEvent $auditEvent)
    {
        abort_unless($this->canAccessAudit($request->user(), $auditEvent), 403, 'Access denied');

        return response()->json(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    public function update(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');
        abort_unless($this->canAccessAudit($user, $auditEvent), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'result' => ['nullable', 'integer', 'in:1,2'],
            'main_auditor' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['sometimes', 'required', 'date', 'after:start_time'],
        ]);

        $auditEvent->update($data);

        return response()->json(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    public function destroy(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true), 403, 'Access denied');
        abort_unless($this->canAccessAudit($user, $auditEvent), 403, 'Access denied');

        $auditEvent->delete();

        return response()->noContent();
    }

    private function canAccessAudit(User $user, AuditEvent $auditEvent): bool
    {
        // Роли с глобальным доступом.
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        // Админ организации работает только с аудитами своей организации.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return $auditEvent->project()
                ->where('organization_id', $user->organization_id)
                ->exists();
        }

        // Доступ студента зависит от участия в команде проекта аудита.
        if ($user->role === User::ROLE_STUDENT) {
            return $auditEvent->project()
                ->whereHas('team.users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }
}
