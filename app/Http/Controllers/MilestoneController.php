<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Http\Resources\MilestoneResource;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD этапов проекта (удаление = soft delete).
 */
class MilestoneController extends Controller
{
    use ChecksProjectAccess;

    /**
     * GET /api/milestones — список этапов (фильтр: project_id).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Milestone::query()->with('project');

        $this->applyProjectChildVisibility($query, $user);

        if ($request->filled('project_id')) {
            $project = Project::findOrFail($request->integer('project_id'));
            abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');
            $query->where('project_id', $project->id);
        }

        return MilestoneResource::collection($query->get());
    }

    /**
     * POST /api/milestones — создать этап.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', Rule::in($this->milestoneStatuses())],
            'deadline' => ['nullable', 'date'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canWriteProject($user, $project), 403, 'Access denied');
        $this->assertProjectAcceptsMilestone($user, $project);

        $milestone = Milestone::create($data);

        return (new MilestoneResource($milestone->load('project')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/milestones/{milestone} — один этап.
     */
    public function show(Request $request, Milestone $milestone)
    {
        abort_unless($this->canAccessMilestone($request->user(), $milestone), 403, 'Access denied');

        return new MilestoneResource($milestone->load('project'));
    }

    /**
     * PUT/PATCH /api/milestones/{milestone} — обновить этап (без status).
     */
    public function update(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless(
            $milestone->project && $this->canWriteProject($user, $milestone->project),
            403,
            'Access denied'
        );

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        $milestone->update($data);

        return new MilestoneResource($milestone->load('project'));
    }

    /**
     * PATCH /api/milestones/{milestone}/status — смена статуса этапа.
     */
    public function updateStatus(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless(
            $milestone->project && $this->canWriteProject($user, $milestone->project),
            403,
            'Access denied'
        );

        $data = $request->validate([
            'status' => ['required', 'integer', Rule::in($this->milestoneStatuses())],
        ]);

        $milestone->update(['status' => $data['status']]);

        return new MilestoneResource($milestone->load('project'));
    }

    /**
     * DELETE /api/milestones/{milestone} — soft delete.
     */
    public function destroy(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless(
            $milestone->project && $this->canWriteProject($user, $milestone->project),
            403,
            'Access denied'
        );

        $milestone->delete();

        return response()->noContent();
    }

    private function canAccessMilestone(User $user, Milestone $milestone): bool
    {
        if (!$milestone->project) {
            return false;
        }

        return $this->canStaffAccessProject($user, $milestone->project);
    }

    /** Нельзя создавать этапы в inactive-проекте (кроме admin/NTI). */
    private function assertProjectAcceptsMilestone(User $user, Project $project): void
    {
        if ($project->status !== Project::STATUS_INACTIVE) {
            return;
        }

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'project_id' => ['Cannot create milestones for an inactive project.'],
        ]);
    }

    /** Допустимые статусы этапа (константы Milestone). */
    private function milestoneStatuses(): array
    {
        return [
            Milestone::STATUS_PENDING,
            Milestone::STATUS_IN_PROGRESS,
            Milestone::STATUS_DONE,
            Milestone::STATUS_FAILED,
        ];
    }
}
