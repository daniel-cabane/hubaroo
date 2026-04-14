<?php

use App\Http\Controllers\AttemptController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\KangourouSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Authentication Routes
Route::middleware(['web'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/check', [AuthController::class, 'check'])->name('auth.check');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.email');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

    Route::middleware(['auth'])->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// Kangourou Session API Routes (guests allowed)
Route::get('/api/papers', [KangourouSessionController::class, 'papers'])->name('papers.index');
Route::post('/api/kangourou-sessions', [KangourouSessionController::class, 'store'])->name('kangourou-sessions.store');
Route::get('/api/kangourou-sessions/{code}', [KangourouSessionController::class, 'show'])->name('kangourou-sessions.show');
Route::patch('/api/kangourou-sessions/{kangourouSession}/activate', [KangourouSessionController::class, 'activate'])->name('kangourou-sessions.activate');

Route::post('/api/kangourou-sessions/{code}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
Route::get('/api/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
Route::patch('/api/attempts/{attempt}/answer', [AttemptController::class, 'updateAnswer'])->name('attempts.updateAnswer');
Route::post('/api/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
Route::get('/api/attempts/recover/{code}', [AttemptController::class, 'recover'])->name('attempts.recover');

// Auth-only routes
Route::middleware(['auth'])->group(function () {
    Route::get('/api/my/kangourou-sessions', [KangourouSessionController::class, 'myIndex'])->name('kangourou-sessions.myIndex');
    Route::get('/api/my/attempts', [AttemptController::class, 'myIndex'])->name('attempts.myIndex');
    Route::patch('/api/kangourou-sessions/{kangourouSession}', [KangourouSessionController::class, 'update'])->name('kangourou-sessions.update');
    Route::get('/api/kangourou-sessions/{kangourouSession}/details', [KangourouSessionController::class, 'details'])->name('kangourou-sessions.details');
    Route::patch('/api/kangourou-sessions/{kangourouSession}/change-code', [KangourouSessionController::class, 'changeCode'])->name('kangourou-sessions.changeCode');
});

Route::fallback(function () {
    return view('welcome');
});
