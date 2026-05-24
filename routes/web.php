<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BugReportController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\JumpAttemptController;
use App\Http\Controllers\JumpController;
use App\Http\Controllers\JumpRejoinDemandController;
use App\Http\Controllers\KangourouSessionController;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\RejoinDemandController;
use App\Http\Controllers\SuggestedQuestionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Authentication Routes
Route::middleware(['web'])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register')->middleware('throttle:30,1');
    Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:30,1');
    Route::get('/check', [AuthController::class, 'check'])->name('auth.check');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.email')->middleware('throttle:10,1');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update')->middleware('throttle:10,1');

    // Google OAuth
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::middleware(['auth'])->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::patch('/user/name', [AuthController::class, 'updateName'])->name('user.updateName');
        Route::post('/user/role', [AuthController::class, 'assignRole'])->name('user.assignRole');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// Kangourou Session API Routes (guests allowed)
Route::get('/api/papers', [KangourouSessionController::class, 'papers'])->name('papers.index');
Route::get('/api/papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
Route::get('/api/kangourou-sessions/{code}', [KangourouSessionController::class, 'show'])->name('kangourou-sessions.show');

Route::post('/api/kangourou-sessions/{code}/attempts', [AttemptController::class, 'store'])->name('attempts.store')->middleware('throttle:300,1');
Route::get('/api/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show')->middleware('throttle:300,1');
Route::patch('/api/attempts/{attempt}/answer', [AttemptController::class, 'updateAnswer'])->name('attempts.updateAnswer')->middleware('throttle:300,1');
Route::post('/api/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit')->middleware('throttle:300,1');
Route::get('/api/attempts/recover/{code}', [AttemptController::class, 'recover'])->name('attempts.recover')->middleware('throttle:60,1');

// Rejoin demand (guests allowed to create)
Route::post('/api/attempts/{attempt}/rejoin-demand', [RejoinDemandController::class, 'store'])->name('rejoin-demands.store');

// Auth-only routes
Route::middleware(['auth'])->group(function () {
    Route::post('/api/kangourou-sessions', [KangourouSessionController::class, 'store'])->name('kangourou-sessions.store');
    Route::get('/api/my/kangourou-sessions', [KangourouSessionController::class, 'myIndex'])->name('kangourou-sessions.myIndex');
    Route::get('/api/my/attempts', [AttemptController::class, 'myIndex'])->name('attempts.myIndex');
    Route::patch('/api/attempts/{attempt}', [AttemptController::class, 'update'])->name('attempts.update');
    Route::delete('/api/attempts/{attempt}', [AttemptController::class, 'destroy'])->name('attempts.destroy');
    Route::post('/api/attempts/claim', [AttemptController::class, 'claim'])->name('attempts.claim');
    Route::patch('/api/kangourou-sessions/{kangourouSession}', [KangourouSessionController::class, 'update'])->name('kangourou-sessions.update');

    // Rejoin demands (auth required for teacher actions)
    Route::get('/api/my/rejoin-demands', [RejoinDemandController::class, 'myIndex'])->name('rejoin-demands.myIndex');
    Route::post('/api/rejoin-demands/{rejoinDemand}/approve', [RejoinDemandController::class, 'approve'])->name('rejoin-demands.approve');
    Route::delete('/api/rejoin-demands/{rejoinDemand}', [RejoinDemandController::class, 'reject'])->name('rejoin-demands.reject');
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
    Route::patch('/api/divisions/{division}/students/{student}', [DivisionController::class, 'updateStudentClassName'])->name('divisions.updateStudentClassName');

    // Session ↔ Division management
    Route::post('/api/kangourou-sessions/{session}/divisions/{division}', [DivisionController::class, 'openForDivision'])->name('sessions.openForDivision');
    Route::delete('/api/kangourou-sessions/{session}/divisions/{division}', [DivisionController::class, 'closeForDivision'])->name('sessions.closeForDivision');
    Route::patch('/api/divisions/{division}/kangourou-sessions/{kangourouSession}/questions/{questionId}/reviewed', [DivisionController::class, 'toggleQuestionReviewed'])->name('sessions.toggleQuestionReviewed');

    // Invites
    Route::get('/api/my/invites', [DivisionController::class, 'myInvites'])->name('divisions.myInvites');
    Route::post('/api/invites/{invite}/accept', [DivisionController::class, 'acceptInvite'])->name('divisions.acceptInvite');
    Route::post('/api/invites/{invite}/decline', [DivisionController::class, 'declineInvite'])->name('divisions.declineInvite');

    Route::post('/api/bug-reports', [BugReportController::class, 'store'])->name('bug-reports.store');
    Route::get('/api/my/bug-reports/unsolved-count', [BugReportController::class, 'unsolvedCount'])->name('bug-reports.unsolvedCount');

    // Admin routes
    Route::middleware(['can:admin'])->prefix('/api/admin')->group(function () {
        Route::get('/papers', [AdminController::class, 'papers'])->name('admin.papers');
        Route::patch('/papers/{paper}', [AdminController::class, 'updatePaper'])->name('admin.papers.update');
        Route::get('/users', [AdminController::class, 'searchUsers'])->name('admin.users.search');
        Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.updateRole');
        Route::get('/roles', [AdminController::class, 'roles'])->name('admin.roles');
        Route::get('/bug-reports', [AdminController::class, 'bugReports'])->name('admin.bug-reports.index');
        Route::patch('/bug-reports/{bugReport}', [AdminController::class, 'updateBugReport'])->name('admin.bug-reports.update');
        Route::delete('/bug-reports/{bugReport}', [AdminController::class, 'destroyBugReport'])->name('admin.bug-reports.destroy');
    });

    // Courses & Jumps
    Route::get('/api/divisions/{division}/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::post('/api/divisions/{division}/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::patch('/api/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/api/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
    Route::get('/api/courses/{course}/details', [CourseController::class, 'details'])->name('courses.details');

    Route::post('/api/courses/{course}/jumps', [JumpController::class, 'store'])->name('jumps.store');
    Route::patch('/api/jumps/{jump}', [JumpController::class, 'update'])->name('jumps.update');
    Route::delete('/api/jumps/{jump}', [JumpController::class, 'destroy'])->name('jumps.destroy');

    Route::post('/api/jumps/{jump}/attempts', [JumpAttemptController::class, 'store'])->name('jump-attempts.store');
    Route::get('/api/my/jump-attempts', [JumpAttemptController::class, 'myIndex'])->name('jump-attempts.myIndex');
    Route::get('/api/jump-attempts/{jumpAttempt}', [JumpAttemptController::class, 'show'])->name('jump-attempts.show');
    Route::patch('/api/jump-attempts/{jumpAttempt}/answer', [JumpAttemptController::class, 'updateAnswer'])->name('jump-attempts.updateAnswer');
    Route::post('/api/jump-attempts/{jumpAttempt}/submit', [JumpAttemptController::class, 'submit'])->name('jump-attempts.submit');

    Route::post('/api/jump-attempts/{jumpAttempt}/rejoin-demand', [JumpRejoinDemandController::class, 'store'])->name('jump-rejoin-demands.store');
    Route::get('/api/my/jump-rejoin-demands', [JumpRejoinDemandController::class, 'myIndex'])->name('jump-rejoin-demands.myIndex');
    Route::post('/api/jump-rejoin-demands/{jumpRejoinDemand}/approve', [JumpRejoinDemandController::class, 'approve'])->name('jump-rejoin-demands.approve');
    Route::delete('/api/jump-rejoin-demands/{jumpRejoinDemand}', [JumpRejoinDemandController::class, 'reject'])->name('jump-rejoin-demands.reject');

    // Suggested Questions
    Route::get('/api/courses/{course}/suggested-questions', [SuggestedQuestionController::class, 'index'])->name('suggested-questions.index');
    Route::patch('/api/suggested-questions/{suggestedQuestion}/toggle-public', [SuggestedQuestionController::class, 'togglePublic'])->name('suggested-questions.togglePublic');
    Route::delete('/api/suggested-questions/{suggestedQuestion}', [SuggestedQuestionController::class, 'destroy'])->name('suggested-questions.destroy');
    Route::get('/api/divisions/{division}/public-suggested-questions', [SuggestedQuestionController::class, 'publicForDivision'])->name('suggested-questions.publicForDivision');
});

Route::fallback(function () {
    return view('welcome');
});
