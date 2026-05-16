<?php

namespace App\Jobs;

use App\Models\Jump;
use App\Models\SuggestedQuestion;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class AnalyseJump implements ShouldQueue
{
    use Queueable;

    /** @var array<int, array{level: int, max: int}> */
    private const LEVELS = [
        ['level' => 1, 'max' => 10],
        ['level' => 2, 'max' => 5],
        ['level' => 3, 'max' => 3],
    ];

    public function __construct(public Jump $jump) {}

    public function handle(): void
    {
        $jump = $this->jump->load([
            'course.division.students',
            'attempts.user',
        ]);

        $course = $jump->course;
        $division = $course->division;

        /** @var Collection<int, User> $students */
        $students = $division->students;

        if ($students->isEmpty()) {
            return;
        }

        // Recalculate class mastery average M
        $mastery = $students->avg('mastery') ?? 0;

        // Collect question IDs where the student answered incorrectly
        $incorrectQuestionIds = collect();

        foreach ($jump->attempts as $attempt) {
            foreach ($attempt->question_list ?? [] as $item) {
                if (($item['status'] ?? '') !== 'correct') {
                    $incorrectQuestionIds->push([
                        'id' => $item['id'],
                        'difficulty' => $item['difficulty'] ?? 0,
                    ]);
                }
            }
        }

        if ($incorrectQuestionIds->isEmpty()) {
            return;
        }

        // Deduplicate by question id
        $incorrectQuestionIds = $incorrectQuestionIds->unique('id');

        // Categorise by level
        foreach (self::LEVELS as ['level' => $level, 'max' => $max]) {
            [$minDiff, $maxDiff] = $this->difficultyRange($mastery, $level);

            $candidates = $incorrectQuestionIds->filter(
                fn ($q) => $q['difficulty'] >= $minDiff && $q['difficulty'] <= $maxDiff
            )->pluck('id');

            if ($candidates->isEmpty()) {
                continue;
            }

            // Avoid duplicates already stored
            $existing = SuggestedQuestion::where('course_id', $course->id)
                ->where('level', $level)
                ->pluck('question_id');

            $newIds = $candidates->diff($existing);

            foreach ($newIds as $questionId) {
                // Enforce max: delete oldest first
                $count = SuggestedQuestion::where('course_id', $course->id)
                    ->where('level', $level)
                    ->count();

                if ($count >= $max) {
                    SuggestedQuestion::where('course_id', $course->id)
                        ->where('level', $level)
                        ->orderBy('created_at')
                        ->first()
                        ?->delete();
                }

                SuggestedQuestion::create([
                    'course_id' => $course->id,
                    'question_id' => $questionId,
                    'level' => $level,
                    'is_public' => false,
                ]);
            }
        }
    }

    /**
     * Returns [min, max] difficulty for a given level relative to mastery M.
     *
     * @return array{int, int}
     */
    private function difficultyRange(float $mastery, int $level): array
    {
        return match ($level) {
            1 => [(int) ($mastery - 100), (int) ($mastery + 199)],
            2 => [(int) ($mastery + 200), (int) ($mastery + 399)],
            3 => [(int) ($mastery + 400), (int) ($mastery + 799)],
            default => [0, 0],
        };
    }
}
