<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('chat/new', [ChatController::class, 'newConversation']);
Route::post('chat/send', [ChatController::class, 'sendMessage']);
Route::get('chat/messages', [ChatController::class, 'getMessages']); // ?conversation_id=
Route::get('chat/conversation', [ChatController::class, 'getConversation']); // ?conversation_id=
Route::post('chat/end', [ChatController::class, 'endConversation']);
Route::get('chat/stats', [ChatController::class, 'getStats']);
