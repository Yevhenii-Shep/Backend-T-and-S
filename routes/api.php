<?php

use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// For test without login
Route::apiResource('projects', ProjectController::class);


Route::middleware('auth:sanctum')->group(function () {
    // Route::apiResource('projects', ProjectController::class);
    Route::apiResource('documents', DocumentController::class);
    Route::apiResource('milestones', MilestoneController::class);
    Route::apiResource('audit-events', AuditEventController::class);
});
