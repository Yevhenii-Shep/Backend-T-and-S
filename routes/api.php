<?php

use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


// Публичный вход; throttle — защита от перебора пароля
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

// Текущий пользователь по Bearer-токену (дублирует /me, оставлен для совместимости)
Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');


// Все маршруты ниже требуют заголовок: Authorization: Bearer {token}
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');

    Route::post('users/me/avatar', [UserController::class, 'updateAvatar']);
    Route::delete('users/me', [UserController::class, 'destroyMe']);

    Route::apiResource('users', UserController::class);

    Route::get('users/{user}/subjects', [UserController::class, 'indexSubjects']);
    Route::post('users/{user}/subjects', [UserController::class, 'storeSubject']);
    Route::patch('users/{user}/subjects/{subject}', [UserController::class, 'updateSubject']);
    Route::delete('users/{user}/subjects/{subject}', [UserController::class, 'destroySubject']);

    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);
    Route::patch('projects/{project}/deadline', [ProjectController::class, 'updateDeadline']);
    Route::patch('projects/{project}/assign-nti-mentor', [ProjectController::class, 'assignNtiMentor']);
    Route::patch('projects/{project}/assign-organization-mentor', [ProjectController::class, 'assignOrganizationMentor']);
    Route::patch('projects/{project}/assign-category', [ProjectController::class, 'assignCategory']);
    Route::patch('projects/{project}/assign-team', [ProjectController::class, 'assignTeam']);
    Route::patch('projects/{project}/assign-organization', [ProjectController::class, 'assignOrganization']);
    Route::patch('projects/{project}/accept-after-audit', [ProjectController::class, 'acceptAfterAudit']);
    Route::patch('projects/{project}/decline-after-audit', [ProjectController::class, 'declineAfterAudit']);
    Route::apiResource('projects', ProjectController::class);

    Route::get('documents/{document}/download', [DocumentController::class, 'download']);
    Route::apiResource('documents', DocumentController::class);
    Route::patch('milestones/{milestone}/status', [MilestoneController::class, 'updateStatus']);
    Route::apiResource('milestones', MilestoneController::class);
    Route::apiResource('evaluations', EvaluationController::class);
    Route::patch('audit-events/{audit_event}/result', [AuditEventController::class, 'updateResult']);
    Route::apiResource('audit-events', AuditEventController::class);

    // Участники аудита
    Route::post('audit-events/{audit_event}/participants', [AuditEventController::class, 'storeParticipant']);
    Route::delete('audit-events/{audit_event}/participants/{participantUser}', [AuditEventController::class, 'destroyParticipant']);

    // Team logic (чужая зона)
    Route::apiResource('teams', TeamController::class); // CRUD
    Route::get('teams/{team}/invite-code', [TeamController::class, 'inviteCode']); // показать invite code
    Route::post('/teams/{team}/regenerate-invite-code', [TeamController::class, 'regenerateInviteCode']); // сменить invite code
    Route::post('teams/join', [TeamController::class, 'join']); // встпуить в команду зная invite code

    Route::apiResource('subjects', SubjectController::class);

    Route::apiResource('categories', CategoryController::class);

    Route::apiResource('organizations', OrganizationController::class); // CRUD
    Route::post('organizations/{organization}/change-admin', [OrganizationController::class, "changeOrganizationAdmin"]); // поменять админа орги
    Route::post('organizations/{organization}/add-employee', [OrganizationController::class, "addEmployee"]); // добавить сотркдника в оргу
    Route::post('organizations/{organization}/remove-employee', [OrganizationController::class, "removeEmployee"]); // удалить сотрудника из орги
});
