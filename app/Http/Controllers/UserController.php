<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD пользователей с разграничением по ролям.
 */
class UserController extends Controller
{
    /**
     * GET /api/users — список пользователей (фильтры: role, organization_id).
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        $query = User::query()->with('organization');

        $this->applyUserVisibility($query, $actor);

        if ($request->filled('role')) {
            $query->where('role', $request->integer('role'));
        }

        if ($request->filled('organization_id')) {
            $organizationId = $request->integer('organization_id');
            $this->assertCanFilterByOrganization($actor, $organizationId);
            $query->where('organization_id', $organizationId);
        }

        return response()->json($query->get());
    }

    /**
     * POST /api/users — создание пользователя (admin, org admin).
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($this->canManageUsers($actor), 403, 'Access denied');

        $data = $request->validate([
            'role' => ['required', 'integer', Rule::in($this->assignableRoles($actor))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'birth_date' => ['required', 'date'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $this->normalizeUserDataForActor($actor, $data);
        $this->assertValidRoleOrganization($data['role'], $data['organization_id'] ?? null);

        $user = User::create($data);

        return response()->json($user->load('organization'), 201);
    }

    /**
     * GET /api/users/{user} — карточка пользователя.
     */
    public function show(Request $request, User $user)
    {
        abort_unless($this->canAccessUser($request->user(), $user), 403, 'Access denied');

        return response()->json($user->load('organization'));
    }

    /**
     * PUT/PATCH /api/users/{user} — обновление (admin/org admin или свой профиль).
     */
    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($this->canAccessUser($actor, $user), 403, 'Access denied');
        abort_unless($this->canUpdateUser($actor, $user), 403, 'Access denied');

        $rules = $this->isSelfUpdate($actor, $user)
            ? $this->selfUpdateRules($user)
            : $this->adminUpdateRules($actor, $user);

        $data = $request->validate($rules);
        $data = $this->normalizeUserDataForActor($actor, $data, $user);

        if (isset($data['role']) || array_key_exists('organization_id', $data)) {
            $role = $data['role'] ?? $user->role;
            $organizationId = $data['organization_id'] ?? $user->organization_id;
            $this->assertValidRoleOrganization($role, $organizationId);
        }

        $user->update($data);

        return response()->json($user->load('organization'));
    }

    /**
     * DELETE /api/users/{user} — soft delete и отзыв всех токенов.
     */
    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($this->canManageUsers($actor), 403, 'Access denied');
        abort_unless($this->canAccessUser($actor, $user), 403, 'Access denied');
        abort_if($this->isSelfUpdate($actor, $user), 403, 'You cannot deactivate your own account.');
        abort_if(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true) && $actor->role !== User::ROLE_ADMIN, 403, 'Access denied');

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    /** Создавать и удалять пользователей могут admin и org admin. */
    private function canManageUsers(User $actor): bool
    {
        return in_array($actor->role, [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_ADMIN,
        ], true);
    }

    /** Admin/org admin — любой доступный user; остальные — только свой профиль. */
    private function canUpdateUser(User $actor, User $user): bool
    {
        if ($this->canManageUsers($actor)) {
            return true;
        }

        return $this->isSelfUpdate($actor, $user);
    }

    /** Проверка: actor редактирует сам себя. */
    private function isSelfUpdate(User $actor, User $user): bool
    {
        return (int) $actor->id === (int) $user->id;
    }

    private function canAccessUser(User $actor, User $user): bool
    {
        if ($actor->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            return in_array($user->role, [User::ROLE_STUDENT, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true)
                && (int) $user->organization_id === (int) $actor->organization_id;
        }

        if (in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        if ($actor->role === User::ROLE_ORGANIZATION_ADMIN) {
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

    private function applyUserVisibility(\Illuminate\Database\Eloquent\Builder $query, User $actor): \Illuminate\Database\Eloquent\Builder
    {
        // Сотрудник организации видит только пользователей своей организации.
        if ($actor->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            return $actor->organization_id
                ? $query->where('organization_id', $actor->organization_id)
                : $query->whereRaw('0 = 1');
        }

        if (in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return $query;
        }

        if ($actor->role === User::ROLE_ORGANIZATION_ADMIN) {
            return $query
                ->where('organization_id', $actor->organization_id)
                ->whereNotIn('role', [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE]);
        }

        if ($actor->role === User::ROLE_STUDENT) {
            return $query->where('id', $actor->id);
        }

        return $query->whereRaw('0 = 1');
    }

    /** Org admin не может фильтровать по чужой организации. */
    private function assertCanFilterByOrganization(User $actor, int $organizationId): void
    {
        if ($actor->role === User::ROLE_ORGANIZATION_ADMIN && (int) $organizationId !== (int) $actor->organization_id) {
            abort(403, 'Access denied');
        }
    }

    /** Согласованность role и organization_id. */
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

        if ($actor->role === User::ROLE_ORGANIZATION_ADMIN) {
            return [
                User::ROLE_STUDENT,
                User::ROLE_ORGANIZATION_EMPLOYEE,
                User::ROLE_ORGANIZATION_ADMIN,
            ];
        }

        return [];
    }

    private function selfUpdateRules(User $user): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'birth_date' => ['sometimes', 'required', 'date'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function adminUpdateRules(User $actor, User $user): array
    {
        return [
            'role' => ['sometimes', 'required', 'integer', Rule::in($this->assignableRoles($actor))],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'birth_date' => ['sometimes', 'required', 'date'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function normalizeUserDataForActor(User $actor, array $data, ?User $target = null): array
    {
        if ($actor->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $actor->organization_id;
        }

        if ($this->isSelfUpdate($actor, $target ?? $actor)) {
            unset($data['role'], $data['organization_id']);
        }

        return $data;
    }
}
