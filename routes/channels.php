<?php

use App\Models\Division;
use App\Models\KangourouSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('session.{sessionId}', function ($user, $sessionId) {
    $session = KangourouSession::find($sessionId);

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
