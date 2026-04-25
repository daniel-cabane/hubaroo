<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBugReportRequest;
use App\Models\BugReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BugReportController extends Controller
{
    public function unsolvedCount(Request $request): JsonResponse
    {
        $count = BugReport::where('user_id', $request->user()->id)
            ->whereIn('status', BugReport::unsolvedStatuses())
            ->count();

        return response()->json(['count' => $count]);
    }

    public function store(StoreBugReportRequest $request): JsonResponse
    {
        $user = $request->user();

        $unsolvedCount = BugReport::where('user_id', $user->id)
            ->whereIn('status', BugReport::unsolvedStatuses())
            ->count();

        if ($unsolvedCount >= 5) {
            return response()->json(['message' => 'Vous avez atteint la limite de rapports de bugs non résolus.'], 422);
        }

        $bugReport = BugReport::create([
            'user_id' => $user->id,
            'comment' => $request->validated('comment'),
        ]);

        return response()->json(['bug_report' => $bugReport], 201);
    }
}
