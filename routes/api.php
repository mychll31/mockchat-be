<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\LlmSettingsController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('chat/new', [ChatController::class, 'newConversation']);
Route::post('chat/send', [ChatController::class, 'sendMessage']);
Route::get('chat/messages', [ChatController::class, 'getMessages']); // ?conversation_id=
Route::get('chat/conversation', [ChatController::class, 'getConversation']); // ?conversation_id=
Route::post('chat/end', [ChatController::class, 'endConversation']);
Route::get('chat/stats', [ChatController::class, 'getStats']);

Route::get('llm/providers', [LlmSettingsController::class, 'providers']);
Route::get('llm/settings', [LlmSettingsController::class, 'index']);
Route::post('llm/settings', [LlmSettingsController::class, 'store']);
Route::delete('llm/settings/{provider}', [LlmSettingsController::class, 'destroy']);

Route::apiResource('products', ProductController::class);
