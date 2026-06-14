<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Http\Resources\MilestoneResource;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD этапов проекта (удаление = soft delete).
 */
class MilestoneController extends Controller
{
    use ChecksProjectAccess;

    /**
     * GET /api/milestones — список этапов (фильтр: project_id или project_slug).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Milestone::query()->with('project');

        $this->applyProjectChildVisibility($query, $user);

        if ($project = $this->resolveProjectFromFilter($request)) {
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
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
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

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
        abort_unless($this->canAccessProject($request->user(), $milestone->project), 403, 'Access denied');

        return new MilestoneResource($milestone->load('project'));
    }

    /**
     * PUT/PATCH /api/milestones/{milestone} — обновить этап.
     */
    public function update(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $milestone->project), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('milestones', 'slug')->ignore($milestone->id)],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'integer', Rule::in($this->milestoneStatuses())],
            'deadline' => ['nullable', 'date'],
        ]);

        $milestone->update($data);

        return new MilestoneResource($milestone->load('project'));
    }

    /**
     * DELETE /api/milestones/{milestone} — soft delete.
     */
    public function destroy(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($this->canManageProject($user, $milestone->project), 403, 'Access denied');

        $milestone->delete();

        return response()->noContent();
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
