# Rework: Jump grading

Status: **ready to implement**. Product decisions in section 3 are locked.

---

## Spec review

### Strengths & Core Takeaways

The objective is clear: stop awarding the raw difficulty (hundreds or thousands of points) and score each question on a small scale, with a gain for a right answer and a loss for a wrong one. Future jumps and already stored jumps are both in scope, and the correction UI must stop showing the internal difficulty number.

### Ambiguities & Open Questions

Resolved and locked in section 3:

- A blank stays 0. A wrong letter takes the penalty.
- The official jump total is floored at 0. Line points stay signed, so the header can read 0 while the rows show penalties.
- `Correcte : X` stays on a wrong answer.
- The admin button rewrites only attempts that are already fully graded on `expired` jumps.

### Additions & Suggestions

- One PHP function owns the formula. The UI reads a stored `points` value and does not reimplement rounding.
- Use the difficulty already saved on the attempt (`question_list[].difficulty`), the value the student was given. Live `questions.difficulty` keeps drifting after the attempt.
- Blank vs wrong must stay distinguishable in the points, because both already share the status `incorrect`.
- Student correction is shown only once the jump is `expired`. Teacher live observation on the division page already reveals right and wrong during the jump; that preview has to use the new point values too, or it will show +400 during the jump and +4 after.
- The correction row is copied in four screens, not two. A shared row component is what keeps them aligned.
- The admin action rewrites expired attempts that are already fully graded. It belongs in a queued job with a lock, and it must not re-run mastery, difficulty updates, or `AnalyseJump`.
- After the rewrite, broadcast `PocketChanged` for the affected divisions so open pockets drop the old totals.
- Ship the button as a release step. Until it is clicked, class tables, pocket, and rankings still show the old `jump_user.score`. Correction screens show the new line points and the floored total.
- Because the total is floored at 0, leave the existing `score ?? -1` ranking sentinel alone. A missing attempt stays below every real score.

### Recommended Deletions / Simplifications

- Do not add an admin tab, a per-attempt progress UI, or a second grading path. One header button, one modal, one service.
- Do not recompute mastery or question difficulty. This feature changes the displayed score only.
- Leave Kangourou session grading (`GradingService`) unchanged. The home-screen “Difficulté progressive (0–10)” control is a different setting; leave it.
- Leave answer comparison as it is (`===` on the stored letter). Case folding is a separate change.
- The class page already imports `JumpAttemptDetailModal` and does not render it. Switch that modal over to the shared row (or the shared modal) and drop the duplicated markup. Do not maintain a third copy.

---

## Implementation plan

### 1. Objective

Change jump scoring from “add the question difficulty when the answer is right, add 0 when it is wrong” to a small signed score:

- right answer: `round(difficulty / 100)` points
- wrong answer: lose `max(round(difficulty / 1000), 1)` points
- official jump total: the sum of those points, floored at 0

Apply that rule to every jump graded from now on, and provide an admin action that rewrites jumps already graded under the old rule. Update every jump correction screen so the internal difficulty number is gone and each question shows its new points.

### 2. Why this change is needed

`JumpGradingService::gradeAttempt()` adds `(int) $item['difficulty']` for a correct answer and adds nothing for an incorrect one. Difficulty is an internal mastery scale (`300 * level + 100 * 2^(tier - 1)`, then drifted). A typical question is roughly 400–2300, so a five-question jump scores in the thousands. That number is what students see as “Score” and what class tables, the pocket, and rankings sum.

Difficulty and mastery stay internal (feature 12). They must keep updating exactly as they do now. Only the attempt score changes.

### 3. Product decisions

Locked.

| # | Decision | Locked answer |
| --- | --- | --- |
| 1 | Unanswered question (`answer` null or `''`) | **0 points.** Status stays `incorrect`, matching today, so `AnalyseJump` and mastery stay valid. A wrong letter takes the penalty. |
| 2 | Jump total below 0 | **Floor the official total at 0.** Each question still stores its signed points, so penalties reduce the total, and the total stops at 0. The floor is per attempt, not per question and not on the course aggregate (each attempt is already ≥ 0, so a pocket sum of attempts cannot go below 0 either). |
| 3 | `Correcte : X` on a wrong answer | **Keep it.** |
| 4 | What the admin button rewrites | **Already graded attempts on `expired` jumps only.** Skip `active` and `expiring`. Skip expired attempts that still have a `pending` question (those still belong to `ExpireJumps`, which also applies mastery). |

