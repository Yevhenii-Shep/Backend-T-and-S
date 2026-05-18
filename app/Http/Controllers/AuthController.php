<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация API через Laravel Sanctum (Bearer-токен).
 */
class AuthController extends Controller
{
    /**
     * Вход: email + password. При успехе — Sanctum-токен и пользователь с организацией.
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
        // Токен для заголовка Authorization: Bearer {token}
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('organization'),
        ]);
    }

    /**
     * Выход: удаляет только текущий токен (другие сессии/устройства не затрагиваются).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Текущий авторизованный пользователь (роль, organization_id и т.д.).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load('organization')
        );
    }
}
