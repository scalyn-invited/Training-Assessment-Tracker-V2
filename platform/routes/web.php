<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\SandboxApiController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\TrainingSession;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
Route::middleware(TrainingSession::class)->group(function () {
    Route::get('/settings/ai', [AiController::class, 'settings'])->name('ai.settings');
    Route::post('/settings/ai', [AiController::class, 'policy'])->name('ai.policy');
    Route::post('/settings/ai/{id}/reconcile', [AiController::class, 'reconcile'])->name('ai.reconcile');
    Route::post('/enrolments/{id}/generation', [AiController::class, 'request'])->middleware('throttle:10,1')->name('ai.request');
    Route::get('/generation/{id}', [AiController::class, 'show'])->name('ai.show');
    Route::get('/enrolments/{id}/onboarding', [LearningController::class, 'onboarding'])->name('onboarding.show');
    Route::post('/enrolments/{id}/onboarding/calendar', [LearningController::class, 'calendar'])->name('onboarding.calendar');
    Route::post('/enrolments/{id}/onboarding/{step}', [LearningController::class, 'save'])->name('onboarding.save');
    Route::get('/enrolments/{id}/curriculum', [LearningController::class, 'show'])->name('plans.show');
    Route::post('/enrolments/{id}/curriculum', [LearningController::class, 'create'])->name('plans.create');
    Route::post('/enrolments/{id}/curriculum/edit', [LearningController::class, 'edit'])->name('plans.edit');
    Route::post('/enrolments/{id}/curriculum/review', [LearningController::class, 'review'])->name('plans.review');
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/people', [WorkspaceController::class, 'people'])->name('people');
    Route::get('/enrolments/{id}', [WorkspaceController::class, 'show'])->name('enrolments.show');
    Route::post('/enrolments/{id}/check', [WorkspaceController::class, 'check'])->name('enrolments.check');
    Route::post('/enrolments/{id}/files', [WorkspaceController::class, 'upload'])->middleware('throttle:20,1')->name('files.upload');
    Route::get('/files/{id}', [WorkspaceController::class, 'download'])->name('files.download');
    Route::get('/jobs/{id}', [WorkspaceController::class, 'job'])->name('jobs.show');
});
// Read-only sandbox contract proof. Live delegated identity is deliberately not advertised as implemented.
Route::get('/api/v1/me/training', [SandboxApiController::class, 'summary'])->middleware('throttle:60,1');
Route::get('/api/v1/members/{memberId}/summary', [SandboxApiController::class, 'summary'])->middleware('throttle:60,1');
