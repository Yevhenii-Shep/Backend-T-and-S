<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    use ChecksProjectAccess;

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

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1,2,3'],
            'deadline' => ['nullable', 'date'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $data['is_active'] = true;
        $milestone = Milestone::create($data);

        return response()->json($milestone->load('project'), 201);
    }

    public function show(Request $request, Milestone $milestone)
    {
        abort_unless($milestone->is_active, 404);
        abort_unless($this->canAccessProject($request->user(), $milestone->project), 403, 'Access denied');

        return response()->json($milestone->load('project'));
    }

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
            'status' => ['sometimes', 'required', 'integer', 'in:0,1,2,3'],
            'deadline' => ['nullable', 'date'],
        ]);

        if (isset($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
        }

        $milestone->update($data);

        return response()->json($milestone->load('project'));
    }

    public function destroy(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($milestone->is_active, 404);
        abort_unless($this->canAccessProject($user, $milestone->project), 403, 'Access denied');

        $milestone->update(['is_active' => false]);

        return response()->noContent();
    }
}
