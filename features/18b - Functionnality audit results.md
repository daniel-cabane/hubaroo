# Functionality Audit — Hubaroo

> Auditor: Principal Software Engineer / Senior Solutions Architect / Cybersecurity Reviewer  
> Date: 2026-05-23  
> Stack: Laravel 12 · PHP 8.2 · Vue 3 (Pinia / Vue Router) · Tailwind CSS v4 · Laravel Reverb (WebSockets) · Pest 3

---

## Functionality Map (discovered from code + feature specs)

| # | Feature | Status |
|---|---------|--------|
| 1 | Paper management (import, display) | Implemented |
| 2 | Kangourou Session (create, join, code, privacy) | Implemented |
| 3 | Attempt (guest + auth, 26 questions, timer, submit, blur alarm, correction) | Implemented |
| 4 | Rejoin demand (student → teacher approval, real-time) | Implemented |
| 5 | Session expiry (scheduled job, auto-grade, broadcast) | Implemented |
| 6 | Division / Class system (create, join, invite, archive) | Implemented |
| 7 | Class name formatting | Implemented |
| 8 | Session ↔ Division assignment | Implemented |
| 9 | Real-time notifications (Reverb + Echo) | Implemented |
| 10 | Session analysis per division (success ratio) | Implemented |
| 11 | Mastery & difficulty dynamic scoring | Implemented (with logic bug) |
| 12 | Course (Parcours) + Jump (Saut) | Implemented |
| 13 | Jump question selection (difficulty-adaptive) | Implemented |
| 14 | Jump expiry, grading, mastery update | Implemented |
| 15 | Jump analysis → Suggested Questions | Implemented |
| 16 | Public suggested questions (student highlight) | Implemented |
| 17 | Jump rejoin demand | Implemented |
| 18 | Bug reporting (user + admin panel) | Implemented |
| 19 | Google OAuth | Implemented (partially — see Critical #4) |
| 20 | Recent attempts overlay (site-wide panel) | Implemented |
| 21 | Guest attempt claim after login | Implemented |
| 22 | Shuffle options for session questions | Implemented |
| 23 | Question shuffle notes to student | Implemented |
| 24 | Admin panel (users, roles, papers, bug reports) | Implemented |

---

## 1. Executive Summary

**Overall Code Health Score: 66 / 100**

The codebase is architecturally sound and impressively feature-complete for its scope. The developer has correctly used Eloquent relationships, service classes, form request validation, Laravel policies, and a real-time event system. However, several security gaps — most critically the **absence of ownership checks on attempt mutation endpoints** — would allow any authenticated user to sabotage other users' attempts. Three additional critical issues (logic bug in mastery calculation, public channels carrying sensitive events, and role-less Google OAuth users) compound the risk.

**Top 3 issues requiring immediate action:**

1. **Any authenticated user can update or submit any attempt** (`AttemptController::updateAnswer`, `::submit`, `::show`) — no ownership enforcement.
2. **`UpdateMasteryAndDifficulty` job has a logic bug** — mastery only reflects the delta of the *last* qualifying question, discarding all earlier deltas in the same submission.
3. **`SessionExpired` and `AttemptNameUpdated` events broadcast on unauthenticated public channels** — any visitor listening on `session.{id}` or `attempt.{id}` receives live session-expiry and name-update signals.

---

## 2. Deep-Dive Analysis

---

CRITICAL DON'T IGNORE : The following issues have been reviewed by the boss. He wrote his will under each title starting with this >>> BOSS DECISION. You have to follow this decision to the letter ! 
- If the boss decision is to fix the issue, follow the recommandation given below and fix it to the best of your ability.
- If the boss decision is to ignore the issue, you have to do NOTHING. Do not read the issue, do not try to fix it.
If the boss made further comment, pay special attention to it.


### [CRITICAL] — C1: No ownership check on `AttemptController::updateAnswer` and `::submit`
>>> BOSS DECISION : fix this issue

> ✅ **Fixed** — Both `updateAnswer` and `submit` now return 403 if an authenticated user attempts to act on an attempt they do not own. The guard only activates when `$user` is set and `$attempt->user_id` is non-null, so guest flows are completely unaffected.

**File:** `app/Http/Controllers/AttemptController.php` — lines 118–195

**The Problem:**  
Neither `updateAnswer` nor `submit` verifies that the calling user owns the attempt. For guests it is impossible to check (no session-bound identity), but for authenticated users the check is completely absent. Any logged-in user who knows an attempt ID can overwrite any other user's answers or force-submit their attempt mid-session.

```php
// Current — no ownership check at all
public function updateAnswer(UpdateAnswerRequest $request, Attempt $attempt): JsonResponse
{
    if ($attempt->status === 'finished') { ... }
    ...
}
```

**The Fix:**
```php
public function updateAnswer(UpdateAnswerRequest $request, Attempt $attempt): JsonResponse
{
    $user = $request->user();
    if ($user && $attempt->user_id !== null && $attempt->user_id !== $user->id) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }

    if ($attempt->status === 'finished') { ... }
    ...
}
```
Apply the same guard to `submit()`. For guests, ownership is implied by them knowing the `attempt_id` (stored in `localStorage`), which is an acceptable trade-off for a guest flow — but authenticated users must never be able to act on another user's attempt.

---

### [CRITICAL] — C2: `AttemptController::show` exposes any attempt to any caller
>>> BOSS DECISION : fix this issue

> ✅ **Fixed** — `show` now accepts a `Request $request` parameter and returns 403 if an authenticated user tries to read another user's attempt, unless they are the session author (teacher). Guest callers and attempts with `user_id = null` are still accessible to anyone.

**File:** `app/Http/Controllers/AttemptController.php` — line 104

**The Problem:**  
`GET /api/attempts/{attempt}` has no authorization guard. Any caller can read the full attempt record (including all answers) for any attempt ID. While `maskCorrectionIfNeeded` hides *statuses*, the raw answer letters (`A`, `B`, `C`, …) are still present in the payload.

**The Fix:**
```php
public function show(Attempt $attempt, Request $request): JsonResponse
{
    $user = $request->user();
    if ($user && $attempt->user_id !== null && $attempt->user_id !== $user->id) {
        // Also allow the session author to view (for session details)
        $session = $attempt->kangourouSession;
        if ($session->author_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
    }
    ...
}
```

---

### [CRITICAL] — C3: `UpdateMasteryAndDifficulty` job discards all but the last mastery delta
>>> BOSS DECISION : fix this issue. This is important, double check that it is working correctly.

> ✅ **Fixed** — `$userMastery` is now initialised once before the loop and accumulated with `+=` / `-=` on each qualifying iteration, matching the correct pattern already used in `ExpireJumps::updateMasteryAndDifficulty`. `$user->mastery` is assigned once after the loop and saved. Note: the original code was placing `$userMastery = $user->mastery ?? 0` *inside* the loop (re-reading the Eloquent attribute each iteration), so the practical impact was smaller than the audit's description suggested — but the refactor is correct, cleaner, and consistent with the rest of the codebase.

**File:** `app/Jobs/UpdateMasteryAndDifficulty.php` — lines 23–49

**The Problem:**  
`$userMastery` is captured once before the loop and **never updated inside the loop**. Each iteration computes its delta relative to the *original* mastery and writes to `$user->mastery`, overwriting any update from a previous iteration. Only the final iteration's delta survives.

```php
// BUG — $userMastery never changes; every iteration overwrites $user->mastery
$userMastery = $user->mastery ?? 0;
foreach ($questions as $index => $question) {
    ...
    if ($difference < 0 && $isCorrect) {
        $user->mastery = $userMastery + (int) ceil(-$difference * 0.1); // re-uses stale $userMastery
        ...
    }
}
$user->save(); // only last iteration's value survives
```

Contrast with `ExpireJumps::updateMasteryAndDifficulty` (line 108) which correctly accumulates `$userMastery += delta` inside the loop. That pattern is the fix.

**The Fix:**
```php
$userMastery = $user->mastery ?? 0;
foreach ($questions as $index => $question) {
    ...
    if ($difference < 0 && $isCorrect) {
        $userMastery += (int) ceil(-$difference * 0.1);   // update running variable
        $question->difficulty -= (int) ceil(-$difference * 0.01);
        $question->save();
    } elseif ($difference > 0 && ! $isCorrect) {
        $userMastery -= (int) ceil($difference * 0.1);
        $question->difficulty += (int) ceil($difference * 0.01);
        $question->save();
    }
}
$user->mastery = $userMastery;
$user->save();
```
Also note: `$difference` should be recalculated each iteration using the running `$userMastery`, not the stale one, to match the spec's intent.

---

### [CRITICAL] — C4: Google OAuth creates users without a role
>>> BOSS DECISION : IGNORE this issue - this issue is dealt with in App.vue

**File:** `app/Http/Controllers/Auth/GoogleAuthController.php` — line 37

**The Problem:**  
When a brand-new user signs in via Google for the first time, `User::create([...])` is called without `assignRole()`. The user lands on the home page with no `Teacher` or `Student` role, causing all role-checks (`$user->hasRole('Teacher')`, `is_teacher`, `is_student`) to silently return `false`. Features like creating sessions, joining classes, and submitting bug reports become unavailable or behave incorrectly.

```php
// No role assigned here
$user = User::create([
    'name' => $googleUser->getName(),
    'email' => $googleUser->getEmail(),
    'google_id' => $googleUser->getId(),
    'email_verified_at' => now(),
]);
```

**The Fix:**  
After the Google callback, redirect the new user to a role-selection screen (reusing the existing `POST /user/role` endpoint and `assignRole` flow in `AuthController`), or flag the user as `needs_role = true` and gate all protected features behind a middleware check.

---

### [CRITICAL] — C5: `SessionExpired` broadcasts on a public, unauthenticated channel
>>> BOSS DECISION : fix this issue. Check that it doesn't prevent guests from joining Kangourou sessions (if they have the code)

> ✅ **Fixed** — Both `SessionExpired` and `AttemptNameUpdated` now use `PrivateChannel`. `AttemptNameUpdated` broadcasts on `attempt.{id}` (private). `channels.php` was updated to authorise both channels: `session.{id}` now also admits authenticated session participants (not just the author); a new `attempt.{id}` authorisation block allows the attempt owner and the session author. In `attempt-view.vue`, the Echo subscriptions are wrapped in an `authStore.isAuthenticated` guard so they only run for logged-in users — guests rely on the existing client-side countdown timer for session expiry, preserving full guest functionality.

**File:** `app/Events/SessionExpired.php` — line 26  
**File:** `app/Events/AttemptNameUpdated.php` — line 26  
**File:** `resources/js/components/views/attempt-view.vue` — lines 684, 691

**The Problem:**  
`SessionExpired` uses `new Channel(...)` (public), meaning **anyone** who connects to `session.{id}` receives the expiry signal. A malicious client could listen on all session channels (IDs are sequential integers) and collect metadata. `AttemptNameUpdated` has the same issue — it broadcasts on `attempt.{id}` public channel, but there is no channel authorization definition for it in `channels.php`.

By contrast, `AttemptUpdated` correctly uses `new PrivateChannel(...)`. The inconsistency is the bug.

**The Fix:**
```php
// SessionExpired.php
public function broadcastOn(): array
{
    return [new PrivateChannel('session.'.$this->session->id)];
}

// AttemptNameUpdated.php
public function broadcastOn(): array
{
    return [new PrivateChannel('session.'.$this->attempt->kangourou_session_id)];
}
```
And in `attempt-view.vue`, change:
```js
window.Echo.channel(`session.${session.value.id}`)   // public
window.Echo.channel(`attempt.${attemptStore.attempt.id}`)  // public
```
to:
```js
window.Echo.private(`session.${session.value.id}`)
window.Echo.private(`session.${session.value.id}`)  // merge into same private channel
```
Add a channel authorization in `channels.php` for attempt owners and session participants.

---

### [WARNING] — W1: `ExpireJumps` does not implement `ShouldQueue` — runs synchronously
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — `ExpireJumps` now implements `ShouldQueue`. The class declaration was updated from `class ExpireJumps` to `class ExpireJumps implements ShouldQueue`, and the `ShouldQueue` contract was imported. The job is now dispatched to the queue by the scheduler instead of running synchronously.

**File:** `app/Jobs/ExpireJumps.php` — class declaration  
**File:** `routes/console.php` — line 12

**The Problem:**  
`ExpireKangourouSessions` implements `ShouldQueue` and is dispatched to the queue. `ExpireJumps` uses `Queueable` but does **not** implement `ShouldQueue`. `Schedule::job(new ExpireJumps)->everyMinute()` runs it synchronously inline, blocking the scheduler process for as long as grading takes. With a large class (30+ students, multiple expiring jumps), this can exceed the 60-second scheduler window and cause missed runs.

**The Fix:**
```php
class ExpireJumps implements ShouldQueue
{
    use Queueable;
    ...
}
```

---

### [WARNING] — W2: Race condition in mastery/difficulty updates (concurrent jobs)
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — Both `UpdateMasteryAndDifficulty::handle()` and `ExpireJumps::updateMasteryAndDifficulty()` now wrap the entire mastery/difficulty update loop in `DB::transaction()` with `User::lockForUpdate()->find($userId)`. This ensures that concurrent jobs for the same user are serialised at the database level, preventing lost updates. The `User` model and `DB` facade imports were added to `ExpireJumps`.

**File:** `app/Jobs/UpdateMasteryAndDifficulty.php` — lines 15–51  
**File:** `app/Jobs/ExpireJumps.php` — `updateMasteryAndDifficulty` method

**The Problem:**  
Both jobs read `$user->mastery`, compute a delta, and write back — without any database-level lock. If two jobs run concurrently for the same user (e.g., a student submits a Kangourou session while a jump expires), one write will be lost (classic lost-update).

**The Fix:**  
Use a database-level lock for the user row:
```php
DB::transaction(function () use ($user, $questions, $answers) {
    $user = User::lockForUpdate()->find($user->id);
    $userMastery = $user->mastery ?? 0;
    // ... loop ...
    $user->mastery = $userMastery;
    $user->save();
});
```

---

### [WARNING] — W3: Race condition in `BugReportController` — limit check not atomic
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — The unsolved-count check and `BugReport::create()` are now wrapped in a single `DB::transaction()` with `->lockForUpdate()` on the count query. If the limit is exceeded, a `ValidationException` is thrown inside the transaction (causing a rollback), which the global exception handler converts to a 422 JSON response. The `DB` facade and `ValidationException` imports were added.

**File:** `app/Http/Controllers/BugReportController.php` — lines 23–37

**The Problem:**  
The "maximum 5 unsolved bug reports" check reads the count and then inserts in two separate queries. Concurrent requests can both pass the count check before either inserts, allowing more than 5 records to be created.

**The Fix:**  
Wrap in a transaction with a lock:
```php
DB::transaction(function () use ($user, $request) {
    $unsolvedCount = BugReport::where('user_id', $user->id)
        ->whereIn('status', BugReport::unsolvedStatuses())
        ->lockForUpdate()
        ->count();

    if ($unsolvedCount >= 5) {
        throw ValidationException::withMessages([...]);
    }

    BugReport::create([...]);
});
```

---

### [WARNING] — W4: Race condition in `generateCode()` — check-then-act without locking
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — All five call sites that call `generateCode()` and then create/update a model (`KangourouSessionController::store`, `::changeCode`, `DivisionController::store`, `::changeCode`, `AttemptController::store`) are now wrapped in a `do { try { … break; } catch (UniqueConstraintViolationException) { } } while (true);` retry loop. The database unique constraint remains the true guard; the retry loop handles the rare race-condition window. Pint normalised the catch to the fully-qualified `\Illuminate\Database\UniqueConstraintViolationException`.

**File:** `app/Models/KangourouSession.php`, `Attempt.php`, `Division.php` — `generateCode()` methods

**The Problem:**  
All three models use the same pattern: generate a random code, check if it exists in the DB, retry if so. Between the `exists()` check and the `INSERT`, another concurrent request can generate the same code and pass the check, resulting in a unique constraint violation (which is unhandled — it will throw an uncaught `QueryException`).

**The Fix:**  
Keep the uniqueness constraint on the database column (it already exists from migrations) and wrap the create call in a try/catch to retry on constraint violation:
```php
public static function generateCode(): string
{
    do {
        $code = strtoupper(Str::random(6));
        try {
            // The DB constraint is the real guard; the loop is just for a clean API
            return $code;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // retry
        }
    } while (true);
}
```
Alternatively, add a database-level retry in the controller when creating the model.

---

### [WARNING] — W5: Blur countdown not reset on repeated blur events
>>> BOSS DECISION : ignore this issue. This is working as intended.

**File:** `resources/js/components/views/attempt-view.vue` — lines 633–643

**The Problem:**  
`blurCountdown` is initialized to `10` once. Inside `handleBlur`, the line `blurCountdown.value = 10;` is **commented out**. If a student triggers the blur alarm once (say it counts to 3), returns, then blurs again, the countdown resumes from 3 (or wherever `blurCountdown` was left). In the worst case, if a previous `blurInterval` was not fully cleared, the student could receive an instant auto-submit on second blur.

```js
function handleBlur() {
  if (isInProgress.value) {
    showBlurAlarm.value = true;
    // blurCountdown.value = 10;   <-- BUG: reset is commented out
    blurInterval = setInterval(() => { ... }, 1000);
  }
}
```

**The Fix:**  
Uncomment the reset line and also clear any existing `blurInterval` before starting a new one:
```js
function handleBlur() {
  if (isInProgress.value) {
    if (blurInterval) clearInterval(blurInterval);
    blurCountdown.value = 10;
    showBlurAlarm.value = true;
    blurInterval = setInterval(() => { ... }, 1000);
  }
}
```

---

### [WARNING] — W6: No rate limiting on any route
>>> BOSS DECISION : fix this issue but be quite generous with the limits. Make sure that there is no chance that a non malicious use would hit the limit.

> ✅ **Fixed** — Throttle middleware added directly to sensitive routes in `routes/web.php`: login and register get `throttle:30,1`; forgot-password and reset-password get `throttle:10,1`; all guest API routes (attempts store/show/answer/submit) get `throttle:300,1`. The recover endpoint is rate-limited under W12. All limits are generous enough that no legitimate user would ever hit them.

**File:** `bootstrap/app.php`, `routes/web.php`

**The Problem:**  
No `throttle` middleware is applied to any route. The login endpoint, session-join endpoint, code-guessing flow, and answer-save endpoint are all unbounded. Practical risks:
- Brute-force login attacks
- Enumeration of session and attempt codes
- Flooding the Reverb broadcast system by rapid answer updates
- Denial-of-service via mass attempt creation

**The Fix:**  
Apply throttle middleware in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->throttleApi(); // applies default 60/min throttle to api routes
})
```
And apply specific limits to sensitive routes:
```php
Route::middleware(['auth', 'throttle:5,1'])->post('/login', ...); // 5 attempts/min
Route::middleware(['throttle:30,1'])->patch('/api/attempts/{attempt}/answer', ...);
```

---

### [WARNING] — W7: `openForDivision` missing independent authorization check
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — A `manageForDivision(User $user, KangourouSession $session, Division $division): bool` method was added to `KangourouSessionPolicy`, returning `true` only when the user owns both the session and the division. `DivisionController::openForDivision` now calls `$this->authorize('manageForDivision', [$session, $division])` instead of the manual `if` guard, integrating this check into the Laravel policy system with a proper 403 `AuthorizationException` response.

**File:** `app/Http/Controllers/DivisionController.php` — lines 254–265

**The Problem:**  
The authorization is done manually with an `if` condition rather than via Laravel's policy system. The condition `$session->author_id !== $request->user()->id || $division->teacher_id !== $request->user()->id` is technically correct (requires the user to own both the session AND the division), but it is easy to overlook and isn't covered by the policy system. More critically, if a teacher creates a session but a different teacher owns the division, the action is correctly blocked — but the error message is generic `'Unauthorized.'` rather than a differentiated response.

**The Fix:**  
Move this logic to `KangourouSessionPolicy` and call `$this->authorize('manageForDivision', [$session, $division])`.

---

### [WARNING] — W8: N+1 query in `JumpQuestionSelector::getExcludedQuestionIds`
>>> BOSS DECISION : fix this issue. Make sure it doesn't break the question selection process of the jump.

> ✅ **Fixed** — The Eloquent `->with('paper.questions:id')->get()->flatMap(...)` chain was replaced with a single direct DB query joining `paper_question` and `division_kangourou_session` tables. This reduces the query from `O(N sessions)` to a single subquery, and the result is identical: the set of question IDs used in any session of this division. All 241 tests still pass, confirming the question selection logic is unaffected.

**File:** `app/Services/JumpQuestionSelector.php` — lines 80–89

**The Problem:**  
```php
$sessionQuestionIds = $division->kangourouSessions()
    ->with('paper.questions:id')
    ->get()
    ->flatMap(fn ($session) => $session->paper?->questions?->pluck('id') ?? collect())
    ...
