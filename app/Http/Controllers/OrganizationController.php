<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrganizationResource;
use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\User;
use App\Models\Project;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::query()
            ->select(['id', 'name', 'slug', 'logo_path', 'sector',])
            ->get();

        return response()->json($organizations);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_ADMIN], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'logo_path' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'string'],
            'ico' => ['required', 'string', 'max:20', 'unique:organizations,ico'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'sector' => ['nullable', 'string'],

            'organization_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Admin create an organization
        if ($user->role === User::ROLE_ADMIN) {
            abort_unless(isset($data['organization_admin_id']),
                422, 'Admin must specify who will be an admin of organization'
            );

            $leader = User::query()
                ->where('id', $data['organization_admin_id'])
                ->firstOrFail();

            abort_unless(in_array($leader->role, [User::ROLE_ORGANIZATION_ADMIN,], true),
                422, 'Selected user can not be an organization leader'
            );

            abort_if(!is_null($leader->organization_id),
                422, 'Selected leader already belongs to another organization'
            );

        } else if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            abort_if(!is_null($user->organization_id),
                422, 'You already belong to another organization'
            );

            $leader = $user;
        }

        $organization = Organization::create($data);

        // connect leader
        $leader->update([
            'organization_id' => $organization->id,
        ]);


        return response()->json([
            'message' => 'Organization created successfully',
            'organization' => new OrganizationResource(
                $this->loadOrganizationData($organization)
            ),
        ], 201);
    }

    public function show(Organization $organization)
    {
        return new OrganizationResource(
            $this->loadOrganizationData($organization));
    }

    public function update(Request $request, Organization $organization)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_ADMIN], true),
            403, 'Access denied'
        );

        // org admin может менять только свою организацию
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            abort_unless($user->organization_id === $organization->id,
                403, 'You can only update your own organization'
            );
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('organizations', 'slug')->ignore($organization->id),
            ],
            'logo_path' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'sector' => ['nullable', 'string'],
            'ico' => ['nullable', 'string'],
        ]);

        $organization->update($data);

        return response()->json([
            'message' => 'Organization updated successfully',
            'organization' => new OrganizationResource(
                $this->loadOrganizationData($organization)
            ),
        ]);
    }

    public function destroy(Request $request, Organization $organization)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_ADMIN], true),
            403, 'Access denied'
        );

        // ORG_ADMIN может удалять только свою организацию
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            abort_unless($user->organization_id === $organization->id,
                403, 'You can only delete your own organization'
            );
        }

        // Нельзя удалять organization с активными проектами
        abort_if($organization->projects()->where('status', Project::STATUS_ACTIVE)->exists(),
            422, 'Cannot delete organization with active projects'
        );

        User::where('organization_id', $organization->id)
            ->update([
                'organization_id' => null
        ]);

        $organization->projects()
            ->update([
                'organization_id' => null
            ]);

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully'
        ]);
    }

    public function changeOrganizationAdmin(Request $request, Organization $organization)
    {
        $user = $request->user();

        abort_unless($user->role === User::ROLE_ADMIN,
            403, 'Only admin can change organization leader'
        );

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newLeader = User::findOrFail($data['user_id']);

        // проверка роли
        abort_unless($newLeader->role === User::ROLE_ORGANIZATION_ADMIN,
            422, 'Selected user can not be an organization leader'
        );

        // проверка что не состоит в организации
        abort_if(!is_null($newLeader->organization_id),
            422, 'User already belongs to an organization'
        );

        User::where('organization_id', $organization->id)
            ->where('role', User::ROLE_ORGANIZATION_ADMIN)
            ->update([
                'organization_id' => null
        ]);

        $newLeader->update([
            'organization_id' => $organization->id,
        ]);

        return response()->json([
            'message' => 'Organization leader changed successfully'
        ]);
    }

    public function addEmployee(Request $request, Organization $organization)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_ADMIN], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $employee = User::findOrFail($data['user_id']);

        abort_unless($employee->role === User::ROLE_ORGANIZATION_EMPLOYEE,
            422, 'Selected user can not be an organization employee'
        );

        abort_if(!is_null($employee->organization_id),
            422, 'Selected user already belongs to an organization'
        );

        $employee->update([
            'organization_id' => $organization->id,
        ]);

        return response()->json([
            'message' => 'User added to organization successfully',
        ]);
    }

    public function removeEmployee(Request $request, Organization $organization)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_ADMIN], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $employee = User::findOrFail($data['user_id']);

        // должен состоять именно в этой организации
        abort_if($employee->organization_id !== $organization->id,
            422, 'Selected user does not belong to this organization'
        );

        // нельзя удалить лидера (по твоей логике лидер = ORGANIZATION_ADMIN)
        abort_if($employee->role === User::ROLE_ORGANIZATION_ADMIN,
            422, 'Cannot remove organization leader'
        );

        $employee->update([
            'organization_id' => null,
        ]);

        return response()->json([
            'message' => 'Employee removed from organization successfully',
        ]);
    }

    private function loadOrganizationData(Organization $organization): Organization
    {
        $organization->load('projects');

        $organization->organization_admin = User::query()
            ->where('organization_id', $organization->id)
            ->where('role', User::ROLE_ORGANIZATION_ADMIN)
            ->first();

        $organization->employees = User::query()
            ->where('organization_id', $organization->id)
            ->where('role', User::ROLE_ORGANIZATION_EMPLOYEE)
            ->get();

        return $organization;
    }
}
