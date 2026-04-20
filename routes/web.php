<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\KangourouSessionController;
use App\Http\Controllers\PaperController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Authentication Routes
Route::middleware(['web'])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
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
Route::get('/api/papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
Route::get('/api/kangourou-sessions/{code}', [KangourouSessionController::class, 'show'])->name('kangourou-sessions.show');

Route::post('/api/kangourou-sessions/{code}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
Route::get('/api/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
Route::patch('/api/attempts/{attempt}/answer', [AttemptController::class, 'updateAnswer'])->name('attempts.updateAnswer');
Route::post('/api/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
Route::get('/api/attempts/recover/{code}', [AttemptController::class, 'recover'])->name('attempts.recover');

// Auth-only routes
Route::middleware(['auth'])->group(function () {
    Route::post('/api/kangourou-sessions', [KangourouSessionController::class, 'store'])->name('kangourou-sessions.store');
    Route::get('/api/my/kangourou-sessions', [KangourouSessionController::class, 'myIndex'])->name('kangourou-sessions.myIndex');
    Route::get('/api/my/attempts', [AttemptController::class, 'myIndex'])->name('attempts.myIndex');
    Route::patch('/api/attempts/{attempt}', [AttemptController::class, 'update'])->name('attempts.update');
    Route::delete('/api/attempts/{attempt}', [AttemptController::class, 'destroy'])->name('attempts.destroy');
    Route::post('/api/attempts/claim', [AttemptController::class, 'claim'])->name('attempts.claim');
    Route::patch('/api/kangourou-sessions/{kangourouSession}', [KangourouSessionController::class, 'update'])->name('kangourou-sessions.update');
    Route::delete('/api/kangourou-sessions/{kangourouSession}', [KangourouSessionController::class, 'destroy'])->name('kangourou-sessions.destroy');
    Route::get('/api/kangourou-sessions/{kangourouSession}/details', [KangourouSessionController::class, 'details'])->name('kangourou-sessions.details');
    Route::patch('/api/kangourou-sessions/{kangourouSession}/change-code', [KangourouSessionController::class, 'changeCode'])->name('kangourou-sessions.changeCode');
    Route::patch('/api/kangourou-sessions/{kangourouSession}/activate', [KangourouSessionController::class, 'activate'])->name('kangourou-sessions.activate');
    Route::get('/api/my/divisions', [DivisionController::class, 'myIndex'])->name('divisions.myIndex');
    Route::post('/api/divisions', [DivisionController::class, 'store'])->name('divisions.store');
    Route::get('/api/divisions/{division}', [DivisionController::class, 'show'])->name('divisions.show');
    Route::patch('/api/divisions/{division}', [DivisionController::class, 'update'])->name('divisions.update');
    Route::delete('/api/divisions/{division}', [DivisionController::class, 'destroy'])->name('divisions.destroy');
    Route::patch('/api/divisions/{division}/change-code', [DivisionController::class, 'changeCode'])->name('divisions.changeCode');
    Route::post('/api/divisions/{division}/invite', [DivisionController::class, 'invite'])->name('divisions.invite');
    Route::post('/api/divisions/join', [DivisionController::class, 'join'])->name('divisions.join');
    Route::delete('/api/divisions/{division}/students/{student}', [DivisionController::class, 'removeStudent'])->name('divisions.removeStudent');

    // Session ↔ Division management
    Route::post('/api/kangourou-sessions/{session}/divisions/{division}', [DivisionController::class, 'openForDivision'])->name('sessions.openForDivision');
    Route::delete('/api/kangourou-sessions/{session}/divisions/{division}', [DivisionController::class, 'closeForDivision'])->name('sessions.closeForDivision');

    // Invites
    Route::get('/api/my/invites', [DivisionController::class, 'myInvites'])->name('divisions.myInvites');
    Route::post('/api/invites/{invite}/accept', [DivisionController::class, 'acceptInvite'])->name('divisions.acceptInvite');
    Route::post('/api/invites/{invite}/decline', [DivisionController::class, 'declineInvite'])->name('divisions.declineInvite');

    // Admin routes
    Route::middleware(['can:admin'])->prefix('/api/admin')->group(function () {
        Route::get('/papers', [AdminController::class, 'papers'])->name('admin.papers');
        Route::patch('/papers/{paper}', [AdminController::class, 'updatePaper'])->name('admin.papers.update');
        Route::get('/users', [AdminController::class, 'searchUsers'])->name('admin.users.search');
        Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.updateRole');
        Route::get('/roles', [AdminController::class, 'roles'])->name('admin.roles');
    });
});

Route::fallback(function () {
    return view('welcome');
});