```
This eager-loads every `KangourouSession` for the division, with all their `Paper` and `Question` relationships. For a class with 20 past sessions of 26 questions each, this loads 520+ question records into memory just to get a list of IDs. As the class grows and runs more sessions, memory and query cost grow linearly.

**The Fix:**  
Use a direct query:
```php
$sessionQuestionIds = \DB::table('paper_question')
    ->whereIn('paper_id', function ($q) use ($division) {
        $q->select('paper_id')
          ->from('division_kangourou_session')
          ->where('division_id', $division->id);
    })
    ->pluck('question_id')
    ->unique()
    ->values()
    ->toArray();
```

---

### [WARNING] — W9: `JumpQuestionSelector` loads all questions into memory
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — `Question::whereNotIn('id', $excludedQuestionIds)->get()` was replaced with a `whereBetween('difficulty', [$minTarget - 500, $maxTarget + 500])` filter, where `$minTarget = $mastery - 200` and `$maxTarget = min(2300, $mastery + 100 * $growth)`. This fetches only questions within the relevant difficulty band. A fallback to all non-excluded questions is retained for the degenerate case where the range yields nothing.

**File:** `app/Services/JumpQuestionSelector.php` — line 22

**The Problem:**  
```php
$availableQuestions = Question::whereNotIn('id', $excludedQuestionIds)->get();
```
This loads the **entire questions table** (potentially thousands of rows) into a PHP collection, then sorts and filters in-memory. With 22 years × 6 levels × 26 questions = 3,432 questions (current max), this is manageable now, but poor practice.

**The Fix:**  
Fetch a targeted set of questions near the target difficulty using SQL, then take the closest in PHP. For example, fetch questions within ±500 difficulty of the target rather than all questions.

---

### [WARNING] — W10: `Jump::rank()` Attribute fires one query per jump
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — `rank` was removed from `Jump::$appends`, eliminating the automatic N+1. Rank is now computed at the point of use: in `CourseController::index` and `::details`, jumps are ordered by `id` and rank is set via array index (`$index + 1`); in `JumpAttemptController::show`, a single `COUNT` query computes rank for the one returned jump; in `JumpAttemptController::myIndex`, ranks are computed per course in a single query per unique course; in `JumpRejoinDemandController::myIndex`, which already built the rank explicitly, a `COUNT` query replaces the old accessor call.

**File:** `app/Models/Jump.php` — lines 68–73

**The Problem:**  
```php
public function rank(): Attribute
{
    return Attribute::make(
        get: fn () => Jump::where('course_id', $this->course_id)
            ->where('id', '<=', $this->id)
            ->count(),
    );
}
```
`$appends = ['rank']` means this attribute is serialized automatically. Whenever a collection of jumps is returned (e.g., `CourseController::details` loads all jumps), this fires one `COUNT` query per jump. For a course with 10 jumps, that's 10 extra queries.

**The Fix:**  
Remove `rank` from `$appends`. Compute rank in the frontend using the array index, or calculate it in the controller using `->withCount(...)` / SQL window functions.

---

### [WARNING] — W11: `ExpireKangourouSessions` — N+1 in `computeAnalysis`
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — `handle()` now eager-loads `paper.questions` (ordered) and `divisions.students` before the loop. `computeAnalysis()` was refactored to: (1) use the already-loaded `$session->paper->questions` instead of re-querying, (2) load all finished attempts for the session in one query before the division loop, and (3) filter attempts per division in PHP using `$allFinishedAttempts->whereIn('user_id', ...)`. This reduces the per-session query count from `O(D * 3)` to `O(1 + D * 0)` extra queries.

**File:** `app/Jobs/ExpireKangourouSessions.php` — lines 32–66

**The Problem:**  
```php
foreach ($sessions as $session) {
    ...
    $this->computeAnalysis($session);
    ...
}
```
Inside `computeAnalysis`, for each division associated with the session:
- It calls `$session->paper->questions()` (separate query if not eager-loaded)
- It calls `Attempt::where(...)->whereIn('user_id', $studentIds)->get()` per division

If 5 sessions expire simultaneously and each is open to 3 divisions, this is at least 15 separate attempt queries plus question queries.

**The Fix:**  
Eager-load divisions, students, and attempts before the loop:
```php
$session->load(['paper.questions', 'divisions.students', 'attempts']);
```

---

### [WARNING] — W12: Guest attempt recovery endpoint has no brute-force protection
>>> BOSS DECISION : fix this issue but be quite generous with the limits. Make sure that there is no chance that a non malicious use would hit the limit. Also make sure that this does not prevent guests to access all functionnalities of a Kangourou session if they have the code.

> ✅ **Fixed** — `throttle:60,1` (60 requests per minute) was applied to `GET /api/attempts/recover/{code}`. A legitimate guest would call this endpoint at most once per session recovery; 60/min is far more than needed and has no impact on normal usage. Guest access to Kangourou sessions (join by code, create/update/submit attempts) is unaffected as those routes use the more generous `throttle:300,1`.

**File:** `app/Http/Controllers/AttemptController.php` — `recover()` method  
**File:** `routes/web.php` — `GET /api/attempts/recover/{code}` (unauthenticated)

**The Problem:**  
Anyone can call `GET /api/attempts/recover/{code}` with arbitrary 6-character codes. There is no rate limiting, no authentication, and the response includes the full attempt object including all answers and session details. With no throttle on this unauthenticated endpoint, an attacker could enumerate recovery codes and access other users' attempt data.

**The Fix:**  
Apply aggressive rate limiting (`throttle:10,1`) and consider requiring either authentication or the `STORAGE_KEY` from `localStorage` to also be passed for cross-validation.

---

### [OPTIMIZATION] — O1: Global eager component registration in `app.js`
>>> BOSS DECISION : IGNORE this issue. This will be dealt with later.

**File:** `resources/js/app.js` — lines 23–25

**The Problem:**  
```js
Object.entries(import.meta.glob('./**/*.vue', { eager: true })).forEach(([path, definition]) => {
    app.component(path.split('/').pop().replace(/\.\w+$/, ''), definition.default);
});
```
This registers every single Vue component in the entire project eagerly (including all 22 views). They are all bundled into the initial JS chunk, meaning a user visiting only the home page downloads the code for the admin panel, course details view, and all other views. This inflates the initial bundle unnecessarily.

**The Fix:**  
Remove the glob registration. Use Vue Router's lazy-loading (already possible since routes are already imported directly in `router.js`):
```js
const AdminView = () => import('./components/views/admin-view.vue');
```

---

### [OPTIMIZATION] — O2: `attemptStore.js` fires N HTTP calls to fetch guest attempts
>>> BOSS DECISION : IGNORE this issue. This will be dealt with later.

**File:** `resources/js/stores/attemptStore.js` — lines 64–74

**The Problem:**  
```js
const results = await Promise.all(
    ids.map(id => axios.get(`/api/attempts/${id}`).then(...))
);
```
For a guest user with 5 past attempts, this fires 5 parallel API calls on page load. Each call returns a full attempt with its session and paper relationship loaded. A single batch endpoint would be more efficient.

**The Fix:**  
Add a `POST /api/attempts/batch` endpoint that accepts an array of IDs and returns all matching attempts in one query. Use `Attempt::whereIn('id', $ids)->with('kangourouSession.paper')->get()`.

---

### [OPTIMIZATION] — O3: `DivisionPolicy::view` fires a DB query on every authorization check
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — `DivisionPolicy::view` now short-circuits to a collection check when the `students` relation is already loaded, avoiding a redundant DB query. The fallback `->exists()` query is retained for cases where the relation is not pre-loaded.

**File:** `app/Policies/DivisionPolicy.php` — line 14

**The Problem:**  
```php
public function view(User $user, Division $division): bool
{
    return $division->teacher_id === $user->id
        || $division->students()->where('user_id', $user->id)->exists();
}
```
`$division->students()->where(...)` always fires a DB query. If divisions are loaded with students already eager-loaded, this bypasses the cache and re-queries. The `show`, `index`, and other division routes call `$this->authorize('view', $division)`.

**The Fix:**  
If students are eager-loaded, use the collection:
```php
public function view(User $user, Division $division): bool
{
    if ($division->teacher_id === $user->id) {
        return true;
    }
    if ($division->relationLoaded('students')) {
        return $division->students->contains('id', $user->id);
    }
    return $division->students()->where('user_id', $user->id)->exists();
}
```

---

### [OPTIMIZATION] — O4: `AttemptController::myIndex` loads all attempts with no pagination
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — `->limit(50)` added to the query in `myIndex()`. The 50 most recent attempts are returned, which is more than enough for all current frontend usages (`RecentAttemptsPanel` takes 5, `home-view` takes 3, `my-attempts-view` lists all returned). Full frontend pagination was not implemented as the frontend would require larger changes, and `limit(50)` is the appropriate incremental fix recommended by the audit.

**File:** `app/Http/Controllers/AttemptController.php` — `myIndex()` method

**The Problem:**  
```php
$attempts = $request->user()->attempts()->with('kangourouSession.paper')->latest()->get();
```
A power user who has done 200+ attempts gets all of them in one response. No pagination is applied.

**The Fix:**  
Use `->paginate(20)` or `->latest()->limit(50)->get()` and implement frontend pagination or infinite scroll.

---

### [OPTIMIZATION] — O5: Client-side timer relies on client clock — susceptible to skew
>>> BOSS DECISION : IGNORE this issue for now. This will be reworked later.

**File:** `resources/js/components/views/attempt-view.vue` — `startCountdown()` function (line ~570)

**The Problem:**  
```js
const deadline = new Date(attemptCreatedAt.getTime() + timeLimitMinutes * 60 * 1000 + extraTimeSeconds * 1000);
```
The deadline is computed entirely on the client using `attempt.created_at` (a server timestamp). If the client's system clock is significantly ahead, the timer will expire early; if behind, it won't expire on time. In a competitive/exam setting, this is exploitable.

**The Fix:**  
Return a server-computed `deadline` timestamp from the API alongside the attempt, or return the server's current time so the client can compute an offset.

---

### [OPTIMIZATION] — O6: `KangourouSessionController::show` — answers masking done in PHP on large collections
>>> BOSS DECISION : fix this issue.

> ✅ **Fixed** — Created `app/Http/Resources/QuestionResource.php` with a `$showAnswers` constructor parameter. When `false`, `correct_answer` is unset from the output declaratively. `KangourouSessionController::show` now uses `QuestionResource` to rebuild the questions array when hiding answers, replacing the manual `collect()->map()->unset()` chain. All existing session correction-mode tests pass (241/241).

**File:** `app/Http/Controllers/KangourouSessionController.php` — lines 42–56

**The Problem:**  
The response maps over all questions to unset `correct_answer`. Since questions are already loaded via Eloquent, this creates a PHP-level map on every session fetch. A cleaner approach is to use a conditional Eloquent `select` or an API Resource with a context-aware `when()` clause.

**The Fix:**  
Use an `ApiResource` with `$this->when($showAnswers, fn() => $this->correct_answer)` to handle this declaratively.

---

## 3. Architecture & Scalability Review

### Current Architecture

The application follows a clean **SPA + REST API** pattern:
- **Backend:** Laravel 12 API (session-auth via cookies), Eloquent ORM, queued jobs, policy-based authorization, Laravel Reverb for WebSockets.
- **Frontend:** Vue 3 SPA with Pinia stores, Vue Router, and `laravel-echo` for real-time updates.
- **Real-time:** Events dispatched to private/public Reverb channels. Channel authorization defined in `channels.php`.

The overall separation of concerns is good. Service classes (`GradingService`, `JumpQuestionSelector`, `ClassNameFormatter`) correctly extract business logic. Form Requests handle validation. Events/Jobs handle async work.

### Scalability at 10k+ Active Users

| Concern | Assessment |
|---------|-----------|
| **Database** | The schema has appropriate indexes on foreign keys via migrations. However, the `jump_user` table doubling as both a pivot and a full model creates confusion. The `question_list` JSON column on every attempt row means JSON scanning is needed for aggregate analytics — a potential bottleneck at scale. |
| **Queue** | Mastery/difficulty updates are correctly dispatched to the queue. However, `ExpireJumps` runs synchronously (W1), which will block under load. |
| **Memory** | `JumpQuestionSelector` loads all available questions into memory per attempt creation. At 3,432 questions today this is ~500KB of PHP objects per request; fine now, problematic at 10k concurrent new jump attempts. |
| **Broadcast** | `AttemptUpdated` broadcasts the **full attempt payload** including all 26 answers on every single answer change. For a 30-student class all answering simultaneously, that's 30 × 26 × 5 = 3,900 broadcast events in a 50-minute session. Each event carries ~2KB of JSON. This is a significant Reverb load. Consider broadcasting only the changed answer index rather than the full payload. |
| **Scheduler** | Both `ExpireKangourouSessions` and `ExpireJumps` run every minute. With many concurrent sessions, the scheduler jobs could overlap. Both should use `->withoutOverlapping()`. |
| **Sessions with no pagination** | `myIndex` endpoints return all records. At scale, the `/api/my/attempts` endpoint will return hundreds of rows per user. |

**Verdict:** The app architecture will handle a few hundred concurrent users well. To reach 10k+ reliably, the top priorities are: fixing the synchronous `ExpireJumps`, adding pagination, reducing broadcast payload size, and addressing the questions-loading memory issue.

---

## 4. Actionable Checklist

### Do This First (Security / Correctness)

- [ ] **C1** — Add ownership check to `AttemptController::updateAnswer` and `::submit` so only the attempt owner can mutate their attempt
- [ ] **C2** — Add authorization to `AttemptController::show` to prevent reading other users' answers
- [ ] **C3** — Fix `UpdateMasteryAndDifficulty` job: update the running `$userMastery` variable inside the loop (match `ExpireJumps` pattern)
- [ ] **C4** — Handle role assignment for Google OAuth new users (redirect to role-selection after first Google login)
- [ ] **C5** — Change `SessionExpired` and `AttemptNameUpdated` from `Channel` (public) to `PrivateChannel` and update frontend listeners to `Echo.private(...)`
- [ ] **W5** — Uncomment `blurCountdown.value = 10` in `handleBlur()` in `attempt-view.vue` and clear existing interval before starting new one

### Do This Soon (Stability / Performance)

- [ ] **W1** — Add `implements ShouldQueue` to `ExpireJumps`
- [ ] **W6** — Configure Laravel's `throttle` middleware for login, code-recovery, and answer-update routes
- [ ] **W2** — Wrap mastery/difficulty updates in `DB::transaction()` with `lockForUpdate()` to prevent lost updates under concurrency
- [ ] **W3** — Make bug report limit check atomic with a transaction + lock
- [ ] **W11** — Eager-load `paper.questions` and `attempts` before calling `computeAnalysis` in `ExpireKangourouSessions`
- [ ] **W10** — Remove `rank` from `Jump::$appends`; compute it in the frontend using array index
- [ ] **W12** — Apply rate limiting to `GET /api/attempts/recover/{code}`

### Nice to Have (Code Quality / Scalability)

- [ ] **O1** — Switch to lazy-loaded Vue route components; remove the global eager component glob in `app.js`
- [ ] **O2** — Add a batch-fetch endpoint for guest attempts instead of N parallel calls
- [ ] **W8 / W9** — Rewrite `JumpQuestionSelector::getExcludedQuestionIds` using direct DB queries; fetch questions near the target difficulty from the DB rather than loading all questions
- [ ] **O4** — Add pagination to all `myIndex` endpoints
- [ ] **O5** — Return server-computed `deadline` timestamp from the API instead of relying on client clock arithmetic
- [ ] **W4** — Wrap model creation in a try/catch for `UniqueConstraintViolationException` as the true race-condition safety net
- [ ] **O6** — Replace the manual answer-masking map in `KangourouSessionController::show` with API Resource classes
- [ ] **O3** — Update `DivisionPolicy::view` to use the already-loaded students collection when available
- [ ] Add `->withoutOverlapping()` to both scheduled jobs in `routes/console.php`
- [ ] Reduce `AttemptUpdated` broadcast payload to only the changed answer index + status, not all 26 answers



# Prompts to put this plan to action

Read this file from line 1 to line 62. Pay extra attention to the instructions on lines 58 to 61.
Then read lines 545 to 659 and study the issues in the OPTIMIZATION section.
For each issue, fix it if the boss decision asks you to. Make sure it does not break any of the app functionnalities and run the full test suite. Then write a comment under the issue title to indicate that it has been fixed. If you have any special remark about the fix, add it there as well.
