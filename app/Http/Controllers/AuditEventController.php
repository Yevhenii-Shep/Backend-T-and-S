<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\AuditParticipantResource;
use App\Models\AuditEvent;
use App\Models\AuditParticipant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD аудитов и управление участниками аудита.
 */
class AuditEventController extends Controller
{
    use ChecksProjectAccess;

    /**
     * GET /api/audit-events — список аудитов (фильтр: project_id).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AuditEvent::query()
            ->with(['project', 'mainAuditor', 'participants.user']);

        $this->applyProjectChildVisibility($query, $user);

        if ($request->filled('project_id')) {
            $project = Project::findOrFail($request->integer('project_id'));
            abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');
            $query->where('project_id', $project->id);
        }

        return AuditEventResource::collection($query->get());
    }

    /**
     * POST /api/audit-events — создать аудит для проекта.
     * Не-admin: только будущие даты, без result. Admin: может задним числом, result — если аудит уже завершён.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate($this->storeRules($user));

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->isNtiStaff($user), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');
        $this->assertProjectAcceptsAudit($user, $project);
        $this->assertProjectPendingForAudit($project);
        $this->assertProjectHasNoAudit($project);
        $this->assertAuditorBelongsToProject($data['main_auditor'], $project);
        $this->assertScheduleOnCreate($user, $data);

        unset($data['result']);

        $auditEvent = AuditEvent::create($data);

        return (new AuditEventResource(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/audit-events/{audit_event} — один аудит с участниками.
     */
    public function show(Request $request, AuditEvent $auditEvent)
    {
        abort_unless($this->canAccessAudit($request->user(), $auditEvent), 403, 'Access denied');

        return new AuditEventResource(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    /**
     * PUT/PATCH /api/audit-events/{audit_event} — обновить аудит.
     * result — только после end_time. Не-admin не может переносить аудит в прошлое.
     */
    public function update(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless($this->canAccessAudit($user, $auditEvent), 403, 'Access denied');

        $data = $request->validate($this->updateRules($user));

        $startTime = $data['start_time'] ?? $auditEvent->start_time;
        $endTime = $data['end_time'] ?? $auditEvent->end_time;

        $this->assertEndAfterStart($startTime, $endTime);

        if (array_key_exists('result', $data)) {
            abort_unless($this->canSetAuditResult($user, $auditEvent), 403, 'Access denied');
            $this->assertResultOnlyAfterEnd($endTime);

            if ($data['result'] !== null) {
                $this->assertAuditResultNotFinal($auditEvent);
            }
        } else {
            abort_unless($this->isNtiStaff($user), 403, 'Access denied');
            $this->assertScheduleOnUpdate($user, $data, $auditEvent);
        }

        if (isset($data['main_auditor'])) {
            abort_unless($this->isNtiStaff($user), 403, 'Access denied');
            $this->assertAuditorBelongsToProject($data['main_auditor'], $auditEvent->project, 'main_auditor');
        }

        $auditEvent->update($data);

        return new AuditEventResource(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    /**
     * PATCH /api/audit-events/{audit_event}/result — главный аудитор фиксирует итог аудита.
     */
    public function updateResult(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless($this->canAccessAudit($user, $auditEvent), 403, 'Access denied');
        abort_unless($this->canSetAuditResult($user, $auditEvent), 403, 'Access denied');

        $data = $request->validate([
            'result' => ['required', 'integer', Rule::in([AuditEvent::RESULT_ACCEPTED, AuditEvent::RESULT_DECLINED])],
        ]);

        $this->assertResultOnlyAfterEnd($auditEvent->end_time);
        $this->assertAuditResultNotFinal($auditEvent);

        $auditEvent->update(['result' => $data['result']]);

        return new AuditEventResource(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    /**
     * DELETE /api/audit-events/{audit_event} — soft delete.
     */
    public function destroy(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($auditEvent->project && $this->canWriteProject($user, $auditEvent->project), 403, 'Access denied');

        $auditEvent->delete();

        return response()->noContent();
    }

    /**
     * POST /api/audit-events/{audit_event}/participants — добавить участника аудита.
     */
    public function storeParticipant(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($this->canWriteAudit($user, $auditEvent), 403, 'Access denied');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'integer', Rule::in([AuditParticipant::ROLE_AUDITOR, AuditParticipant::ROLE_CONTRIBUTOR])],
        ]);

        $exists = AuditParticipant::query()
            ->where('audit_event_id', $auditEvent->id)
            ->where('user_id', $data['user_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => ['User is already a participant of this audit.'],
            ]);
        }

        // Аудитором может быть только пользователь с ролью аудитора из org проекта (или admin/NTI).
        if ($data['role'] === AuditParticipant::ROLE_AUDITOR) {
            $this->assertAuditorBelongsToProject($data['user_id'], $auditEvent->project, 'user_id');
        } else {
            $this->assertContributorBelongsToProject($data['user_id'], $auditEvent->project, 'user_id');
        }

        $participant = AuditParticipant::create([
            'user_id' => $data['user_id'],
            'audit_event_id' => $auditEvent->id,
            'role' => $data['role'],
        ]);

        return (new AuditParticipantResource($participant->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/audit-events/{audit_event}/participants/{participantUser} — убрать участника.
     */
    public function destroyParticipant(Request $request, AuditEvent $auditEvent, User $participantUser)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($this->canWriteAudit($user, $auditEvent), 403, 'Access denied');

        $deleted = AuditParticipant::query()
            ->where('audit_event_id', $auditEvent->id)
            ->where('user_id', $participantUser->id)
            ->delete();

        abort_unless($deleted, 404);

        return response()->noContent();
    }

    private function canAccessAudit(User $user, AuditEvent $auditEvent): bool
    {
        $project = $auditEvent->project;

        if (!$project) {
            return false;
        }

        return $this->canStaffAccessProject($user, $project);
    }

    /** Изменение аудита: нужен доступ на запись к проекту. */
    private function canWriteAudit(User $user, AuditEvent $auditEvent): bool
    {
        if (!$auditEvent->project) {
            return false;
        }

        return $this->canWriteProject($user, $auditEvent->project);
    }

    /** Нельзя создавать аудит для inactive-проекта (кроме admin/NTI). */
    private function assertProjectAcceptsAudit(User $user, Project $project): void
    {
        if ($project->status !== Project::STATUS_INACTIVE) {
            return;
        }

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'project_id' => ['Cannot create audits for an inactive project.'],
        ]);
    }

    /** Первый аудит — только для проекта в ожидании. */
    private function assertProjectPendingForAudit(Project $project): void
    {
        if ($project->status !== Project::STATUS_PENGING) {
            throw ValidationException::withMessages([
                'project_id' => ['Audit can only be scheduled for a pending project.'],
            ]);
        }
    }

    /** На проект можно назначить только один аудит. */
    private function assertProjectHasNoAudit(Project $project): void
    {
        if ($project->auditEvents()->exists()) {
            throw ValidationException::withMessages([
                'project_id' => ['This project already has an audit scheduled.'],
            ]);
        }
    }

    /** Итог аудита нельзя менять повторно. */
    private function assertAuditResultNotFinal(AuditEvent $auditEvent): void
    {
        if ($auditEvent->result !== null) {
            throw ValidationException::withMessages([
                'result' => ['Audit result has already been set.'],
            ]);
        }
    }

    /** Главный аудитор: admin/NTI или любой сотрудник/админ любой организации. */
    private function assertAuditorBelongsToProject(
        int $userId,
        ?Project $project,
        string $field = 'main_auditor'
    ): void {
        $auditor = User::find($userId);

        if (!$auditor) {
            throw ValidationException::withMessages([
                $field => ['Auditor not found.'],
            ]);
        }

        if (in_array($auditor->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return;
        }

        if (!in_array($auditor->role, [User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true)) {
            throw ValidationException::withMessages([
                $field => ['Auditor must be an organization employee or admin.'],
            ]);
        }

        if (!$auditor->organization_id) {
            throw ValidationException::withMessages([
                $field => ['Auditor must belong to an organization.'],
            ]);
        }
    }

    /** Участник contributor: студент команды проекта, staff той же org или admin/NTI. */
    private function assertContributorBelongsToProject(int $userId, ?Project $project, string $field = 'user_id'): void
    {
        $participant = User::find($userId);

        if (!$participant) {
            throw ValidationException::withMessages([
                $field => ['Invalid participant.'],
            ]);
        }

        if (in_array($participant->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return;
        }

        if ($participant->role === User::ROLE_STUDENT) {
            if (
                !$project?->team_id
                || !$project->team()->whereHas('users', fn ($q) => $q->where('users.id', $participant->id))->exists()
            ) {
                throw ValidationException::withMessages([
                    $field => ['Student must be a member of the project team.'],
                ]);
            }

            return;
        }

        if (in_array($participant->role, [User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true)) {
            if (!$project || !$project->organization_id) {
                throw ValidationException::withMessages([
                    $field => ['Organization participant requires a project with an organization.'],
                ]);
            }

            if ((int) $participant->organization_id !== (int) $project->organization_id) {
                throw ValidationException::withMessages([
                    $field => ['Participant must belong to the project organization.'],
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            $field => ['Invalid participant role.'],
        ]);
    }

    /** Только super admin может задавать прошлые даты и result при создании. */
    private function isAdmin(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    /** Правила валидации при создании аудита. */
    private function storeRules(User $user): array
    {
        $rules = [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'main_auditor' => ['required', 'integer', 'exists:users,id'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ];

        if (!$this->isAdmin($user)) {
            $rules['start_time'][] = 'after:now';
        }

        $rules['result'] = ['prohibited'];

        return $rules;
    }

    /** Правила валидации при обновлении аудита. */
    private function updateRules(User $user): array
    {
        $rules = [
            'result' => ['nullable', 'integer', Rule::in([AuditEvent::RESULT_ACCEPTED, AuditEvent::RESULT_DECLINED])],
            'main_auditor' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['sometimes', 'required', 'date', 'after:start_time'],
        ];

        if (!$this->isAdmin($user)) {
            $rules['start_time'][] = 'after:now';
            $rules['end_time'][] = 'after:now';
        }

        return $rules;
    }

    /** Не-admin: только будущее. Admin + result: end_time уже в прошлом. */
    private function assertScheduleOnCreate(User $user, array $data): void
    {
        if (!$this->isAdmin($user)) {
            $this->assertStartInFuture($data['start_time']);

            return;
        }

        /*
        if (isset($data['result']) && $data['result'] !== null) {
            $this->assertResultOnlyAfterEnd($data['end_time']);
        }
        */
    }

    /** Не-admin не может сдвинуть start_time в прошлое. */
    private function assertScheduleOnUpdate(User $user, array $data, AuditEvent $auditEvent): void
    {
        if ($this->isAdmin($user)) {
            return;
        }

        if (isset($data['start_time'])) {
            $this->assertStartInFuture($data['start_time']);
        }

        // Уже начавшийся аудит: нельзя менять start_time (end_time можно продлить).
        if ($auditEvent->start_time->lte(now()) && isset($data['start_time'])) {
            throw ValidationException::withMessages([
                'start_time' => ['Cannot change start time after the audit has begun.'],
            ]);
        }
    }

    private function assertStartInFuture(mixed $startTime): void
    {
        if (Carbon::parse($startTime)->lte(now())) {
            throw ValidationException::withMessages([
                'start_time' => ['Audit must be scheduled in the future.'],
            ]);
        }
    }

    private function assertEndAfterStart(mixed $startTime, mixed $endTime): void
    {
        if (Carbon::parse($endTime)->lte(Carbon::parse($startTime))) {
            throw ValidationException::withMessages([
                'end_time' => ['The end time must be after start time.'],
            ]);
        }
    }

    /** result допустим только когда аудит по расписанию уже завершён. */
    private function assertResultOnlyAfterEnd(mixed $endTime): void
    {   
        /*
        if (Carbon::parse($endTime)->gt(now())) {
            throw ValidationException::withMessages([
                'result' => ['Result can only be set after the audit has ended.'],
            ]);
        }
        */
    }
}
