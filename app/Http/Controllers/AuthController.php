<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Вход, выход и профиль текущего пользователя (Sanctum Bearer-токен).
 */
class AuthController extends Controller
{
    /**
     * POST /api/register — публичная регистрация студента.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'birth_date' => ['required', 'date'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::create([
            'role' => User::ROLE_STUDENT,
            'name' => $data['name'],
            'email' => $data['email'],
            'birth_date' => $data['birth_date'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'organization_id' => null,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        $user->notify(new WelcomeNotification());

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('organization')),
        ], 201);
    }

    /**
     * POST /api/login — выдаёт токен и данные пользователя.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = $request->user();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('organization')),
        ]);
    }

    /**
     * POST /api/logout — удаляет только текущий токен.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * GET /api/me — текущий авторизованный пользователь.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(
            new UserResource($request->user()->load('organization'))
        );
    }

    /**
     * POST /api/change-password — смена пароля (нужен текущий пароль).
     * Throttle на маршруте — защита от перебора current_password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Invalid password.'],
            ]);
        }

        $user->update(['password' => $data['password']]);

        $user->notify(new PasswordChangedNotification());

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }
}
