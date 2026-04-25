<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBugReportRequest;
use App\Models\BugReport;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function papers(): JsonResponse
    {
        $papers = Paper::orderBy('year', 'desc')->orderBy('level')->get();

        return response()->json(['papers' => $papers]);
    }

    public function updatePaper(Request $request, Paper $paper): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'level' => ['required', 'string', 'in:e,b,c,p,j,s'],
        ]);

        $paper->update($validated);

        return response()->json(['paper' => $paper->fresh()]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->validate(['q' => ['required', 'string', 'min:2']])['q'];

        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ];
            });

        return response()->json(['users' => $users]);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::pluck('name');

        return response()->json(['roles' => $roles]);
    }

    public function bugReports(): JsonResponse
    {
        $bugReports = BugReport::with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json(['bug_reports' => $bugReports]);
    }

    public function updateBugReport(UpdateBugReportRequest $request, BugReport $bugReport): JsonResponse
    {
        $bugReport->update($request->validated());

        return response()->json(['bug_report' => $bugReport->fresh('user')]);
    }

    public function destroyBugReport(BugReport $bugReport): JsonResponse
    {
        $bugReport->delete();

        return response()->json(['message' => 'Bug report deleted.']);
    }
}