Question label: the left side shows the 1-based position in the jump (`Question 3`), the student’s answer, and `Correcte : X` on a wrong answer. Remove `Difficulté : …` and the database id line (`Question #{{ item.id }}`).

### 4. Out of scope

- Kangourou session scoring, session results, and `GradingService`.
- Mastery updates and question difficulty updates (`ExpireJumps::updateMasteryAndDifficulty()`, `UpdateMasteryAndDifficulty`).
- `AnalyseJump` and suggested questions.
- Who may see a correction, and when (`scoresReady` / jump `expired` on the student results page).
- The in-progress jump screen (`jump-attempt-view.vue` shows neither score nor difficulty).
- Changing how a letter is compared to `correct_answer`.
- Auto-running the backfill from a migration. An admin runs it once from the modal.

### 5. Current code the agent must re-check

Verified when this spec was written. Re-read these before editing.

**Grading.** `app/Services/JumpGradingService.php` sets each item `status` and sets `score` to the sum of difficulties of correct items. Callers:

- `ExpireJumps::gradeAllAttemptsForJump()` — grades every attempt when the jump expires, then updates mastery.
- `ExpireJumps` safety net — finished attempts that still contain a `pending` item, then mastery.
- `JumpAttemptController::submit()` — regrades only when the jump is already expired and the saved answers changed, then dispatches `AnalyseJump`. It does not update mastery.

Unanswered items (`answer === null`) are stored as `incorrect` and currently add 0. Mastery skips them (`given === null`). `JumpAttempt::answerBreakdown()` treats `null` and `''` as unanswered. The grader does not treat `''` as empty; this feature aligns it with the breakdown.

**Difficulty used for the score** is the snapshot on `question_list[].difficulty`, copied when the question was selected (`JumpQuestionSelector`). Do not read `questions.difficulty` while scoring.

**Score column.** `jump_user.score` is a signed integer, default 0. Negative totals fit the column. No migration.

**Correction UI, four copies** (search for `Difficulté :` inside jump question rows):

| Screen | File | Data |
| --- | --- | --- |
| Home pocket, and the division chart point | `resources/js/components/JumpAttemptDetailModal.vue` | `GET /api/jump-attempts/{id}` |
| Class / course page modal | `resources/js/components/views/course-details-view.vue` (inline modal, ~line 447). The imported `JumpAttemptDetailModal` is unused. | same `GET` |
| Division live observation | `resources/js/components/views/division-details-view.vue` (~line 997), status via `displayStatus` | same `GET`, merged with course details that already include `correct_answer` |
| Student results (not a modal) | `resources/js/components/views/jump-results-view.vue` | attempt in `jumpAttemptStore`, breakdown rendered only when `jump.status === 'expired'` |

`GET /api/jump-attempts/{id}` hides `correct_answer` until the jump is expired, for the student and the teacher. The division observation still previews right/wrong because `CourseController::details` (teacher only) attaches `correct_answer` and the client derives `displayStatus`.

**Other score readers** (they follow `jump_user.score`; no formula of their own): pocket (`PocketAssembler`), class and division tables, `RecentAttemptsPanel`, `JumpAttemptUpdated` (broadcasts `score` when the attempt is `finished`). `course-details-view.vue` and `division-details-view.vue` sort missing attempts with `score ?? -1`.

**Admin.** `resources/js/components/views/admin-view.vue` has tabs Sujets / Utilisateurs / Bugs. Routes live under `routes/web.php` in the `can:admin` group, prefix `/api/admin`. `QUEUE_CONNECTION=database`. Queue worker runs with `composer run dev`.

**Tests that encode the old score** (update them, do not delete them):

- `tests/Feature/ExpireJumpsTest.php` — expired attempt score equals `question.difficulty`.
- `tests/Feature/JumpAttemptTest.php` — late submit expects `score` 1000 when difficulty is 1000, and expects mastery to move exactly once.

### 6. Scoring rules

