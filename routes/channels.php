<?php

use App\Models\Attempt;
use App\Models\Division;
use App\Models\Jump;
use App\Models\KangourouSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('session.{sessionId}', function ($user, $sessionId) {
    $session = KangourouSession::find($sessionId);

    if (! $session) {
        return false;
    }

    if ((int) $session->author_id === (int) $user->id) {
        return true;
    }

    // Allow authenticated participants (students with an active attempt)
    return Attempt::where('kangourou_session_id', $session->id)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('attempt.{attemptId}', function ($user, $attemptId) {
    $attempt = Attempt::find($attemptId);

    if (! $attempt) {
        return false;
    }

    if ((int) $attempt->user_id === (int) $user->id) {
        return true;
    }

    // Also allow the session author (teacher) to receive updates
    $session = $attempt->kangourouSession;

    return $session && (int) $session->author_id === (int) $user->id;
});

Broadcast::channel('division.{divisionId}', function ($user, $divisionId) {
    $division = Division::find($divisionId);

    if (! $division) {
        return false;
    }

    if ((int) $division->teacher_id === (int) $user->id) {
        return true;
    }

    return $division->students()->where('users.id', $user->id)->exists();
});

Broadcast::channel('jump.{jumpId}', function ($user, $jumpId) {
    $jump = Jump::with('course.division')->find($jumpId);

    if (! $jump) {
        return false;
    }

    $division = $jump->course->division;

    if ((int) $division->teacher_id === (int) $user->id) {
        return true;
    }

    return $division->students()->where('users.id', $user->id)->exists();
});
