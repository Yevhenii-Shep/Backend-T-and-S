<?php

use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Публичный вход; throttle — защита от перебора пароля
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Текущий пользователь по Bearer-токену (дублирует /me, оставлен для совместимости)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// For test without login
// Route::apiResource('projects', ProjectController::class);


// Все маршруты ниже требуют заголовок: Authorization: Bearer {token}
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('documents', DocumentController::class);
    Route::apiResource('milestones', MilestoneController::class);
    Route::apiResource('audit-events', AuditEventController::class);

    // Team logic
    Route::apiResource('teams', TeamController::class); // base CRUD
    Route::get('teams/{team}/invite-code', [TeamController::class, 'inviteCode']); // View invite-code
    Route::post('/teams/{team}/regenerate-invite-code', [TeamController::class, 'regenerateInviteCode']); // Re-generate invite code
    Route::post('teams/join', [TeamController::class,'join']); // Join team by invite code

    Route::apiResource('subjects', SubjectController::class);
});