Add the rule to `JumpGradingService` as a pure function used by grading, by the admin regrade, and by any read path that has to attach points. Do not copy the arithmetic into Vue.

```text
difficultyForPoints = max(0, (int) item.difficulty)   // missing or negative snapshot counts as 0

right:  (int) round(difficultyForPoints / 100)         // PHP_ROUND_HALF_UP, the PHP default
wrong:  -max((int) round(difficultyForPoints / 1000), 1)
blank:  0                                              // decision 1
score:  max(0, sum of the item points)                // decision 2, per attempt
```

Use float division. `intdiv` is the wrong operation.

`round` examples the tests must lock:

| Difficulty | Right | Wrong letter | Blank |
| ---: | ---: | ---: | ---: |
| 0 or missing | 0 | -1 | 0 |
| 400 | +4 | -1 | 0 |
| 450 | +5 | -1 | 0 |
| 1000 | +10 | -1 | 0 |
| 1499 | +15 | -1 | 0 |
| 1500 | +15 | -2 | 0 |
| 2300 | +23 | -2 | 0 |

**Status** (unchanged):

- `answer` is `null` or `''`, or `correct_answer` is missing and no comparable letter was given: `status = incorrect`, points 0 when the answer is empty.
- given letter equals the question’s `correct_answer`: `status = correct`, right-answer points.
- any other non-empty letter: `status = incorrect`, wrong-answer points.

Compare letters the way the grader does today (`===`). A non-empty answer with a missing correct answer stays `incorrect` and takes the penalty.

**Persisted shape.** On each graded item, set `points` to that signed integer. Set `jump_user.score` to `max(0, sum of points)`. An empty `question_list` scores 0. `gradeAttempt()` overwrites `status`, `points`, and `score` from the stored answers and the snapshotted difficulty. Ignore any `points` a client sends.

The floor applies only when the score is saved and when a correction payload reports the official total. It does not clip `points`. Three wrong answers at difficulty 1000 store `points` of -1, -1, -1 and a `score` of 0. One right answer (+10) and two wrong answers (-1, -1) store a `score` of 8. The header can therefore read 0 while the rows show penalties. That is the rule. Do not sum the rows into the header on the client, and do not clip a row to 0.

**When grading runs.** Do not add a new call site. In-progress jumps keep `score` at 0 and items at `pending` with no `points`. The existing expiry job and the existing late-submit regrade pick up the new formula because they already call `gradeAttempt()`.

### 7. Future jumps

No new workflow. After this change:

- A jump graded by `ExpireJumps` stores the new `points` and the new `score`, then runs mastery exactly once, as today.
- A late answer save on an already expired jump regrades and dispatches `AnalyseJump`, and does not touch mastery, as today.
- An `active` or `expiring` attempt is not scored early.

`JumpAttemptUpdated` keeps broadcasting the stored `score` for a finished attempt. Before expiry that value is still 0. Do not start broadcasting the preview total.

### 8. Admin regrade of past jumps

One-shot operator action. Idempotent: running it twice leaves the same `points` and `score`.

**Who.** `can:admin` only. Anyone else gets 403. Follow the existing admin routes. The POST body is empty; do not add a Form Request for an empty body. Match `AdminController` style.

**Which rows.** Decision 4:

- jump `status === 'expired'`
- the attempt has no `question_list` item with `status === 'pending'`

Count those jumps (distinct `jump_id`) and those attempts. Do not count active jumps, expiring jumps, or expired-but-still-pending attempts. The modal count is “what Confirm will rewrite”, not “every jump row in the database”.

**What it writes.** Load the attempt, call the same `gradeAttempt()` (correct answers looked up in bulk per chunk, as `ExpireJumps` already does). That refreshes `status`, `points`, and `score` from the stored answers and the snapshotted difficulty.

**What it must not do.**

- Do not call `updateMasteryAndDifficulty`.
- Do not change `questions.difficulty` or `users.mastery`.
- Do not dispatch `AnalyseJump`.
- Do not change attempt `status`, `termination`, `timer`, or answers. `gradeAttempt()` today rewrites `question_list` statuses; for this backfill that rewrite is the same correct/incorrect result the row already has, plus `points`.
- Do not grade a row that still has `pending` items.

