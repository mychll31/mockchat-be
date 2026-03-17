<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\LlmSettingsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Auth (public)
// ---------------------------------------------------------------------------

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::get('auth/google', [AuthController::class, 'redirectUrl']);
Route::get('auth/google/callback', [AuthController::class, 'handleCallback']);

// ---------------------------------------------------------------------------
// Protected routes (any authenticated, enabled user)
// ---------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'check.enabled'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('chat/new', [ChatController::class, 'newConversation']);
    Route::post('chat/send', [ChatController::class, 'sendMessage']);
    Route::get('chat/messages', [ChatController::class, 'getMessages']);
    Route::get('chat/conversation', [ChatController::class, 'getConversation']);
    Route::post('chat/end', [ChatController::class, 'endConversation']);
    Route::get('chat/stats', [ChatController::class, 'getStats']);

    Route::get('llm/providers', [LlmSettingsController::class, 'providers']);
    Route::get('llm/settings', [LlmSettingsController::class, 'index']);
    Route::post('llm/settings', [LlmSettingsController::class, 'store']);
    Route::delete('llm/settings/{provider}', [LlmSettingsController::class, 'destroy']);

    Route::apiResource('products', ProductController::class);

    // -----------------------------------------------------------------------
    // Admin routes
    // -----------------------------------------------------------------------

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::post('users/{user}/toggle', [UserController::class, 'toggle']);
    });

    // -----------------------------------------------------------------------
    // Mentor + Admin routes
    // -----------------------------------------------------------------------

    Route::middleware('role:admin,mentor')->prefix('mentor')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::post('students/{user}/toggle', [UserController::class, 'toggle']);

        // Student conversations (live chat viewing)
        Route::get('students/{user}/conversations', [UserController::class, 'conversations']);
        Route::get('students/{user}/conversations/{conversationId}/messages', [UserController::class, 'conversationMessages']);

        // Student products
        Route::get('students/{user}/products', [UserController::class, 'studentProducts']);
        Route::post('students/{user}/products', [UserController::class, 'storeStudentProduct']);
        Route::delete('students/{user}/products/{productId}', [UserController::class, 'destroyStudentProduct']);

        // Student LLM settings
        Route::get('students/{user}/llm', [UserController::class, 'studentLlmSettings']);
        Route::post('students/{user}/llm', [UserController::class, 'storeStudentLlmSetting']);
        Route::delete('students/{user}/llm/{provider}', [UserController::class, 'destroyStudentLlmSetting']);
    });
});
