<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBugReportRequest;
use App\Models\BugReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $bugReport = DB::transaction(function () use ($user, $request): BugReport {
            $unsolvedCount = BugReport::where('user_id', $user->id)
                ->whereIn('status', BugReport::unsolvedStatuses())
                ->lockForUpdate()
                ->count();

            if ($unsolvedCount >= 5) {
                throw ValidationException::withMessages([
                    'bug_report' => ['Vous avez atteint la limite de rapports de bugs non résolus.'],
                ]);
            }

            return BugReport::create([
                'user_id' => $user->id,
                'comment' => $request->validated('comment'),
            ]);
        });

        return response()->json(['bug_report' => $bugReport], 201);
    }
}
