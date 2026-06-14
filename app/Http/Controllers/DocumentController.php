<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD документов проекта (удаление = soft delete).
 * Загрузка файла: multipart/form-data, поле file.
 */
class DocumentController extends Controller
{
    use ChecksProjectAccess;

    private const DOCUMENT_DISK = 'public';

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

        return DocumentResource::collection($query->get());
    }

    /**
     * POST /api/documents — загрузить документ к проекту (multipart: file, project_id, name?, description?).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', $this->documentFileRule()],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $document = Document::create([
            'project_id' => $project->id,
            'name' => $data['name'] ?? $data['file']->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'file_path' => $this->storeUploadedFile($data['file'], $project->id),
        ]);

        return (new DocumentResource($document->load('project')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/documents/{document} — один документ.
     */
    public function show(Request $request, Document $document)
    {
        abort_unless($this->canAccessProject($request->user(), $document->project), 403, 'Access denied');

        return new DocumentResource($document->load('project'));
    }

    /**
     * GET /api/documents/{document}/download — скачать файл документа.
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        abort_unless($this->canAccessProject($request->user(), $document->project), 403, 'Access denied');
        abort_unless(Storage::disk(self::DOCUMENT_DISK)->exists($document->file_path), 404);

        return Storage::disk(self::DOCUMENT_DISK)->download(
            $document->file_path,
            basename($document->file_path)
        );
    }

    /**
     * PUT/PATCH /api/documents/{document} — обновить метаданные; опционально новый file.
     */
    public function update(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $document->project), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['sometimes', 'required', 'file', $this->documentFileRule()],
        ]);

        if (isset($data['file'])) {
            $this->deleteStoredFile($document->file_path);
            $data['file_path'] = $this->storeUploadedFile($data['file'], $document->project_id);
            unset($data['file']);
        }

        $document->update($data);

        return new DocumentResource($document->load('project'));
    }

    /**
     * DELETE /api/documents/{document} — soft delete и удаление файла с диска.
     */
    public function destroy(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_unless($this->canManageProject($user, $document->project), 403, 'Access denied');

        $this->deleteStoredFile($document->file_path);
        $document->delete();

        return response()->noContent();
    }

    private function documentFileRule(): File
    {
        return File::types([
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'svg', 'png', 'jpg', 'jpeg', 'zip', 'sql',
        ])->max(10 * 1024);
    }

    private function storeUploadedFile(UploadedFile $file, int $projectId): string
    {
        return $file->store('documents/'.$projectId, self::DOCUMENT_DISK);
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::disk(self::DOCUMENT_DISK)->exists($path)) {
            Storage::disk(self::DOCUMENT_DISK)->delete($path);
        }
    }
}