**Execution.** `QUEUE_CONNECTION=database`, so a single HTTP request that rewrites every attempt can die at the proxy. Shape:

- `GET /api/admin/jump-scores` → `{ jumps, attempts }` for the set above. Cheap count, no writes.
- `POST /api/admin/jump-scores/regrade` → dispatch one queued job, return `202` with `{ state: "running" }`.
- The job chunks attempts (a few hundred per chunk), calls `gradeAttempt()`, and records `{ state, jumps, attempts, error }` in cache.
- A cache lock rejects a second run with `409` while `state === "running"`.
- `GET /api/admin/jump-scores/regrade` → the cache status, so the modal can poll. On success: `{ state: "done", jumps, attempts }`. On failure: `{ state: "failed" }` and leave already-written rows as they are (the next run is safe).
- After a successful run, `PocketChanged::broadcastToDivisions()` for the divisions of the jumps that were rewritten, once, not once per attempt.
- Put the rewrite loop in one class the job calls, so a failed UI run can be repeated from the job. Do not add a separate artisan command unless the job class is awkward to dispatch from the container; the modal is the product surface.

**Modal** on `admin-view.vue`. A button in the page header, visible on every tab: **Recalculer les scores**. It opens a modal that immediately calls the count endpoint.

Copy:

- Title: `Recalculer les scores des sauts`
- Body when `attempts > 0`: `{jumps} sauts expirés, {attempts} copies déjà notées. Les scores seront recalculés. La maîtrise des élèves et la difficulté des questions ne changent pas.`
- Body when `attempts === 0`: `Aucun saut à recalculer.` and disable confirm.
- Confirm: `Recalculer`. Cancel: `Annuler`.
- While the job runs: `Recalcul en cours…`, both buttons disabled.
- Success: `{attempts} copies mises à jour.`
- Failure: `Le recalcul a échoué. Vous pouvez le relancer.`
- A 409: `Un recalcul est déjà en cours.`

Poll about every 2 seconds until `done` or `failed`. Closing the modal does not cancel the job.

### 9. Correction UI

Extract one presentational component, `resources/js/components/JumpQuestionBreakdown.vue`. Use it in all four screens in the table in section 5. Delete the four copied footers. On the class page, remove the unused `JumpAttemptDetailModal` import if the local modal remains as the shell; the row markup itself must not stay duplicated.

The component renders the question image (same constraints as today: not draggable, context menu suppressed) and one footer row.

**Footer**

- Left: `Question {n}` where `n` is the 1-based index. Under it, `Réponse : {letter}` or `Réponse : —` when the student gave nothing. Under that, when decision 3 is locked as keep: `Correcte : {letter}` only if this row is wrong and `correct_answer` is present. Same `text-xs text-success font-medium` treatment as today.
- Right: the points in `text-2xl font-bold`.
  - right answer: `+N` in `text-success` (`+0` when rounding yields 0)
  - wrong letter: `-N` in `text-error`
  - blank: `0` in `text-text-muted`
  - not yet graded (`pending`, no points to show): `—` in `text-text-muted`
- Remove the difficulty line. Remove `Question #{id}`.

Keep the existing card chrome: green / red / neutral border and tint from the displayed status, the round index badge, and the total line above the list (`Score : …` / `Score total`). The total is the official attempt `score` from the payload (already floored at 0). Do not sum the rows on the client. When every answer is wrong, the rows show negative points and the header shows 0.

**Points on the payload**

- `gradeAttempt()` persists `points`. After expiry, `JumpAttemptController::show` and the payload that fills `jumpAttemptStore` for `jump-results-view.vue` include those stored points. The agent must trace the results-page load (it is not only `show`) and decorate that payload too.
- Do not add `points` to a student payload while the jump is not `expired`. That would reveal right and wrong early. `show` already strips `correct_answer` in that case; treat `points` the same way.
- Teacher live observation is the exception that already reveals outcomes. `CourseController::details` is teacher-only and already attaches `correct_answer`. Also attach a computed `points` there (not saved). `mergeCourseAttemptQuestionDetails` in `division-details-view.vue` must keep `points` the same way it keeps `correct_answer`. The observation row passes `displayStatus` into the shared component and shows the number whenever that status is `correct` or `incorrect`.
- The class-page modal and `JumpAttemptDetailModal` key off stored `status`. While that status is `pending`, they show `—` even if a teacher preview value exists on some other payload. They must not start revealing points before the jump is graded.
- A legacy graded row has no `points` key until the admin job runs. Correction screens still render the new formula for those rows by computing on read inside the PHP service (same function, do not persist on GET). The correction payload’s `score` is `max(0, sum of those points)`, the same value the job will store. Class tables, pocket, and rankings keep reading the database column until the job writes it. That split is temporary and is why the release step in section 13 is mandatory.

