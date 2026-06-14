<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CRUD оценок проекта (один оценщик — одна оценка на проект).
 */
class EvaluationController extends Controller
{
    use ChecksProjectAccess;

    /**
     * GET /api/evaluations — список оценок (фильтр: project_id).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Evaluation::query()->with(['project', 'evaluator']);

        $this->applyEvaluationVisibility($query, $user);

        if ($request->filled('project_id')) {
            $project = Project::findOrFail($request->integer('project_id'));
            abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');
            $query->where('project_id', $project->id);
        }

        return EvaluationResource::collection($query->get());
    }

    /**
     * POST /api/evaluations — поставить оценку проекту.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'evaluator_id' => ['nullable', 'integer', 'exists:users,id'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');
        $this->assertProjectAcceptsEvaluation($user, $project);

        // Только admin может назначить другого оценщика.
        if ($user->role !== User::ROLE_ADMIN) {
            $data['evaluator_id'] = $user->id;
        } else {
            $data['evaluator_id'] = $data['evaluator_id'] ?? $user->id;
        }

        $this->assertEvaluatorCanRate($data['evaluator_id']);

        if (Evaluation::where('project_id', $data['project_id'])->where('evaluator_id', $data['evaluator_id'])->exists()) {
            throw ValidationException::withMessages([
                'evaluator_id' => ['This evaluator already rated this project.'],
            ]);
        }

        $evaluation = Evaluation::create($data);

        return (new EvaluationResource($evaluation->load(['project', 'evaluator'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/evaluations/{evaluation} — одна оценка.
     */
    public function show(Request $request, Evaluation $evaluation)
    {
        abort_unless($this->canAccessEvaluation($request->user(), $evaluation), 403, 'Access denied');

        return new EvaluationResource($evaluation->load(['project', 'evaluator']));
    }

    /**
     * PUT/PATCH /api/evaluations/{evaluation} — изменить оценку.
     */
    public function update(Request $request, Evaluation $evaluation)
    {
        $user = $request->user();
        abort_unless($this->canModifyEvaluation($user, $evaluation), 403, 'Access denied');
        abort_unless($this->canAccessEvaluation($user, $evaluation), 403, 'Access denied');

        $data = $request->validate([
            'score' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
        ]);

        $evaluation->update($data);

        return new EvaluationResource($evaluation->load(['project', 'evaluator']));
    }

    /**
     * DELETE /api/evaluations/{evaluation} — удалить оценку.
     */
    public function destroy(Request $request, Evaluation $evaluation)
    {
        $user = $request->user();
        abort_unless($this->canModifyEvaluation($user, $evaluation), 403, 'Access denied');
        abort_unless($this->canAccessEvaluation($user, $evaluation), 403, 'Access denied');

        $evaluation->delete();

        return response()->noContent();
    }

    /** Чтение оценки: автор оценки или доступ к проекту (admin/NTI — включая inactive). */
    private function canAccessEvaluation(User $user, Evaluation $evaluation): bool
    {
        if ((int) $evaluation->evaluator_id === (int) $user->id) {
            return true;
        }

        if (!$evaluation->project) {
            return false;
        }

        return $this->canStaffAccessProject($user, $evaluation->project);
    }

    /** Нельзя оценивать inactive-проект (кроме admin/NTI). */
    private function assertProjectAcceptsEvaluation(User $user, Project $project): void
    {
        if ($project->status !== Project::STATUS_INACTIVE) {
            return;
        }

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'project_id' => ['Cannot evaluate an inactive project.'],
        ]);
    }

    /** Менять оценку может автор (с правом оценивать) или admin. */
    private function canModifyEvaluation(User $user, Evaluation $evaluation): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        if (!$this->canModifyResources($user)) {
            return false;
        }

        return (int) $evaluation->evaluator_id === (int) $user->id;
    }

    /** Оценщик — не студент (admin, org, NTI). */
    private function assertEvaluatorCanRate(int $evaluatorId): void
    {
        $evaluator = User::find($evaluatorId);

        if (!$evaluator || !$this->canModifyResources($evaluator)) {
            throw ValidationException::withMessages([
                'evaluator_id' => ['User cannot evaluate projects.'],
            ]);
        }
    }
}
