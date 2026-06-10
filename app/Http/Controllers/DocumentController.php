<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CRUD документов проекта (удаление = is_active false).
 */
class DocumentController extends Controller
{
    use ChecksProjectAccess;

    /**
     * GET /api/documents — список документов (фильтр: project_id).
     */
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

    /**
     * POST /api/documents — добавить документ к проекту.
     */
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
        $this->assertSafeFilePath($data['file_path']);

        $data['is_active'] = true;
        $document = Document::create($data);

        return response()->json($document->load('project'), 201);
    }

    /**
     * GET /api/documents/{document} — один документ.
     */
    public function show(Request $request, Document $document)
    {
        abort_unless($document->is_active, 404);
        abort_unless($this->canAccessProject($request->user(), $document->project), 403, 'Access denied');

        return response()->json($document->load('project'));
    }

    /**
     * PUT/PATCH /api/documents/{document} — обновить метаданные документа.
     */
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

        if (isset($data['file_path'])) {
            $this->assertSafeFilePath($data['file_path']);
        }

        $document->update($data);

        return response()->json($document->load('project'));
    }

    /**
     * DELETE /api/documents/{document} — деактивация (is_active = false).
     */
    public function destroy(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($document->is_active, 404);
        abort_unless($this->canAccessProject($user, $document->project), 403, 'Access denied');

        $document->update(['is_active' => false]);

        return response()->noContent();
    }

    /** Запрет path traversal в file_path (.., абсолютные пути). */
    private function assertSafeFilePath(string $filePath): void
    {
        if (str_contains($filePath, '..') || str_starts_with($filePath, '/') || str_starts_with($filePath, '\\')) {
            throw ValidationException::withMessages([
                'file_path' => ['Invalid file path.'],
            ]);
        }
    }
}
