<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDivisionRequest;
use App\Http\Requests\UpdateDivisionRequest;
use App\Models\Division;
use App\Models\DivisionInvite;
use App\Models\KangourouSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    public function myIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('Teacher')) {
            $divisions = $user->teacherDivisions()
                ->with('teacher')
                ->with('kangourouSessions', fn ($q) => $q->where('status', 'active')->with('paper'))
                ->withCount('students')
                ->latest()
                ->get();
        } else {
            $divisions = $user->divisions()
                ->with('teacher')
                ->with('kangourouSessions', fn ($q) => $q->where('status', 'active')->with('paper'))
                ->withCount('students')
                ->latest()
                ->get();
        }

        return response()->json(['divisions' => $divisions]);
    }

    public function show(Division $division, Request $request): JsonResponse
    {
        $this->authorize('view', $division);

        $user = $request->user();
        $isTeacher = $division->teacher_id === $user->id;

        $division->loadCount('students');

        if ($isTeacher) {
            $division->load([
                'teacher',
                'students',
                'invites',
                'kangourouSessions.paper',
            ]);
        } else {
            $division->load([
                'teacher',
                'kangourouSessions' => fn ($q) => $q->where('status', 'active'),
                'kangourouSessions.paper',
            ]);
        }

        return response()->json(['division' => $division, 'is_teacher' => $isTeacher]);
    }

    public function store(CreateDivisionRequest $request): JsonResponse
    {
        $division = Division::create([
            'teacher_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'code' => Division::generateCode(),
            'accepting_students' => true,
            'archived' => false,
        ]);

        return response()->json([
            'message' => 'Division created successfully.',
            'division' => $division->load('teacher'),
        ], 201);
    }

    public function update(UpdateDivisionRequest $request, Division $division): JsonResponse
    {
        $data = $request->validated();
        $division->update($data);

        return response()->json([
            'message' => 'Division updated.',
            'division' => $division->fresh(['teacher']),
        ]);
    }

    public function destroy(Division $division, Request $request): JsonResponse
    {
        $this->authorize('delete', $division);

        $division->delete();

        return response()->json(['message' => 'Division deleted.']);
    }

    public function changeCode(Division $division, Request $request): JsonResponse
    {
        $this->authorize('update', $division);

        $division->update(['code' => Division::generateCode()]);

        return response()->json([
            'message' => 'Code changed.',
            'division' => $division->fresh(['teacher']),
        ]);
    }

    public function invite(Division $division, Request $request): JsonResponse
    {
        $this->authorize('manageStudents', $division);

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');

        $user = User::where('email', $email)->first();

        if ($user && $division->students()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'This user is already in the division.'], 409);
        }

        $invite = DivisionInvite::firstOrCreate(
            ['division_id' => $division->id, 'email' => $email],
            ['user_id' => $user?->id, 'status' => 'pending'],
        );

        return response()->json([
            'message' => 'Invite sent.',
            'invite' => $invite,
        ], 201);
    }

    public function acceptInvite(DivisionInvite $invite, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($invite->email !== $user->email) {
            return response()->json(['message' => 'This invite is not for you.'], 403);
        }

        if ($invite->status !== 'pending') {
            return response()->json(['message' => 'This invite has already been responded to.'], 409);
        }

        $division = $invite->division;
        $invite->delete();
        $division->students()->syncWithoutDetaching([$user->id]);

        return response()->json(['message' => 'Invite accepted.']);
    }

    public function declineInvite(DivisionInvite $invite, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($invite->email !== $user->email) {
            return response()->json(['message' => 'This invite is not for you.'], 403);
        }

        $invite->delete();

        return response()->json(['message' => 'Invite declined.']);
    }

    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $division = Division::where('code', strtoupper($request->input('code')))->firstOrFail();

        if (! $division->accepting_students) {
            return response()->json(['message' => 'This class is not accepting new students.'], 403);
        }

        if ($division->archived) {
            return response()->json(['message' => 'This class is archived.'], 403);
        }

        $user = $request->user();

        if ($division->students()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already in this class.'], 409);
        }

        $division->students()->attach($user->id);

        DivisionInvite::where('division_id', $division->id)
            ->where('email', $user->email)
            ->where('status', 'pending')
            ->update(['status' => 'accepted', 'user_id' => $user->id]);

        return response()->json([
            'message' => 'Joined class successfully.',
            'division' => $division->load('teacher'),
        ]);
    }

    public function removeStudent(Division $division, User $student, Request $request): JsonResponse
    {
        $this->authorize('manageStudents', $division);

        $division->students()->detach($student->id);

        return response()->json(['message' => 'Student removed.']);
    }

    public function openForDivision(KangourouSession $session, Division $division, Request $request): JsonResponse
    {
        if ($session->author_id !== $request->user()->id || $division->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $session->divisions()->syncWithoutDetaching([$division->id]);

        return response()->json(['message' => 'Session opened for division.']);
    }

    public function closeForDivision(KangourouSession $session, Division $division, Request $request): JsonResponse
    {
        if ($session->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $session->divisions()->detach($division->id);

        return response()->json(['message' => 'Session closed for division.']);
    }

    public function myInvites(Request $request): JsonResponse
    {
        $invites = DivisionInvite::where('email', $request->user()->email)
            ->where('status', 'pending')
            ->with('division.teacher')
            ->get();

        return response()->json(['invites' => $invites]);
    }
}
