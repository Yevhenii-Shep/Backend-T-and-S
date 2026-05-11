<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::query()->with('project');

        // Опциональный фильтр по проекту.
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file_path' => ['required', 'string', 'max:500'],
        ]);

        // Перед созданием повторно проверяем доступ через проект.
        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $document = Document::create($data);

        return response()->json($document->load('project'), 201);
    }

    public function show(Request $request, Document $document)
    {
        abort_unless($this->canAccessProject($request->user(), $document->project), 403, 'Access denied');

        return response()->json($document->load('project'));
    }

    public function update(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $document->project), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file_path' => ['sometimes', 'required', 'string', 'max:500'],
        ]);

        if (isset($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
        }

        $document->update($data);

        return response()->json($document->load('project'));
    }

    public function destroy(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $document->project), 403, 'Access denied');

        $document->delete();

        return response()->noContent();
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        // Роли с глобальным доступом.
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        // Админ организации работает только со своими проектами.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return (int) $project->organization_id === (int) $user->organization_id;
        }

        // Доступ студента определяется членством в команде.
        if ($user->role === User::ROLE_STUDENT) {
            return $project->team()
                ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }
}
