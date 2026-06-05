<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use ChecksProjectAccess;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Project::query()
            ->with(['team', 'organization', 'category']);

        $this->applyProjectVisibility($query, $user);

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['required', 'integer'],
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['required', 'integer', Rule::in($this->settableProjectStatuses())],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        $project = Project::create($data);

        return response()->json(
            $project->load(['team', 'organization', 'category']),
            201
        );
    }

    public function show(Request $request, Project $project)
    {
        abort_unless($this->canAccessProject($request->user(), $project), 403, 'Access denied');

        return response()->json(
            $project->load([
                'team',
                'organization',
                'category',
                'documents' => fn ($query) => $query->where('is_active', true),
                'milestones' => fn ($query) => $query->where('is_active', true),
                'auditEvents' => fn ($query) => $query->where('is_active', true),
            ])
        );
    }

    public function update(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($project->id)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['sometimes', 'required', 'integer'],
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', 'required', 'integer', Rule::in($this->settableProjectStatuses())],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        $project->update($data);

        return response()->json(
            $project->load([
                'team',
                'organization',
                'category',
                'documents' => fn ($query) => $query->where('is_active', true),
                'milestones' => fn ($query) => $query->where('is_active', true),
                'auditEvents' => fn ($query) => $query->where('is_active', true),
            ])
        );
    }

    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_if($project->status === Project::STATUS_INACTIVE, 404);
        abort_unless($this->canManageProject($user, $project), 403, 'Access denied');

        $project->update(['status' => Project::STATUS_INACTIVE]);

        return response()->noContent();
    }
}
