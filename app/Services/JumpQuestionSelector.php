<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Jump;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;

class JumpQuestionSelector
{
    public function selectQuestions(Jump $jump, User $student, Division $division): array
    {
        $course = $jump->course;
        $nbQuestions = $jump->nb_questions;
        $growth = $jump->growth;
        $mastery = $student->mastery ?? 1000;

        $excludedQuestionIds = $this->getExcludedQuestionIds($jump, $student, $division);

        $availableQuestions = Question::whereNotIn('id', $excludedQuestionIds)->get();

        if ($availableQuestions->isEmpty()) {
            $availableQuestions = Question::all();
        }

        $selected = [];

        // Q1: difficulty ≈ mastery - 100
        $q1Target = $mastery - 100;
        $q1 = $this->closestQuestion($availableQuestions, $q1Target, $selected);
        if ($q1) {
            $selected[] = ['id' => $q1->id, 'status' => 'pending', 'difficulty' => $q1->difficulty];
        }

        if ($nbQuestions < 2) {
            return $selected;
        }

        // Q2: difficulty ≈ mastery
        $q2 = $this->closestQuestion($availableQuestions, $mastery, $selected);
        if ($q2) {
            $selected[] = ['id' => $q2->id, 'status' => 'pending', 'difficulty' => $q2->difficulty];
        }

        if ($nbQuestions < 3) {
            return $selected;
        }

        // Q3: difficulty ≈ mastery
        $q3 = $this->closestQuestion($availableQuestions, $mastery, $selected);
        if ($q3) {
            $selected[] = ['id' => $q3->id, 'status' => 'pending', 'difficulty' => $q3->difficulty];
        }

        if ($nbQuestions < 4) {
            return $selected;
        }

        // Q4..Qn: linearly spaced from mastery to min(2300, mastery + 200*growth)
        $maxDifficulty = min(2300, $mastery + 200 * $growth);
        $remaining = $nbQuestions - 3;

        for ($k = 1; $k <= $remaining; $k++) {
            $target = (int) round($mastery + ($k / $remaining) * ($maxDifficulty - $mastery));
            $q = $this->closestQuestion($availableQuestions, $target, $selected);
            if ($q) {
                $selected[] = ['id' => $q->id, 'status' => 'pending', 'difficulty' => $q->difficulty];
            }
        }

        return $selected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $alreadySelected
     */
    private function closestQuestion(Collection $questions, int $target, array $alreadySelected): ?Question
    {
        $selectedIds = collect($alreadySelected)->pluck('id')->toArray();

        return $questions
            ->reject(fn (Question $q) => in_array($q->id, $selectedIds))
            ->sortBy(fn (Question $q) => abs($q->difficulty - $target))
            ->first();
    }

    /**
     * @return array<int>
     */
    private function getExcludedQuestionIds(Jump $jump, User $student, Division $division): array
    {
        $course = $jump->course;

        // Questions from previous jumps in this course for this student
        $previousJumpIds = $course->jumps()
            ->where('id', '!=', $jump->id)
            ->pluck('id');

        $usedInCourse = \DB::table('jump_user')
            ->where('user_id', $student->id)
            ->whereIn('jump_id', $previousJumpIds)
            ->pluck('question_list')
            ->flatMap(fn ($list) => collect(json_decode($list ?? '[]', true))->pluck('id'))
            ->unique()
            ->values()
            ->toArray();

        // Questions from Kangaroo sessions done by this class
        $sessionQuestionIds = $division->kangourouSessions()
            ->with('paper.questions:id')
            ->get()
            ->flatMap(fn ($session) => $session->paper?->questions?->pluck('id') ?? collect())
            ->unique()
            ->values()
            ->toArray();

        return array_unique(array_merge($usedInCourse, $sessionQuestionIds));
    }
}
