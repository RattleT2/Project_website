<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SharedController;
use App\Http\Controllers\Api\Admin\ExportController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

Route::get('captcha', [CaptchaController::class, 'generate']);
Route::get('captcha/reload', [CaptchaController::class, 'reload']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('google', [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateProfile']);
        Route::put('me/password', [AuthController::class, 'changePassword']);
    });
});

Route::get('media-types', [SharedController::class, 'mediaTypes']);
Route::get('evaluation-questions', [SharedController::class, 'evaluationQuestions']);
Route::get('evaluation-questions/{mediaTypeId}', [SharedController::class, 'questionsByMediaType']);

Route::middleware(['auth:api', 'role:pelapor'])->prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index']);
    Route::post('/', [ReportController::class, 'store']);
    Route::get('{id}', [ReportController::class, 'show']);
    Route::put('{id}', [ReportController::class, 'update']);
    Route::delete('{id}', [ReportController::class, 'destroy']);
    Route::post('{id}/submit', [ReportController::class, 'submit']);
    Route::post('{reportId}/upload/{questionId}', [ReportController::class, 'uploadFile']);
});

Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [AdminReportController::class, 'dashboard']);

    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('{id}', [AdminUserController::class, 'show']);
        Route::put('{id}/status', [AdminUserController::class, 'updateStatus']);
        Route::delete('{id}', [AdminUserController::class, 'destroy']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/', [AdminReportController::class, 'index']);
        Route::get('{id}', [AdminReportController::class, 'show']);
        Route::put('{id}', [AdminReportController::class, 'update']);
        Route::put('{id}/status', [AdminReportController::class, 'updateStatus']);
        Route::get('{id}/pdf', [ExportController::class, 'singlePdf']);
    });

    Route::get('export-pdf', [ExportController::class, 'recapPdf']);
});
