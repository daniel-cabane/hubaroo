<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Division;
use App\Models\KangourouSession;

class SessionAnalysisService
{
    public function compute(KangourouSession $session): void
    {
        $session->loadMissing([
            'paper.questions' => fn ($q) => $q->orderByPivot('order'),
            'divisions.students',
        ]);

        $questions = $session->paper->relationLoaded('questions')
            ? $session->paper->questions
            : $session->paper->questions()->orderByPivot('order')->get();

        $allFinishedAttempts = Attempt::where('kangourou_session_id', $session->id)
            ->where('status', 'finished')
            ->get();

        $session->divisions->each(function (Division $division) use ($session, $questions, $allFinishedAttempts): void {
            $studentIds = $division->relationLoaded('students')
                ? $division->students->pluck('id')
                : $division->students()->pluck('users.id');

            $attempts = $allFinishedAttempts->whereIn('user_id', $studentIds->toArray())->values();

            if ($attempts->isEmpty()) {
                return;
            }

            $totalAttempts = $attempts->count();

            $analysis = $questions->values()->map(function ($question, int $index) use ($attempts, $totalAttempts): array {
                $correctCount = $attempts->filter(function (Attempt $attempt) use ($index): bool {
                    $answers = $attempt->answers ?? [];

                    return isset($answers[$index]) && ($answers[$index]['status'] ?? '') === 'correct';
                })->count();

                return [
                    'question_id' => $question->id,
                    'success_ratio' => round($correctCount / $totalAttempts, 2),
                    'reviewed' => false,
                ];
            })->all();

            $division->kangourouSessions()->updateExistingPivot($session->id, ['analysis' => $analysis]);
        });
    }
}
