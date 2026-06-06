<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoomController;
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


Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
