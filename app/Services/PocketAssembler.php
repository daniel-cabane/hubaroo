<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Division;
use App\Models\DivisionInvite;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\KangourouSession;
use App\Models\SuggestedQuestion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PocketAssembler
{
    /**
     * @return array{viewer: string, invites: Collection<int, DivisionInvite>, classes: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $invites = DivisionInvite::query()
            ->where('email', $user->email)
            ->where('status', 'pending')
            ->with('division.teacher')
            ->get();

        if ($user->hasRole('Teacher')) {
            return [
                'viewer' => 'teacher',
                'invites' => $invites,
                'classes' => $this->teacherClasses($user),
            ];
        }

        return [
            'viewer' => 'student',
            'invites' => $invites,
            'classes' => $this->studentClasses($user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function studentClasses(User $user): array
    {
        $divisions = $this->sorted($user->divisions()->where('divisions.archived', false)->get());

        if ($divisions->isEmpty()) {
            return [];
        }

        $divisionIds = $divisions->pluck('id')->all();
        $courses = $this->studentCourses($user, $divisionIds);
        $sessions = $this->sessionsFor($divisionIds, $user);
        $questions = $this->publicQuestions($divisionIds);

        return $divisions->map(function (Division $division) use ($courses, $sessions, $questions): array {
            $divisionCourses = $courses->get($division->id, collect());
            $divisionSessions = $sessions->get($division->id, collect());
            $divisionQuestions = $questions->get($division->id, collect());

            $openJumps = $this->studentOpenJumps($divisionCourses);
            $openSessions = $this->studentOpenSessions($divisionSessions);
            $active = $this->studentActiveCourse($divisionCourses);
            $sessionCandidates = $this->studentSessionCandidates($divisionSessions);
            $recentSessions = array_slice($sessionCandidates, 0, 3);
            $freshGrades = $this->studentFreshGrades($divisionCourses, $active['shown_ids']);

            $freshSessionTimes = collect($sessionCandidates)
                ->filter(fn (array $row) => $row['fresh'])
                ->map(fn (array $row) => Carbon::parse($row['revealed_at']));
            $freshJumpTimes = $this->studentFreshJumpTimes($divisionCourses);

            $priority = $this->priority(
                openJumps: $openJumps !== [],
                openSessions: $openSessions !== [],
                freshGrade: $freshJumpTimes->isNotEmpty() || $freshSessionTimes->isNotEmpty(),
                hasQuestions: $divisionQuestions->isNotEmpty(),
            );
            $signalAt = $this->signalAt($priority, $openJumps, $openSessions, $freshJumpTimes->merge($freshSessionTimes), $divisionQuestions->pluck('updated_at'));

            return [
                'id' => $division->id,
                'name' => $division->name,
                'open_jumps' => $this->withoutInternal($openJumps),
                'open_sessions' => $this->withoutInternal($openSessions),
                'active_course' => $active['course'],
                'recent_sessions' => $recentSessions,
                'open_questions' => $this->questionRows($divisionQuestions),
                'fresh_grades' => $freshGrades,
                'priority' => $priority,
                'signal_at' => $signalAt,
                'badge_count' => count($openJumps) + count($openSessions),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teacherClasses(User $user): array
    {
        $divisions = $this->sorted($user->teacherDivisions()->where('archived', false)->get());

        if ($divisions->isEmpty()) {
            return [];
        }

        $divisionIds = $divisions->pluck('id')->all();
        $courses = $this->teacherCourses($divisionIds);
        $sessions = $this->sessionsFor($divisionIds, null);
        $questions = $this->publicQuestions($divisionIds);

        return $divisions->map(function (Division $division) use ($user, $courses, $sessions, $questions): array {
            $divisionCourses = $courses->get($division->id, collect());
            $divisionSessions = $sessions->get($division->id, collect());
            $divisionQuestions = $questions->get($division->id, collect());

            $openJumps = $this->teacherOpenJumps($divisionCourses);
            $openSessions = $this->teacherOpenSessions($divisionSessions, $user);
            $active = $this->teacherActiveCourse($divisionCourses);
            $sessionCandidates = $this->teacherSessionCandidates($divisionSessions, $user);
            $recentSessions = array_slice($sessionCandidates, 0, 3);
            $freshJumpModels = $this->teacherFreshJumps($divisionCourses);
            $shownIds = $active['shown_ids'];
            $freshGrades = $freshJumpModels
                ->reject(fn (Jump $jump) => in_array($jump->id, $shownIds, true))
                ->take(3)
                ->map(fn (Jump $jump) => [
                    'kind' => 'jump',
                    'course_id' => $jump->course_id,
                    'course_title' => $jump->course->title,
                    'jump_id' => $jump->id,
                    'number' => $this->expiredNumber($jump),
                ])->values()->all();

            $freshSessionTimes = collect($sessionCandidates)
                ->filter(fn (array $row) => $row['fresh'])
                ->map(fn (array $row) => Carbon::parse($row['expires_at']));

            $priority = $this->priority(
                openJumps: $openJumps !== [],
                openSessions: $openSessions !== [],
                freshGrade: $freshJumpModels->isNotEmpty() || $freshSessionTimes->isNotEmpty(),
                hasQuestions: $divisionQuestions->isNotEmpty(),
            );

            $signalAt = $this->signalAt(
                $priority,
                $openJumps,
                $openSessions,
                $freshJumpModels->map(fn (Jump $jump) => $jump->updated_at)->merge($freshSessionTimes),
                $divisionQuestions->pluck('updated_at'),
            );

            return [
                'id' => $division->id,
                'name' => $division->name,
                'open_jumps' => $this->withoutInternal($openJumps),
                'open_sessions' => $this->withoutInternal($openSessions),
                'active_course' => $active['course'],
                'recent_sessions' => $recentSessions,
                'open_questions' => $this->questionRows($divisionQuestions),
                'fresh_grades' => $freshGrades,
                'priority' => $priority,
                'signal_at' => $signalAt,
                'badge_count' => count($openJumps) + count($openSessions),
            ];
        })->all();
    }

    /**
     * @param  list<int>  $divisionIds
     * @return Collection<int, Collection<int, Course>>
     */
    private function studentCourses(User $user, array $divisionIds): Collection
    {
        return Course::query()
            ->whereIn('division_id', $divisionIds)
            ->where('archived', false)
            ->with(['jumps' => function ($query) use ($user): void {
                $query->whereIn('status', ['active', 'expired'])
                    ->orderBy('id')
                    ->with(['attempts' => function ($attempts) use ($user): void {
                        $attempts->where('user_id', $user->id)
                            ->select(['id', 'jump_id', 'user_id', 'score', 'status', 'updated_at']);
                    }]);
            }])
            ->get()
            ->each(function (Course $course): void {
                $course->jumps->each(fn (Jump $jump) => $jump->setRelation('course', $course));
            })
            ->groupBy('division_id');
    }

    /**
     * @param  list<int>  $divisionIds
     * @return Collection<int, Collection<int, Course>>
     */
    private function teacherCourses(array $divisionIds): Collection
    {
        return Course::query()
            ->whereIn('division_id', $divisionIds)
            ->where('archived', false)
            ->with(['jumps' => function ($query): void {
                $query->whereIn('status', ['active', 'expired'])
                    ->orderBy('id')
                    ->withCount([
                        'attempts',
                        'attempts as graded_attempts_count' => fn ($attempts) => $attempts
                            ->where('status', 'finished')
                            ->whereNotNull('score'),
                    ]);
            }])
            ->get()
            ->each(function (Course $course): void {
                $course->jumps->each(fn (Jump $jump) => $jump->setRelation('course', $course));
            })
            ->groupBy('division_id');
    }

    /**
     * @param  list<int>  $divisionIds
     * @return Collection<int, Collection<int, KangourouSession>>
     */
    private function sessionsFor(array $divisionIds, ?User $student): Collection
    {
        $query = KangourouSession::query()
            ->whereHas('divisions', fn ($divisions) => $divisions->whereIn('divisions.id', $divisionIds))
            ->with([
                'paper:id,title',
                'divisions:id',
            ]);

        if ($student !== null) {
            $query->with(['attempts' => function ($attempts) use ($student): void {
                $attempts->where('user_id', $student->id)
                    ->select(['id', 'kangourou_session_id', 'user_id', 'status', 'score', 'updated_at']);
            }]);
        } else {
            $query->withCount('attempts');
        }

        $grouped = collect();
        foreach ($divisionIds as $divisionId) {
            $grouped->put($divisionId, collect());
        }

        foreach ($query->get() as $session) {
            foreach ($session->divisions as $division) {
                if (! $grouped->has($division->id)) {
                    continue;
                }
                $grouped->get($division->id)->push($session);
            }
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $divisionIds
     * @return Collection<int, Collection<int, SuggestedQuestion>>
     */
    private function publicQuestions(array $divisionIds): Collection
    {
        return SuggestedQuestion::query()
            ->where('is_public', true)
            ->whereHas('course', fn ($course) => $course
                ->whereIn('division_id', $divisionIds)
                ->where('archived', false))
            ->with([
                'question:id,image',
                'course:id,title,division_id',
            ])
            ->orderBy('level')
            ->orderByDesc('created_at')
            ->get(['id', 'level', 'course_id', 'question_id', 'updated_at', 'is_public'])
            ->groupBy(fn (SuggestedQuestion $question) => $question->course->division_id);
    }

    /**
     * @param  Collection<int, SuggestedQuestion>  $questions
     * @return list<array<string, mixed>>
     */
    private function questionRows(Collection $questions): array
    {
        return $questions->map(fn (SuggestedQuestion $question) => [
            'id' => $question->id,
            'course_id' => $question->course_id,
            'level' => $question->level,
            'course_title' => $question->course->title,
            'question' => [
                'id' => $question->question->id,
                'image' => $question->question->image,
            ],
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return list<array<string, mixed>>
     */
    private function studentOpenJumps(Collection $courses): array
    {
        return $courses
            ->flatMap(fn (Course $course) => $course->jumps)
            ->filter(fn (Jump $jump) => $jump->status === 'active' && ! $jump->isClosed())
            ->filter(function (Jump $jump): bool {
                $attempt = $this->ownAttempt($jump);

                return $attempt === null || $attempt->status === 'inProgress';
            })
            ->sortByDesc('id')
            ->map(function (Jump $jump): array {
                $attempt = $this->ownAttempt($jump);

                return [
                    'id' => $jump->id,
                    'course_title' => $jump->course->title,
                    'nb_questions' => $jump->nb_questions,
                    'time' => $jump->time,
                    'expiration' => $this->iso($jump->expiration),
                    'action' => $attempt === null ? 'start' : 'resume',
                    'updated_at' => $jump->updated_at,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, KangourouSession>  $sessions
     * @return list<array<string, mixed>>
     */
    private function studentOpenSessions(Collection $sessions): array
    {
        return $sessions
            ->filter(fn (KangourouSession $session) => $session->isActive())
            ->filter(function (KangourouSession $session): bool {
                $attempt = $session->attempts->sortByDesc('updated_at')->first();

                return $attempt === null || $attempt->status === 'inProgress';
            })
            ->sortByDesc('id')
            ->map(function (KangourouSession $session): array {
                $attempt = $session->attempts->sortByDesc('updated_at')->first();

                return [
                    'id' => $session->id,
                    'code' => $session->code,
                    'paper_title' => $session->paper?->title ?? 'Session Kangourou',
                    'expires_at' => $this->iso($session->expires_at),
                    'action' => $attempt === null ? 'join' : 'resume',
                    'attempt_id' => $attempt?->status === 'inProgress' ? $attempt->id : null,
                    'updated_at' => $session->updated_at,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return array{course: array<string, mixed>|null, shown_ids: list<int>}
     */
    private function studentActiveCourse(Collection $courses): array
    {
        $jumps = $courses->flatMap(fn (Course $course) => $course->jumps);
        $best = $jumps->sortByDesc('id')->first();

        if ($best === null) {
            return ['course' => null, 'shown_ids' => []];
        }

        $course = $best->course;
        $expired = $course->jumps->filter(fn (Jump $jump) => $jump->status === 'expired')->sortBy('id')->values();
        $score = $expired->sum(function (Jump $jump): int {
            $attempt = $this->ownAttempt($jump);

            return (int) ($attempt->score ?? 0);
        });

        $recent = $expired
            ->filter(function (Jump $jump): bool {
                $attempt = $this->ownAttempt($jump);

                return $attempt !== null && $attempt->status === 'finished' && $attempt->score !== null;
            })
            ->sortByDesc('id')
            ->take(3)
            ->map(fn (Jump $jump) => $this->studentJumpChip($jump, $expired))
            ->values()
            ->all();

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'score' => $score,
                'recent_jumps' => $recent,
            ],
            'shown_ids' => array_column($recent, 'id'),
        ];
    }

    /**
     * @param  Collection<int, Jump>  $expired
     * @return array<string, mixed>
     */
    private function studentJumpChip(Jump $jump, Collection $expired): array
    {
        $attempt = $this->ownAttempt($jump);

        return [
            'id' => $jump->id,
            'attempt_id' => $attempt->id,
            'number' => $expired->search(fn (Jump $row) => $row->id === $jump->id) + 1,
            'score' => (int) $attempt->score,
            'created_at' => $this->iso($jump->created_at),
            'fresh' => $attempt->updated_at !== null && $attempt->updated_at->greaterThanOrEqualTo(now()->subDay()),
        ];
    }

    /**
     * @param  Collection<int, KangourouSession>  $sessions
     * @return list<array<string, mixed>>
     */
    private function studentSessionCandidates(Collection $sessions): array
    {
        $rows = [];

        foreach ($sessions as $session) {
            $attempt = $session->attempts
                ->filter(fn ($row) => $row->status === 'finished' && $row->score !== null)
                ->sortByDesc('updated_at')
                ->first();

            if ($attempt === null) {
                continue;
            }

            $immediate = $this->correctionIsImmediate($session);

            if (! $immediate && ! $session->isExpired()) {
                continue;
            }

            $revealed = $immediate ? $attempt->updated_at : $session->expires_at;

            if ($revealed === null || $revealed->lt(now()->subDays(42))) {
                continue;
            }

            $rows[] = [
                'id' => $session->id,
                'attempt_id' => $attempt->id,
                'code' => $session->code,
                'paper_title' => $session->paper?->title ?? 'Session Kangourou',
                'score' => $attempt->score,
                'revealed_at' => $this->iso($revealed),
                'fresh' => $revealed->greaterThanOrEqualTo(now()->subDay()),
                '_sort' => $revealed->getTimestamp(),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['_sort'] <=> $a['_sort']);

        return array_map(function (array $row): array {
            unset($row['_sort']);

            return $row;
        }, $rows);
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  list<int>  $shownIds
     * @return list<array<string, mixed>>
     */
    private function studentFreshGrades(Collection $courses, array $shownIds): array
    {
        $rows = [];

        foreach ($courses as $course) {
            $expired = $course->jumps->filter(fn (Jump $jump) => $jump->status === 'expired')->sortBy('id')->values();

            foreach ($expired as $jump) {
                if (in_array($jump->id, $shownIds, true)) {
                    continue;
                }

                $attempt = $this->ownAttempt($jump);

                if ($attempt === null || $attempt->status !== 'finished' || $attempt->score === null || $attempt->updated_at === null) {
                    continue;
                }

                if ($attempt->updated_at->lt(now()->subDay())) {
                    continue;
                }

                $rows[] = [
                    'attempt_id' => $attempt->id,
                    'course_title' => $course->title,
                    'number' => $expired->search(fn (Jump $row) => $row->id === $jump->id) + 1,
                    'score' => (int) $attempt->score,
                    'created_at' => $this->iso($jump->created_at),
                    '_sort' => $attempt->updated_at->getTimestamp(),
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => $b['_sort'] <=> $a['_sort']);

        return array_map(function (array $row): array {
            unset($row['_sort']);

            return $row;
        }, array_slice($rows, 0, 3));
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, CarbonInterface>
     */
    private function studentFreshJumpTimes(Collection $courses): Collection
    {
        $times = collect();

        foreach ($courses as $course) {
            foreach ($course->jumps as $jump) {
                if ($jump->status !== 'expired') {
                    continue;
                }

                $attempt = $this->ownAttempt($jump);

                if ($attempt === null || $attempt->status !== 'finished' || $attempt->score === null || $attempt->updated_at === null) {
                    continue;
                }

                if ($attempt->updated_at->greaterThanOrEqualTo(now()->subDay())) {
                    $times->push($attempt->updated_at);
                }
            }
        }

        return $times;
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return list<array<string, mixed>>
     */
    private function teacherOpenJumps(Collection $courses): array
    {
        return $courses
            ->flatMap(fn (Course $course) => $course->jumps)
            ->filter(fn (Jump $jump) => $jump->status === 'active' && ! $jump->isClosed())
            ->sortByDesc('id')
            ->map(fn (Jump $jump) => [
                'id' => $jump->id,
                'course_id' => $jump->course_id,
                'course_title' => $jump->course->title,
                'nb_questions' => $jump->nb_questions,
                'time' => $jump->time,
                'expiration' => $this->iso($jump->expiration),
                'attempts_count' => (int) $jump->attempts_count,
                'updated_at' => $jump->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, KangourouSession>  $sessions
     * @return list<array<string, mixed>>
     */
    private function teacherOpenSessions(Collection $sessions, User $user): array
    {
        return $sessions
            ->filter(fn (KangourouSession $session) => $session->isActive())
            ->sortByDesc('id')
            ->map(fn (KangourouSession $session) => [
                'id' => $session->id,
                'code' => $session->code,
                'paper_title' => $session->paper?->title ?? 'Session Kangourou',
                'expires_at' => $this->iso($session->expires_at),
                'attempts_count' => (int) $session->attempts_count,
                'is_author' => (int) $session->author_id === (int) $user->id,
                'updated_at' => $session->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return array{course: array<string, mixed>|null, shown_ids: list<int>}
     */
    private function teacherActiveCourse(Collection $courses): array
    {
        $best = $courses->flatMap(fn (Course $course) => $course->jumps)->sortByDesc('id')->first();

        if ($best === null) {
            return ['course' => null, 'shown_ids' => []];
        }

        $course = $best->course;
        $expired = $course->jumps->filter(fn (Jump $jump) => $jump->status === 'expired')->sortBy('id')->values();
        $recent = $expired->sortByDesc('id')->take(3)->map(fn (Jump $jump) => [
            'id' => $jump->id,
            'course_id' => $jump->course_id,
            'number' => $expired->search(fn (Jump $row) => $row->id === $jump->id) + 1,
            'created_at' => $this->iso($jump->created_at),
            'graded_attempts_count' => (int) $jump->graded_attempts_count,
            'fresh' => $jump->updated_at !== null && $jump->updated_at->greaterThanOrEqualTo(now()->subDay()),
        ])->values()->all();

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'recent_jumps' => $recent,
            ],
            'shown_ids' => array_column($recent, 'id'),
        ];
    }

    /**
     * @param  Collection<int, KangourouSession>  $sessions
     * @return list<array<string, mixed>>
     */
    private function teacherSessionCandidates(Collection $sessions, User $user): array
    {
        $rows = [];

        foreach ($sessions as $session) {
            if ($session->status === 'draft' || $session->expires_at === null) {
                continue;
            }

            $ended = $session->status === 'expired'
                || ($session->status === 'active' && $session->expires_at->isPast());

            if (! $ended || $session->expires_at->lt(now()->subDays(42))) {
                continue;
            }

            $rows[] = [
                'id' => $session->id,
                'paper_title' => $session->paper?->title ?? 'Session Kangourou',
                'expires_at' => $this->iso($session->expires_at),
                'attempts_count' => (int) $session->attempts_count,
                'is_author' => (int) $session->author_id === (int) $user->id,
                'fresh' => $session->expires_at->greaterThanOrEqualTo(now()->subDay()),
                '_sort' => $session->expires_at->getTimestamp(),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['_sort'] <=> $a['_sort']);

        return array_map(function (array $row): array {
            unset($row['_sort']);

            return $row;
        }, $rows);
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, Jump>
     */
    private function teacherFreshJumps(Collection $courses): Collection
    {
        return $courses
            ->flatMap(fn (Course $course) => $course->jumps)
            ->filter(fn (Jump $jump) => $jump->status === 'expired'
                && $jump->updated_at !== null
                && $jump->updated_at->greaterThanOrEqualTo(now()->subDay()))
            ->sortByDesc(fn (Jump $jump) => $jump->updated_at?->getTimestamp() ?? 0)
            ->values();
    }

    private function expiredNumber(Jump $jump): int
    {
        $expired = $jump->course->jumps
            ->filter(fn (Jump $row) => $row->status === 'expired')
            ->sortBy('id')
            ->values();

        return $expired->search(fn (Jump $row) => $row->id === $jump->id) + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $openJumps
     * @param  list<array<string, mixed>>  $openSessions
     * @param  Collection<int, CarbonInterface>  $freshTimes
     * @param  Collection<int, mixed>  $questionTimes
     */
    private function signalAt(?int $priority, array $openJumps, array $openSessions, Collection $freshTimes, Collection $questionTimes): ?string
    {
        $stamp = match ($priority) {
            1 => collect($openJumps)->pluck('updated_at')->filter(),
            2 => collect($openSessions)->pluck('updated_at')->filter(),
            3 => $freshTimes,
            4 => $questionTimes->map(fn ($at) => $at instanceof CarbonInterface ? $at : Carbon::parse($at)),
            default => collect(),
        };

        $latest = $stamp
            ->filter()
            ->sortByDesc(fn ($at) => $at instanceof CarbonInterface ? $at->getTimestamp() : Carbon::parse($at)->getTimestamp())
            ->first();

        return $latest === null ? null : $this->iso($latest instanceof CarbonInterface ? $latest : Carbon::parse($latest));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withoutInternal(array $rows): array
    {
        return array_map(function (array $row): array {
            unset($row['updated_at']);

            return $row;
        }, $rows);
    }

    private function ownAttempt(Jump $jump): ?JumpAttempt
    {
        return $jump->attempts->first();
    }

    private function correctionIsImmediate(KangourouSession $session): bool
    {
        return ($session->getEffectivePreferences()['correction'] ?? 'delayed') === 'immediate';
    }

    private function priority(bool $openJumps, bool $openSessions, bool $freshGrade, bool $hasQuestions): ?int
    {
        if ($openJumps) {
            return 1;
        }

        if ($openSessions) {
            return 2;
        }

        if ($freshGrade) {
            return 3;
        }

        if ($hasQuestions) {
            return 4;
        }

        return null;
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }

    /**
     * @param  Collection<int, Division>  $divisions
     * @return Collection<int, Division>
     */
    private function sorted(Collection $divisions): Collection
    {
        return $divisions->sort(function (Division $a, Division $b): int {
            $byName = strcasecmp((string) $a->name, (string) $b->name);

            return $byName !== 0 ? $byName : $a->id <=> $b->id;
        })->values();
    }
}
