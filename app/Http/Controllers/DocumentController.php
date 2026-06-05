<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use ChecksProjectAccess;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Document::query()->with('project');

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
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file_path' => ['required', 'string', 'max:500'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $data['is_active'] = true;
        $document = Document::create($data);

        return response()->json($document->load('project'), 201);
    }

    public function show(Request $request, Document $document)
    {
        abort_unless($document->is_active, 404);
        abort_unless($this->canAccessProject($request->user(), $document->project), 403, 'Access denied');

        return response()->json($document->load('project'));
    }

    public function update(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($document->is_active, 404);
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
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($document->is_active, 404);
        abort_unless($this->canAccessProject($user, $document->project), 403, 'Access denied');

        $document->update(['is_active' => false]);

        return response()->noContent();
    }
}