`text-success`, `text-error`, `text-text-muted`, `border-success`, `border-error` already exist. Do not add colors.

### 10. Rankings and totals

Official attempt scores are always ≥ 0, so the existing `score ?? -1` sorts in `course-details-view.vue` and `division-details-view.vue` stay valid: a missing attempt still sorts below a real 0. Do not change those sentinels.

Pocket and class totals already sum the stored integer. They pick up the new totals when the database is updated. No formula in those Vue files. A jump whose raw points sum to -5 contributes 0 to the course total.

### 11. Tests

Pest feature tests. Update the existing score assertions instead of leaving them red. Add:

- The table in section 6, including half-up on 450 and 1500, blank = 0 with status `incorrect`, wrong letter = penalty.
- Floor: three wrong answers at difficulty 1000 store points -1, -1, -1 and `score` 0. One right (+10) and two wrong (-1, -1) store `score` 8. A positive sum is stored as itself.
- `''` is blank, same as `null`.
- A snapshotted difficulty is what gets scored when `questions.difficulty` has since changed.
- Late submit still moves mastery once and stores the new score (the 1000 expectation becomes `+10` for difficulty 1000).
- Admin count and regrade: a non-admin receives 403. An expired graded attempt changes score. An `active` attempt is untouched. An expired attempt that still has `pending` is untouched. Mastery and question difficulty are unchanged. `AnalyseJump` is not dispatched. A second run yields the same score.
- Overlapping regrade returns 409 while the lock is held (drive the job synchronously in that test, or lock the cache key directly).

Run `php artisan test --compact` on the jump grading, expire-jumps, jump-attempt, and new admin regrade tests.

### 12. Acceptance criteria

- A newly expired jump stores signed `points` per question and a `score` of `max(0, sum of points)`, using the table in section 6.
- A right answer on difficulty 1000 is worth 10, not 1000. A wrong letter on that question is worth -1. A blank is worth 0 and stays `incorrect`. Three wrong answers score 0 overall, and the rows still show -1.
- Mastery and question difficulty move only through the existing expiry path, once.
- Correction rows on the home pocket, the class page, the division observation, and the student results page show no difficulty and no database question id. They show the question position, the student’s answer, the correct letter on a wrong answer, and a large `+N` / `-N` / `0` / `—`. The header shows the floored official score, not a client-side sum of the rows.
- The student results breakdown stays hidden until the jump is `expired`. Points are absent from the student payload before that.
- The division observation preview during a live jump shows the new point values for questions it already marks right or wrong.
- An admin can open the modal, read how many expired jumps and graded attempts will change, and run the rewrite. A second click while it runs does not start a second rewrite. A non-admin cannot.
- After the job, pocket, class tables, and rankings show the new totals. Re-running the job does not change them again.
- Session grading is unchanged.

### 13. Release step

Deploy the code, then sign in as an admin and run **Recalculer les scores** once before anyone is asked to look at old jumps. Until that finishes, class tables and the pocket still show the old totals, while correction screens show the new line points and a matching header. New jumps graded after deploy are already on the new rule and do not need the button.

### 14. Verification

- Pest, as in section 11.
- In the browser, as an admin: open `/admin`, run the modal against a database that has at least one expired jump, and confirm the success count.
- As a teacher: open a graded attempt from the class page and from the home pocket. Confirm the row layout. When the raw points sum to more than 0, the header equals that sum. When every answer is wrong, the rows show penalties and the header shows 0.
- As a teacher: open division observation on a jump that is still `active` and on one that is `expired`.
- As a student: finish a jump that has not expired and confirm the results page still waits, with no per-question points. After expiry, confirm the new points and the total.
- Confirm a Kangourou session result still shows its old score.
