<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubjectResource;
use App\Http\Resources\UserResource;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

/**
 * CRUD пользователей и оценки по предметам (pivot student_subject).
 *
 * Матрица прав:
 * | Действие              | Admin | NTI | Org admin | Org employee | Student |
 * |-----------------------|-------|-----|-----------|--------------|---------|
 * | GET /users (список)   |  ✓    |  ✓  |           |              |         |
 * | POST/DELETE /users    |  ✓    |  ✓  |           |              |         |
 * | PATCH чужого профиля  |  ✓    |  ✓* |           |              |         |
 * | PATCH свой профиль    |  ✓    |  ✓  |  ✓        |  ✓           |  ✓      |
 * | GET /users/{id}       |  все  | все | своя org  | своя org     |  себя   |
 * | Оценки по предметам   |  ✓    |  ✓  |           |              | читать  |
 *
 * * NTI не трогает существующих admin и NTI.
 * Регистрация студента: POST /register (публичный).
 */
class UserController extends Controller
{
    private const AVATAR_DISK = 'public';

    /** GET /api/users — список (фильтры: role, organization_id). */
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless($this->canListUsers($actor), 403, 'Access denied');

        $query = User::query()->with('organization');

        if ($request->filled('role')) {
            $query->where('role', $request->integer('role'));
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->integer('organization_id'));
        }

        return UserResource::collection($query->get());
    }

    /** POST /api/users/me/avatar — загрузить или заменить свой аватар. */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'avatar' => ['required', 'file', $this->avatarFileRule()],
        ]);

        $this->deleteStoredAvatar($user->avatar_path);

        $user->update([
            'avatar_path' => $this->storeAvatarFile($data['avatar'], $user->id),
        ]);

        return new UserResource($user->load('organization'));
    }

    /** POST /api/users — создать пользователя (только admin и NTI). */
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($this->canCreateOrDeleteUsers($actor), 403, 'Access denied');

        $data = $request->validate([
            'role' => ['required', 'integer', Rule::in($this->assignableRoles($actor))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'birth_date' => ['required', 'date'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $data = $this->normalizeUserDataForActor($actor, $data);
        $this->assertValidRoleOrganization($data['role'], $data['organization_id'] ?? null);

        $user = User::create($data);

        return (new UserResource($user->load('organization')))
            ->response()
            ->setStatusCode(201);
    }

    /** GET /api/users/{user} — карточка (доступ по canViewUser). */
    public function show(Request $request, User $user)
    {
        abort_unless($this->canViewUser($request->user(), $user), 403, 'Access denied');

        $user->load('organization');

        if ($user->role === User::ROLE_STUDENT) {
            $user->load('subjects');
        }

        return new UserResource($user);
    }

    /** PATCH /api/users/{user} — свой профиль или чужой (admin/NTI). */
    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($this->canViewUser($actor, $user), 403, 'Access denied');
        abort_unless($this->canUpdateUser($actor, $user), 403, 'Access denied');
        $this->assertCanManageTargetRole($actor, $user->role);

        $rules = $this->isSelfUpdate($actor, $user)
            ? $this->selfUpdateRules($user)
            : $this->managerUpdateRules($actor, $user);

        $data = $request->validate($rules);
        $data = $this->normalizeUserDataForActor($actor, $data, $user);

        if (isset($data['role']) || array_key_exists('organization_id', $data)) {
            $role = $data['role'] ?? $user->role;
            $organizationId = $data['organization_id'] ?? $user->organization_id;
            $this->assertValidRoleOrganization($role, $organizationId);
        }

        if (isset($data['role'])) {
            $this->assertCanAssignRole($actor, (int) $data['role']);
        }

        $user->update($data);

        return new UserResource($user->load('organization'));
    }

    /** DELETE /api/users/{user} — soft delete и отзыв токенов (только admin и NTI). */
    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($this->canCreateOrDeleteUsers($actor), 403, 'Access denied');
        abort_unless($this->canViewUser($actor, $user), 403, 'Access denied');
        abort_if($this->isSelfUpdate($actor, $user), 403, 'You cannot deactivate your own account.');
        $this->assertCanManageTargetRole($actor, $user->role);

        $this->deleteStoredAvatar($user->avatar_path);
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    /** GET /api/users/{user}/subjects — предметы студента с оценками. */
    public function indexSubjects(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($this->canViewStudentSubjects($actor, $user), 403, 'Access denied');

        return SubjectResource::collection($user->subjects()->get());
    }

    /** POST /api/users/{user}/subjects — привязать предмет и оценку (admin, NTI). */
    public function storeSubject(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($this->canManageSubjectGrades($actor), 403, 'Access denied');
        $this->assertGradeTargetStudent($user);

        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade' => ['nullable', 'numeric', 'min:1', 'max:4'],
        ]);

        if ($user->subjects()->where('subjects.id', $data['subject_id'])->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => ['Student is already enrolled in this subject.'],
            ]);
        }

        $user->subjects()->attach($data['subject_id'], [
            'grade' => $data['grade'] ?? null,
        ]);

        $subject = $user->subjects()->where('subjects.id', $data['subject_id'])->first();

        return (new SubjectResource($subject))
            ->response()
            ->setStatusCode(201);
    }

    /** PATCH /api/users/{user}/subjects/{subject} — обновить оценку (admin, NTI). */
    public function updateSubject(Request $request, User $user, Subject $subject)
    {
        $actor = $request->user();
        abort_unless($this->canManageSubjectGrades($actor), 403, 'Access denied');
        $this->assertGradeTargetStudent($user);
        $this->assertStudentHasSubject($user, $subject);

        $data = $request->validate([
            'grade' => ['required', 'numeric', 'min:1', 'max:4'],
        ]);

        $user->subjects()->updateExistingPivot($subject->id, [
            'grade' => $data['grade'],
        ]);

        return new SubjectResource($user->subjects()->where('subjects.id', $subject->id)->first());
    }

    /** DELETE /api/users/{user}/subjects/{subject} — отвязать предмет (admin, NTI). */
    public function destroySubject(Request $request, User $user, Subject $subject)
    {
        $actor = $request->user();
        abort_unless($this->canManageSubjectGrades($actor), 403, 'Access denied');
        $this->assertGradeTargetStudent($user);
        $this->assertStudentHasSubject($user, $subject);

        $user->subjects()->detach($subject->id);

        return response()->noContent();
    }

    // ——— Аватар ———

    /** Правила файла аватара (тип и размер). */
    private function avatarFileRule(): File
    {
        return File::types(['jpg', 'jpeg', 'png', 'webp', 'gif'])->max(2 * 1024);
    }

    /** Сохранить файл аватара на диск. */
    private function storeAvatarFile(UploadedFile $file, int $userId): string
    {
        return $file->store('avatars/'.$userId, self::AVATAR_DISK);
    }

    /** Удалить старый файл аватара с диска. */
    private function deleteStoredAvatar(?string $path): void
    {
        if ($path && Storage::disk(self::AVATAR_DISK)->exists($path)) {
            Storage::disk(self::AVATAR_DISK)->delete($path);
        }
    }

    // ——— Проверки ролей ———

    /** Admin или NTI employee. */
    private function isAdminOrNti(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true);
    }

    /** GET /users — только admin и NTI. */
    private function canListUsers(User $actor): bool
    {
        return $this->isAdminOrNti($actor);
    }

    /** Запись оценок по предметам — только admin и NTI. */
    private function canManageSubjectGrades(User $actor): bool
    {
        return $this->isAdminOrNti($actor);
    }

    /** POST и DELETE /users — только admin и NTI (org admin создаёт пользователей через них или /register). */
    private function canCreateOrDeleteUsers(User $actor): bool
    {
        return $this->isAdminOrNti($actor);
    }

    /**
     * PATCH чужого профиля: admin — любой; NTI — кроме admin/NTI.
     * Свой профиль — canViewUser + isSelfUpdate.
     */
    private function canUpdateUser(User $actor, User $user): bool
    {
        if ($this->isSelfUpdate($actor, $user)) {
            return true;
        }

        if ($actor->role === User::ROLE_ADMIN) {
            return true;
        }

        if ($actor->role === User::ROLE_NTI_EMPLOYEE) {
            return !in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true);
        }

        return false;
    }

    /**
     * GET /users/{id}: admin/NTI — все; org — своя организация; студент — только себя.
     */
    private function canViewUser(User $actor, User $user): bool
    {
        if ($this->isAdminOrNti($actor)) {
            return true;
        }

        if ($actor->role === User::ROLE_ORGANIZATION_ADMIN || $actor->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
                return false;
            }

            return (int) $user->organization_id === (int) $actor->organization_id;
        }

        if ($actor->role === User::ROLE_STUDENT) {
            return $this->isSelfUpdate($actor, $user);
        }

        return false;
    }

    /** Чтение предметов: admin, NTI или сам студент. */
    private function canViewStudentSubjects(User $actor, User $user): bool
    {
        if ($user->role !== User::ROLE_STUDENT) {
            return false;
        }

        if ($this->canManageSubjectGrades($actor)) {
            return true;
        }

        return $this->isSelfUpdate($actor, $user);
    }

    /** Actor редактирует свой аккаунт. */
    private function isSelfUpdate(User $actor, User $user): bool
    {
        return (int) $actor->id === (int) $user->id;
    }

    /** Цель для оценок — только студент. */
    private function assertGradeTargetStudent(User $user): void
    {
        if ($user->role !== User::ROLE_STUDENT) {
            throw ValidationException::withMessages([
                'user' => ['Subject grades can only be assigned to students.'],
            ]);
        }
    }

    /** 404 если студент не привязан к предмету. */
    private function assertStudentHasSubject(User $user, Subject $subject): void
    {
        if (!$user->subjects()->where('subjects.id', $subject->id)->exists()) {
            abort(404);
        }
    }

    /**
     * role ↔ organization_id: org-роли требуют org; admin/NTI — без org.
     */
    private function assertValidRoleOrganization(int $role, ?int $organizationId): void
    {
        $requiresOrg = in_array($role, [User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true);
        $forbidsOrg = in_array($role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true);

        if ($requiresOrg && !$organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => ['organization_id is required for this role.'],
            ]);
        }

        if ($forbidsOrg && $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => ['organization_id must be null for this role.'],
            ]);
        }
    }

    /** Роли, которые actor может назначить при create/update. */
    private function assignableRoles(User $actor): array
    {
        if ($actor->role === User::ROLE_ADMIN) {
            return [
                User::ROLE_ADMIN,
                User::ROLE_STUDENT,
                User::ROLE_ORGANIZATION_EMPLOYEE,
                User::ROLE_ORGANIZATION_ADMIN,
                User::ROLE_NTI_EMPLOYEE,
            ];
        }

        if ($actor->role === User::ROLE_NTI_EMPLOYEE) {
            return [
                User::ROLE_STUDENT,
                User::ROLE_ORGANIZATION_EMPLOYEE,
                User::ROLE_ORGANIZATION_ADMIN,
            ];
        }

        return [];
    }

    /** NTI не может менять/удалять существующих admin и NTI. */
    private function assertCanManageTargetRole(User $actor, int $targetRole): void
    {
        if ($actor->role === User::ROLE_ADMIN) {
            return;
        }

        if (in_array($targetRole, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            abort(403, 'Access denied');
        }
    }

    /** Проверка role из запроса входит в assignableRoles(actor). */
    private function assertCanAssignRole(User $actor, int $role): void
    {
        if (!in_array($role, $this->assignableRoles($actor), true)) {
            throw ValidationException::withMessages([
                'role' => ['You cannot assign this role.'],
            ]);
        }
    }

    /** Поля при редактировании своего профиля (без role и organization_id). */
    private function selfUpdateRules(User $user): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'birth_date' => ['sometimes', 'required', 'date'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** Поля при редактировании чужого профиля admin/NTI (включая role). */
    private function managerUpdateRules(User $actor, User $user): array
    {
        return [
            'role' => ['sometimes', 'required', 'integer', Rule::in($this->assignableRoles($actor))],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'birth_date' => ['sometimes', 'required', 'date'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Подготовка данных: при self-update убрать role/org;
     * org admin больше не форсирует org (не создаёт пользователей).
     */
    private function normalizeUserDataForActor(User $actor, array $data, ?User $target = null): array
    {
        if ($this->isSelfUpdate($actor, $target ?? $actor)) {
            unset($data['role'], $data['organization_id']);
        }

        return $data;
    }
}
