<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\User;

class SubjectController extends Controller
{

    public function index()
    {
        $subjects = Subject::query()
            ->select(["id", "name", "description"])
            ->get();

        return response()->json($subjects);
    }

    
    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $subject = Subject::create($data);

        return response()->json([
            'message' => 'Subject created successfully',
            'subject' => $subject
        ], 201);
    }

    public function show(Subject $subject)
    {
        $subject->load([
            'categories:id,name'
        ]);

        return response()->json($subject);
    }

    public function update(Request $request, Subject $subject)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $subject->update($data);

        return response()->json([
            'message' => 'Subject updated successfully',
            'subject' => $subject->fresh()->load('categories:id,name')
        ]);
    }

    // Только админ, но жесткое удаление(теряем все связи)
    public function destroy(Request $request, Subject $subject)
    {
        $user = $request->user();

        abort_unless($user->role === User::ROLE_ADMIN,
            403, 'Only admin can delete subjects'
        );

        // удаляем связи со студентами
        $subject->users()->detach();

        // удаляем связи с категориями
        $subject->categories()->detach();

        // удаляем сам предмет
        $subject->delete();

        return response()->json([
            'message' => 'Subject deleted successfully'
        ]);
    }
}
