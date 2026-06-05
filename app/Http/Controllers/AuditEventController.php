<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    use ChecksProjectAccess;

    public function index(Request $request)
    {
        $user = $request->user();

        $query = AuditEvent::query()
            ->with(['project', 'mainAuditor', 'participants.user'])
            ->where('is_active', true);

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return response()->json($query->get());
        }

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return response()->json(
                $query->whereHas(
                    'project',
                    fn ($projects) => $projects
                        ->where('organization_id', $user->organization_id)
                        ->where('status', '!=', Project::STATUS_INACTIVE)
                )->get()
            );
        }

        if ($user->role === User::ROLE_STUDENT) {
            return response()->json(
                $query->whereHas(
                    'project',
                    fn ($projects) => $projects
                        ->where('status', '!=', Project::STATUS_INACTIVE)
                        ->whereHas('team.users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))
                )->get()
            );
        }

        return response()->json(collect());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'result' => ['nullable', 'integer', 'in:1,2'],
            'main_auditor' => ['required', 'integer', 'exists:users,id'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $data['is_active'] = true;
        $auditEvent = AuditEvent::create($data);

        return response()->json(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user']),
            201
        );
    }

    public function show(Request $request, AuditEvent $auditEvent)
    {
        abort_unless($auditEvent->is_active, 404);
        abort_unless($this->canAccessAudit($request->user(), $auditEvent), 403, 'Access denied');

        return response()->json(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    public function update(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($auditEvent->is_active, 404);
        abort_unless($this->canAccessAudit($user, $auditEvent), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'result' => ['nullable', 'integer', 'in:1,2'],
            'main_auditor' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['sometimes', 'required', 'date', 'after:start_time'],
        ]);

        if (isset($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
        }

        $auditEvent->update($data);

        return response()->json(
            $auditEvent->load(['project', 'mainAuditor', 'participants.user'])
        );
    }

    public function destroy(Request $request, AuditEvent $auditEvent)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($auditEvent->is_active, 404);
        abort_unless($this->canAccessAudit($user, $auditEvent), 403, 'Access denied');

        $auditEvent->update(['is_active' => false]);

        return response()->noContent();
    }

    private function canAccessAudit(User $user, AuditEvent $auditEvent): bool
    {
        if (!$auditEvent->is_active) {
            return false;
        }

        $project = $auditEvent->project;

        if (!$project || $project->status === Project::STATUS_INACTIVE) {
            return false;
        }

        return $this->canAccessProject($user, $project);
    }
}
