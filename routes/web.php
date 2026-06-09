<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\BroadcastAuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoomController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/join', [RoomController::class, 'showJoin'])->name('rooms.join');
Route::post('/join', [RoomController::class, 'join'])->name('rooms.join.post');

Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');

Route::middleware('auth')->post('/rooms/{code}/start', [RoomController::class, 'start'])->name('rooms.start');

Route::get('/rooms/{code}', [RoomController::class, 'lobby'])->name('rooms.lobby');
Route::get('/rooms/{code}/play', [RoomController::class, 'play'])->name('rooms.play');

// Answer submission — web middleware for session + CSRF
Route::post('/api/answers', [AnswerController::class, 'store'])->name('answers.store');
Route::post('/api/heartbeat', [HeartbeatController::class, 'store'])->name('heartbeat');

// Broadcasting auth — custom controller authorises guests via session game_player_id
// (the default Broadcast::auth requires an authenticated user and 403s for guests).
Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate'])
    ->withoutMiddleware(VerifyCsrfToken::class);


// Groups — static segments declared before {group} so they aren't captured as a binding.
Route::middleware('auth')->group(function () {
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::post('/groups/join', [GroupController::class, 'join'])->name('groups.join');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::post('/groups/{group}/launch', [GroupController::class, 'launch'])->name('groups.launch');
    Route::delete('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');
});

require __DIR__.'/settings.php';
