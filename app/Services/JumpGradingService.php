<?php

namespace App\Services;

use App\Models\JumpAttempt;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JumpGradingService
{
    /**
     * Points for one question. A blank is not an outcome of this method; callers pass 0 for it.
     */
    public function pointsFor(int $difficulty, string $outcome): int
    {
        $difficulty = max(0, $difficulty);

        if ($outcome === 'correct') {
            return (int) round($difficulty / 100);
        }

        if ($outcome === 'incorrect') {
            return -max((int) round($difficulty / 1000), 1);
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questionList
     */
    public function officialScore(array $questionList): int
    {
        $sum = 0;

        foreach ($questionList as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sum += (int) ($item['points'] ?? 0);
        }

        return max(0, $sum);
    }

    /**
     * Teacher preview from the answer the student gave. Not persisted.
     *
     * @param  array<string, mixed>  $item
     */
    public function previewPoints(array $item, ?string $correctAnswer): int
    {
        $answer = $item['answer'] ?? null;

        if ($answer === null || $answer === '') {
            return 0;
        }

        $difficulty = (int) ($item['difficulty'] ?? 0);

        if ($correctAnswer !== null && $answer === $correctAnswer) {
            return $this->pointsFor($difficulty, 'correct');
        }

        return $this->pointsFor($difficulty, 'incorrect');
    }

    /**
     * Attach display points without writing. When the jump is not expired, points are removed.
     * When a graded row has no stored points, they are computed and `score` is the official total.
     * A null score means the caller should keep the stored total.
     *
     * @param  array<int, array<string, mixed>>  $questionList
     * @return array{question_list: array<int, array<string, mixed>>, score: int|null}
     */
    public function displayQuestionList(array $questionList, bool $reveal): array
    {
        $computed = false;
        $hasPending = false;

        foreach ($questionList as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! $reveal) {
                unset($item['points']);
                $questionList[$index] = $item;

                continue;
            }

            $status = $item['status'] ?? 'pending';

            if ($status !== 'correct' && $status !== 'incorrect') {
                $hasPending = true;
                unset($item['points']);
                $questionList[$index] = $item;

                continue;
            }

            if (! array_key_exists('points', $item) || $item['points'] === null) {
                $computed = true;
                $answer = $item['answer'] ?? null;
                $blank = $answer === null || $answer === '';
                $difficulty = (int) ($item['difficulty'] ?? 0);

                if ($blank) {
                    $item['points'] = 0;
                } elseif ($status === 'correct') {
                    $item['points'] = $this->pointsFor($difficulty, 'correct');
                } else {
                    $item['points'] = $this->pointsFor($difficulty, 'incorrect');
                }
            }

            $questionList[$index] = $item;
        }

        $score = ($reveal && ! $hasPending && $computed) ? $this->officialScore($questionList) : null;

        return [
            'question_list' => array_values($questionList),
            'score' => $score,
        ];
    }

    /**
     * @param  Collection<int, string>|null  $correctAnswers
     */
    public function gradeAttempt(JumpAttempt $attempt, ?Collection $correctAnswers = null): void
    {
        $questionList = $attempt->question_list ?? [];

        if ($correctAnswers === null) {
            $questionIds = collect($questionList)->pluck('id')->unique()->filter();
            $correctAnswers = Question::query()->whereIn('id', $questionIds)->pluck('correct_answer', 'id');
        }

        foreach ($questionList as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $correct = $correctAnswers->get($item['id'] ?? null);
            $given = $item['answer'] ?? null;
            $blank = $given === null || $given === '';
            $difficulty = (int) ($item['difficulty'] ?? 0);

            if ($blank) {
                $item['status'] = 'incorrect';
                $item['points'] = 0;
            } elseif ($correct !== null && $given === $correct) {
                $item['status'] = 'correct';
                $item['points'] = $this->pointsFor($difficulty, 'correct');
            } else {
                $item['status'] = 'incorrect';
                $item['points'] = $this->pointsFor($difficulty, 'incorrect');
            }

            $questionList[$index] = $item;
        }

        $attempt->update([
            'question_list' => $questionList,
            'score' => $this->officialScore($questionList),
        ]);
    }

    /**
     * @return array{jumps: int, attempts: int}
     */
    public function regradeCounts(): array
    {
        $jumpIds = [];
        $attempts = 0;

        $this->expiredAttemptsQuery()
            ->select(['id', 'jump_id', 'question_list'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$jumpIds, &$attempts): void {
                foreach ($chunk as $attempt) {
                    if ($this->hasPendingQuestion($attempt)) {
                        continue;
                    }

                    $attempts++;
                    $jumpIds[$attempt->jump_id] = true;
                }
            });

        return [
            'jumps' => count($jumpIds),
            'attempts' => $attempts,
        ];
    }

    /**
     * Rewrite scores for expired attempts that are already fully graded.
     *
     * @return array{jumps: int, attempts: int, division_ids: array<int, int>}
     */
    public function regradeExpiredAttempts(): array
    {
        $jumpIds = [];
        $divisionIds = [];
        $attempts = 0;

        $this->expiredAttemptsQuery()
            ->with('jump.course:id,division_id')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$jumpIds, &$divisionIds, &$attempts): void {
                $eligible = $chunk->reject(fn (JumpAttempt $attempt): bool => $this->hasPendingQuestion($attempt));

                if ($eligible->isEmpty()) {
                    return;
                }

                $questionIds = $eligible->flatMap(
                    fn (JumpAttempt $attempt) => collect($attempt->question_list ?? [])->pluck('id')
                )->filter()->unique()->values();

                $correctAnswers = $questionIds->isEmpty()
                    ? collect()
                    : Question::query()->whereIn('id', $questionIds)->pluck('correct_answer', 'id');

                foreach ($eligible as $attempt) {
                    $this->gradeAttempt($attempt, $correctAnswers);
                    $attempts++;
                    $jumpIds[$attempt->jump_id] = true;

                    $divisionId = $attempt->jump?->course?->division_id;

                    if ($divisionId !== null) {
                        $divisionIds[(int) $divisionId] = (int) $divisionId;
                    }
                }
            });

        return [
            'jumps' => count($jumpIds),
            'attempts' => $attempts,
            'division_ids' => array_values($divisionIds),
        ];
    }

    /**
     * @return Builder<JumpAttempt>
     */
    private function expiredAttemptsQuery(): Builder
    {
        return JumpAttempt::query()
            ->whereHas('jump', fn (Builder $query) => $query->where('status', 'expired'));
    }

    private function hasPendingQuestion(JumpAttempt $attempt): bool
    {
        foreach ($attempt->question_list ?? [] as $item) {
            if (is_array($item) && ($item['status'] ?? null) === 'pending') {
                return true;
            }
        }

        return false;
    }
}
