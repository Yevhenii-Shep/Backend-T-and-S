<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD этапов проекта (удаление = is_active false).
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
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
            $query->where('project_id', $project->id);
        }

        return response()->json($query->get());
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

        $data['is_active'] = true;
        $milestone = Milestone::create($data);

        return response()->json($milestone->load('project'), 201);
    }

    /**
     * GET /api/milestones/{milestone} — один этап.
     */
    public function show(Request $request, Milestone $milestone)
    {
        abort_unless($milestone->is_active, 404);
        abort_unless($this->canAccessProject($request->user(), $milestone->project), 403, 'Access denied');

        return response()->json($milestone->load('project'));
    }

    /**
     * PUT/PATCH /api/milestones/{milestone} — обновить этап.
     */
    public function update(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($milestone->is_active, 404);
        abort_unless($this->canAccessProject($user, $milestone->project), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'integer', Rule::in($this->milestoneStatuses())],
            'deadline' => ['nullable', 'date'],
        ]);

        if (isset($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
        }

        $milestone->update($data);

        return response()->json($milestone->load('project'));
    }

    /**
     * DELETE /api/milestones/{milestone} — деактивация (is_active = false).
     */
    public function destroy(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($milestone->is_active, 404);
        abort_unless($this->canAccessProject($user, $milestone->project), 403, 'Access denied');

        $milestone->update(['is_active' => false]);

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
