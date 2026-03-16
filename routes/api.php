<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\LlmSettingsController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Auth (public)
// ---------------------------------------------------------------------------

Route::get('auth/google', [AuthController::class, 'redirectUrl']);
Route::get('auth/google/callback', [AuthController::class, 'handleCallback']);

// ---------------------------------------------------------------------------
// Protected routes
// ---------------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {
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
});
