# Fix: Jump Expiry Force-Stop, Late Answer Save, and Rejoin Hiding

## 1. Objective

When a jump expires, every learner who is still taking it must be force-stopped, their latest local answers must be recorded, and they must land on the jump results page. Rejoin must not remain available after the jump has closed.

This is the jump counterpart of feature #24. Copy that session expiry contract, then adapt it to jump-specific facts (no guests, `question_list`, `expiring` status, grading only at expiry). Do not invent a third finalization path, and do not change Kangourou session expiry.

## 2. Product Decisions Locked for This Iteration

- This feature applies only to jumps, not to Kangourou sessions.
- Feature #24 is the behaviour template. Feature #22 is the jump write-path template (`question_list` submit, batched sync). Do not copy session models or jump-expiry’s current “redirect without submit” handler.
- Jumps have three live statuses: `active` → `expiring` → `expired`. Sessions do not. Force-stop happens as soon as the jump is **closed**, not when scores become ready.
	- **Closed:** `expiration` is in the past, or `status` is `expiring` or `expired`. Learners must stop answering.
	- **Scores ready:** `status === 'expired'` after `ExpireJumps` has graded. Results may then show score and correction.
- The force-stop signal is the existing `JumpExpired` event on `jump.{id}`, plus a local `expiration` deadline on the learner client so a missed Echo event still stops the attempt.
- Force-stop means: stop the attempt UI, persist the latest local `question_list`, and redirect to `JumpResults` (`jump-results-view.vue`). It does not mean deleting attempts or changing unrelated jumps.
- Unsaved local answers are sent through the existing submit path (`POST /api/jump-attempts/{attempt}/submit` with the full `question_list`). Periodic sync is only a helper, not the terminate contract.
- The backend allows a 3-minute post-expiry write window, measured from `expiration`, so a late client flush or submit still records answers if the jump closed less than 3 minutes ago. While `status === 'expiring'`, late writes are always allowed, even if grading is slow and the 3 minutes have already elapsed. That is the jump-specific analogue of feature #24’s grace window: the job must not eat the window.
- After that window (and once the jump is no longer `expiring`), the backend must not persist new answers, but it must still return a normal success response that lets the frontend terminate. Do not send a client-facing error for “jump already expired / already submitted” on the terminate path.
- Clicking **Terminer le saut** on `jump-attempt-view.vue` after the jump has closed uses the same contract: no error, end the attempt, save answers only if still inside the writable window.
- On `jump-results-view.vue`, hide **Demander à reprendre** whenever the jump is closed. Do not wait for `status === 'expired'` or for scores to appear.
- Keep the expiry job’s current disconnected-student safety net: in-progress attempts that never reach the server are still auto-finalized as `timeout` and graded when the job runs.
- Do not allow new attempts, and do not allow rejoin, after the jump is closed. The writable window is only for recording answers of attempts that already existed.
- Jumps have no guests. Do not add guest support. A local `expiration` deadline is still required as an Echo fallback for authenticated learners.
- Keep `JumpExpired` on the current public `jump.{id}` channel. Do not migrate it to a private channel in this feature. `JumpAttemptUpdated` stays on the private jump channel from feature #22.

## 3. Why This Change Is Needed

Jump expiry already exists, but the learner-facing end of it drops unsaved answers on purpose.

The current contract is:

- `ExpireJumps` moves `active` jumps with `expiration <= now()` to `expiring`, grades every attempt (in-progress ones become `timeout`), sets `status = expired`, broadcasts `JumpExpired` with `{ jump_id }`, then dispatches `AnalyseJump`.
- Teacher **Expire now** PATCHes `expiration` to now and `status` to `expiring`. It does **not** broadcast `JumpExpired`. Learners keep answering until the job finishes grading (up to about one minute, longer if grading is heavy).
- `jump-attempt-view.vue` listens to `JumpExpired` and **redirects to results without submitting**. Pending local answers and even the last unsynced batch are discarded. Feature #24 explicitly called this out as the pattern not to copy.
- `POST /api/jump-attempts/{id}/submit` 403s with `Already submitted.` as soon as the job has finished the row. Even if the client did submit after `JumpExpired`, the job-then-client race would drop answers.
- Sync only rejects when the attempt is not `inProgress`. It does not look at jump status. Once the job finishes the row, the next flush 403s and the current jump view swallows that error.
- `jump-results-view.vue` hides rejoin only because the whole awaiting block is `v-if="!isJumpExpired"` and `isJumpExpired` is `status === 'expired'`. During `expiring`, rejoin remains visible and `JumpRejoinDemandController` allows it (`isExpired()` is status-only).
- Submit does not grade. Scores appear only after the job. That must stay. This feature only has to persist the final `question_list` so the job (or a late re-grade) scores the real answers.

This feature changes the contract to:

- the jump closing always force-stops connected learners, including when Echo is missing;
- the latest local `question_list` is recorded when it arrives inside the writable window, even if the job already timeout-finished the row;
- submit after close is success-oriented, never an error the UI gets stuck on;
- closed jump results never offer rejoin;
- score/correction still appear only when `status === 'expired'`.

## 4. How This Differs From Sessions (Feature #24) and From Current Jump Expiry

The implementing agent must not copy feature #24 or the current jump `JumpExpired` handler blindly.

| Topic | Sessions (#24) | Jumps (this feature) |
| --- | --- | --- |
| Learners | Guests + authenticated | Authenticated class members only |
| Close clock | `expires_at` | `expiration` |
| Status path | `active` → `expired` | `active` → `expiring` → `expired` |
| Force-stop moment | `expires_at` past / `SessionExpired` | jump **closed** (`expiration` past or `expiring`/`expired`), not “scores ready” |
| Stop event | `SessionExpired` on private `session.{id}` | `JumpExpired` on public `jump.{id}` (keep current channel) |
| Final payload | `answers` array of 26 `{ answer, status }` | `question_list` of variable length `{ id, answer, status, difficulty, ... }` |
| Submit while running | May delay grading | Never grades; job grades at expiry |
| Submit after close | Grades immediately | Persist answers; re-grade only if the job already graded; otherwise leave `pending` for the job |
| Mastery | `UpdateMasteryAndDifficulty` job | Inline in `ExpireJumps::updateMasteryAndDifficulty()`. Do not apply it twice |
| Analysis | Session division pivot analysis | `AnalyseJump` job. Re-dispatch after a late save that changed answers on an already-expired jump |
| Rejoin hide | When session is expired | When jump is **closed**, including `expiring` |
| Results reveal | Correction when session expired | Scores only when `status === 'expired'` |
| Current attempt-page handler | Submits then redirects | Redirects **without** submit — this is the bug to fix |
| Expire-now | Broadcasts `SessionExpired` | Currently silent; must broadcast `JumpExpired` when the jump becomes closed |
| Button | **Terminer la session** | **Terminer le saut** |

Feature `XX - Unify attempt.md` is out of scope. Do not edit session controllers, session events, or `attempt-view.vue`.

## 5. Current-State Notes for the Implementing Agent

Verified runtime facts at the time this spec was written. Re-check them before editing.

- `ExpireJumps` is scheduled every minute from `routes/console.php`.
- The job:
	- `active` + `expiration <= now()` → `expiring`;
	- every `expiring` jump: finish in-progress attempts as `timeout`, grade all attempts, set `expired`, broadcast `JumpExpired`, dispatch `AnalyseJump`;
	- also grades leftover finished attempts on already-expired jumps whose items are still `pending`.
- `JumpExpired::broadcastWith()` is `{ jump_id }`. Keep that payload. Do not add `question_list`.
- `JumpExpired` uses `new Channel('jump.'.$id)` (public). Teacher and learner UIs listen with `Echo.channel(...)`. Keep that.
- `JumpAttemptUpdated` uses `PrivateChannel('jump.'.$id)`. Do not mix the two contracts.
- `Jump::isActive()` is `status === 'active'` only. It does **not** look at `expiration`. A jump can be `active` with `expiration` already past until the job ticks.
- `Jump::isExpired()` is `status === 'expired'` only. `expiring` is not expired. Rejoin and results currently use this.
- `JumpAttemptController::store()` rejects `!isActive()`, so `expiring` already cannot start a new attempt.
- `sync()` and `submit()` do **not** check jump status. Sync only requires `inProgress`. Submit 403s whenever the attempt is not `inProgress`.
- Feature #22 already made submit accept `question_list`. `jump-attempt-view.vue` already sends it on submit, timeout, blur, abandon, and beacon. The `JumpExpired` path is the one that does not.
- `jump-attempt-view.vue` on `JumpExpired` clears timers, stops the sync loop, **resets the dirty map**, and `router.replace`s to `JumpResults`. That reset is how unsynced answers are thrown away.
- On mount, if `loadedAttempt.jump?.status !== 'active'`, the attempt page already redirects to results without submitting. That is correct for a learner opening the page after close. It is not sufficient for a learner already on the page.
- Teacher expire-now lives in `course-details-view.vue` and `division-details-view.vue`: `updateJump({ expiration: now, status: 'expiring' })`.
- Jump reopen (`JumpReopened`) is out of scope.
- Mastery in `ExpireJumps` is not idempotent. Grading the same attempt twice would apply mastery twice. Late re-grades must not run mastery again when the attempt was already graded.

### 5.1 Primary Implementation Surfaces

These are the first files the AI agent should inspect and are the most likely edit surfaces:

- `app/Models/Jump.php`
	- add the 3-minute grace constant and helpers used by submit/sync (`allowsLateAnswerSave()`, and a clear “jump is closed” helper).
- `app/Http/Controllers/JumpAttemptController.php`
	- `submit()` and `sync()` must become close-safe.
	- `updateAnswer()` should use the same helper if the endpoint is kept.
- `app/Jobs/ExpireJumps.php`
	- keep auto-finalize, grading, `JumpExpired`, and `AnalyseJump`.
	- grading/mastery used for a late re-grade must be reusable without double-applying mastery.
- `app/Events/JumpExpired.php`
	- keep the current payload and public channel.
- `app/Http/Controllers/JumpController.php`
	- teacher `update()` that closes a jump (status `expiring`, or `expiration` moved into the past) must broadcast `JumpExpired` immediately.
- `app/Http/Controllers/JumpRejoinDemandController.php`
	- reject rejoin when the jump is closed, not only when `status === 'expired'`.
- `resources/js/components/views/jump-attempt-view.vue`
	- `JumpExpired` / local deadline / **Terminer le saut** / redirect after submit.
- `resources/js/stores/jumpAttemptStore.js`
	- submit and sync must treat post-close success as success, including already-finished rows.
- `resources/js/components/views/jump-results-view.vue`
	- split “jump closed” (hide rejoin) from “scores ready” (show correction).
- `tests/Feature/JumpAttemptTest.php`
	- primary backend tests for submit/sync grace-window behaviour.
- `tests/Feature/ExpireJumpsTest.php`
	- keep existing job tests passing.
- `tests/Feature/JumpRejoinDemandTest.php`
	- closed jump cannot create a rejoin demand.

Secondary impact review surfaces:

- `resources/js/components/views/course-details-view.vue`
- `resources/js/components/views/division-details-view.vue`
	- already listen to `JumpExpired` and refetch courses. Keep that. Expire-now should now also produce the event, so observation updates sooner.
- `app/Jobs/AnalyseJump.php`
	- re-dispatch after a late save that changed answers on an already-expired jump.
- `tests/Feature/CourseTest.php` if jump update tests exist.

## 6. Scope

### In Scope

- force-stopping every in-progress jump attempt when the parent jump closes;
- redirecting those learners to `jump-results-view.vue`;
- persisting the learner’s latest local `question_list` through submit, including after the job already timeout-finished the row, if the writable window is still open;
- making post-close **Terminer le saut** and `JumpExpired` auto-submit return a normal success payload instead of an error;
- making post-close sync fail closed for writes outside the window, but fail open for frontend termination;
- hiding **Demander à reprendre** on jump results when the jump is closed, including `expiring`;
- broadcasting `JumpExpired` immediately when a teacher closes a jump (expire-now / `expiration` in the past / status `expiring`);
- covering missed Echo via a local `expiration` deadline;
- backend tests for the writable window, already-finished post-close submit, and unchanged active-jump rules.

### Out of Scope

- Kangourou session expiry, session rejoin, or `SessionExpired` behaviour;
- allowing new jump attempts after the jump is closed;
- allowing rejoin during `expiring` or after `expired`;
- changing jump question selection, growth, blur, or reopen;
- changing the `JumpAttemptUpdated` summary payload from feature #22;
- replacing `ExpireJumps` with client-only grading;
- unifying session and jump attempts;
- moving `JumpExpired` onto a private channel;
- making jump mastery a queued job rewrite.

## 7. User-Visible Behavior

### 7.1 Learner Still On the Attempt Page

When the jump closes, whether by scheduled `expiration`, by the expiry job, or by the teacher clicking expire-now:

- the attempt UI stops immediately;
- pending local answers are flushed best-effort, then submitted with the full local `question_list`;
- the learner is redirected to `JumpResults` for that `jumpId` and `attemptId`;
- the learner must not remain able to change answers;
- the learner must not see an error toast that blocks termination.

This includes:

- Echo `JumpExpired`;
- local `expiration` countdown (Echo fallback);
- clicking **Terminer le saut** after the jump has already closed;
- personal timer timeout that happens to coincide with jump close.

If submit reports success because the attempt was already finished, still redirect.

Do not copy the current handler that only `router.replace`s and `resetAnswerSyncState()`s.

### 7.2 Learner On the Jump Results Page

Split two flags. Do not keep using a single `isJumpExpired === (status === 'expired')` for both.

- **Jump closed** (`expiration` past, or status `expiring` / `expired`):
	- **Demander à reprendre** is not rendered, regardless of termination reason;
	- pending rejoin copy is also hidden.
- **Scores ready** (`status === 'expired'`):
	- show score and per-question correction, as today;
	- if the page was showing the awaiting state, refetch the attempt when `JumpExpired` arrives so correction can appear without a manual refresh.

While the jump is still `active` and `expiration` is in the future:

- keep today’s rejoin UI, including hiding it for `termination === 'timeout'`.

### 7.3 Disconnected Learners

If the client never receives the event and never submits:

- the expiry job still timeout-finalizes and grades from server-side `question_list`, as today;
- those learners see results the next time they open the attempt or results route.

### 7.4 Teachers

Teacher live tables already receive `JumpAttemptUpdated` summaries and already refetch on `JumpExpired`. After expire-now they should receive `JumpExpired` immediately so observation does not stay stuck on `active` until the scheduler runs. Do not rebuild the observation tables in this feature.

## 8. Functional Requirements

### 8.1 Closed Jump and Grace Window

Add one backend constant and use it everywhere:

```php
public const POST_EXPIRY_ANSWER_GRACE_SECONDS = 180;
```

Put it on `Jump` with named helpers. Suggested names:

- `isClosed(): bool` — `status` is `expiring` or `expired`, **or** `expiration` is past.
- `allowsLateAnswerSave(): bool` — the jump is closed, **and** either `status === 'expiring'` **or** `now < expiration + 180 seconds`.

Use `expiration`, not only `status`, because the job may not have flipped `active` → `expiring` yet when the first late request arrives.

| Request | Jump still open (`active` and `expiration` future) | Closed, writable (`expiring` or inside 3 minutes of `expiration`) | Closed, window closed (`expired` and `expiration + 180s` past) |
| --- | --- | --- | --- |
| New attempt | current rules | rejected, unchanged | rejected, unchanged |
| Rejoin demand | current rules | rejected | rejected |
| Active sync | current feature #22 rules (204) | persist if attempt is writable per §8.4; return terminate JSON | do not persist; still return terminate JSON |
| Submit with `question_list` | current rules; finished → 403 | persist list, finish, return success | do not persist new answers; still finish if needed; return success |
| Submit without `question_list`, already finished | 403 | return current finished attempt as success | return current finished attempt as success |

### 8.2 `JumpExpired` Force-Stop

Keep broadcasting `JumpExpired` from `ExpireJumps` after the jump is marked `expired` (scores ready). Results pages need that refetch.

Also broadcast `JumpExpired` when an authorized teacher update **closes** a jump:

- `status` set to `expiring`, or
- `expiration` set to a past timestamp on an `active` jump.

If the job later broadcasts the same event again, the learner client must ignore the duplicate because it is no longer `inProgress` / already submitting. The results client may refetch twice; that is acceptable.

Do not broadcast `question_list` on `JumpExpired`. The attempt client already has it locally.

On `jump-attempt-view.vue`:

- keep the Echo `JumpExpired` listener;
- also start a jump-level deadline from `jump.expiration` when the attempt is in progress;
- both paths call the same terminate helper;
- that helper must be idempotent (`isSubmitting` / `!isInProgress` guards).

The terminate helper: stop timers and sync loop → best-effort flush → submit with local `question_list` → `router.replace` JumpResults.

Do **not** call `resetAnswerSyncState()` before submit. That is the current bug.

### 8.3 Submit After Close

`POST /api/jump-attempts/{jumpAttempt}/submit` is the terminate contract.

Keep:

- owner check (`SubmitJumpAttemptRequest` already authorizes by `user_id`);
- open jump + already finished → 403 (`Already submitted.`);
- feature #22 authoritative `question_list` persist;
- `JumpAttemptUpdated` summary broadcast on a real state change;
- **do not grade** while the jump is still open (`active` and `expiration` future). Score stays `0` and items stay `pending` until the job, as today.

Change:

- if the jump is closed (`isClosed()`), never 403 for “already submitted”;
- if `question_list` is present and `allowsLateAnswerSave()` is true, persist it even when the row is already `finished`;
- if the jump is still `expiring` (job has not graded yet), persist and finish, leave statuses `pending`, do not run jump grading or mastery (the job will);
- if the jump is already `expired` (job already graded) and this late save changed answers, re-grade that attempt using the same scoring rules as `ExpireJumps::gradeAttempt()`, then **do not** run mastery again;
- if `question_list` is present but the writable window has closed, ignore the new list and return the current finished attempt in the normal `{ attempt }` shape;
- if the attempt is still `inProgress` after close, finish it with the provided termination even outside the window, using already-stored `question_list` when late answers are rejected.

Termination rules for the race with the job (same as #24):

- if the job already set `termination = timeout` and the client then submits inside the window with a new `question_list`, keep `timeout` when the client sent `timeout`. If the client explicitly sent `submitted` because they clicked **Terminer le saut**, keep `submitted`. Do not revert a pre-close `submitted` / `blurred` / `abandoned` to `timeout`.
- only apply `question_list` when the request actually contains that key.

After a writable-window save that changed `question_list` on a jump that is already `expired`, re-dispatch `AnalyseJump` for that jump. Do not duplicate scoring rules. If the jump is still `expiring`, do not dispatch `AnalyseJump` from submit; the job still owns the first analysis.

Extract grading used for late re-grade so submit does not copy a third scoring implementation. Prefer a small shared method or service used by `ExpireJumps` and `JumpAttemptController`. Mastery stays inside the job’s first grade path only.

### 8.4 Sync After Close

`PATCH /api/jump-attempts/{jumpAttempt}/sync` must stop being a hard 403 the moment the attempt is finished by the job, and must tell the client to terminate once the jump is closed.

While the jump is open:

- keep 204 on success;
- keep finished-attempt 403;
- keep validation and unauthorized 403.

Once the jump is closed:

- if `allowsLateAnswerSave()` and the attempt is `inProgress`, or is `finished` with `termination = timeout`, apply the change list exactly as today’s sync does;
- return JSON, not silent 204, for example `{ "jump_closed": true, "saved": true }`;
- broadcast `JumpAttemptUpdated` only when something actually changed, same as feature #22.

If the window is closed, or the attempt is finished with a non-timeout termination:

- do not persist changes;
- return `{ "jump_closed": true, "saved": false }` with HTTP 200;
- do not 403.

Frontend:

- `flushAnswerChanges` must not throw the learner out of terminate/submit when it receives `jump_closed`;
- if `jump_closed` arrives while the attempt is still in progress and the user has not already submitted, stop the sync loop and run the same terminate helper;
- do not keep retrying a 3-second loop after close.

Use the key `jump_closed`, not `session_expired`.

### 8.5 Results Rejoin Button

On `jump-results-view.vue`:

- compute **closed** from `status` in `expiring`/`expired` **or** `expiration` in the past;
- hide the entire rejoin block when closed;
- keep the current `termination === 'timeout'` hide for an still-open jump;
- keep **scores ready** as `status === 'expired'`;
- if the page is open when close/expiry happens, refetch. Authenticated users already listen to `JumpExpired`; also honor a local `expiration` timer so a missed event still hides rejoin. A second `JumpExpired` after grading should refetch again so scores appear.

Do not show score/review UI during `expiring`. Do not change session results.

### 8.6 Rejoin API

`JumpRejoinDemandController::store()` must reject when `jump->isClosed()`, not only `isExpired()`. Message can stay `Ce saut est terminé.`

## 9. Data Contract

No new routes.

### 9.1 Submit

Keep `POST /api/jump-attempts/{jumpAttempt}/submit`.

Success body stays:

```json
{
	"attempt": { }
}
```

After close, `attempt` must still be present even when the row was already finished. The frontend redirect depends on a non-throwing response, not on a new field.

`attempt` in the JSON body is the full attempt (existing submit/show shape, including images). That is different from the `JumpAttemptUpdated` broadcast summary. Do not mix them. Do not reveal `correct_answer` while the jump is not yet `expired`.

### 9.2 Sync After Close

Open-jump success remains `204 No Content`.

Post-close response:

```json
{
	"jump_closed": true,
	"saved": true
}
```

`saved` is `true` only when the writable window accepted the writes.

### 9.3 Event

Keep:

```json
{ "jump_id": 12 }
```

on public `jump.{id}`, event name `JumpExpired`.

The event now means “the jump closed or finished closing”. Clients must be idempotent. Results use it as a refetch signal; they still decide UI from `status` and `expiration` on the attempt payload.

## 10. Implementation Guardrails

- Reuse `submit()` as the authoritative persist+terminate path from feature #22. Do not add `POST /jump-attempts/{id}/finalize-expired`.
- Do not persist answers after the writable window, even if the client still has dirty state.
- Do not 403 the terminate path after the jump is closed. 403 is how the session UI used to get stuck, and how jump submit already fails today.
- Do not run jump mastery a second time for an attempt the job already graded.
- Do not grade on submit while the jump is still open.
- Do not skip the job’s timeout finalize. Disconnected students still need it.
- Do not allow rejoin while `expiring` or `expired`.
- Do not change session controllers, session events, or session views.
- Do not regress feature #22: batch sync while the jump is open, summary `JumpAttemptUpdated`, extra time, variable-length `question_list`.
- Keep `student cannot start an attempt on an expiring jump` and `student cannot start attempt for inactive jump`.
- If `updateAnswer()` remains, give it the same post-close helper as sync so leftover callers cannot wedge a client, but do not revive it in the learner UI.
- Keep `JumpExpired` public. Do not switch the attempt page to `Echo.private` for this event unless a test proves the public listener is already gone.

### 10.1 Ask for Clarification Only If One of These Conditions Occurs

- late re-grade cannot share `ExpireJumps` scoring without also running mastery, and extracting scoring would become a large rewrite;
- broadcasting `JumpExpired` when entering `expiring` breaks a teacher UI that treats the event as “scores are ready” and shows empty/zero scores;
- `AnalyseJump` cannot be re-dispatched after a late save without duplicating suggested-question rows or otherwise corrupting existing analysis;
- a current consumer of submit’s 403-on-finished behaviour depends on that error **after** the jump is closed (not while it is still open).

## 11. Suggested Implementation Sequence for the AI Agent

Execute these steps in order. Do not start frontend redirects before submit is close-safe; otherwise the client will keep hitting 403. After each substantive step, run the narrowest relevant tests.

Copy feature #24’s attempt-view terminate helper shape (`finalizeAttempt`, local deadline, idempotent guards). Copy feature #22’s jump payload names. Do not edit session files.

### Step 1: Verify the Current Runtime Path

Primary files:

- `app/Jobs/ExpireJumps.php`
- `app/Events/JumpExpired.php`
- `app/Models/Jump.php`
- `app/Http/Controllers/JumpAttemptController.php`
- `app/Http/Controllers/JumpController.php`
- `resources/js/components/views/jump-attempt-view.vue`
- `resources/js/stores/jumpAttemptStore.js`
- `resources/js/components/views/jump-results-view.vue`
- `tests/Feature/JumpAttemptTest.php`
- `tests/Feature/ExpireJumpsTest.php`

Checklist:

- [ ] Confirm submit already accepts `question_list` and does not grade.
- [ ] Confirm submit 403s when the attempt is already finished.
- [ ] Confirm sync 403s only when the attempt is not `inProgress`.
- [ ] Confirm `JumpExpired` on the attempt page redirects without submit and resets the dirty map.
- [ ] Confirm results rejoin visibility is tied to `status === 'expired'`.
- [ ] Confirm expire-now sets `expiring` and does not broadcast.
- [ ] Write down any mismatch between this spec and the current code before proceeding.

Done when:

- the agent can describe the race between `ExpireJumps` and client submit in one paragraph, including the current redirect-without-submit bug.

### Step 2: Add Closed / Grace-Window Helpers

Primary files:

- `app/Models/Jump.php`
- `tests/Feature/ExpireJumpsTest.php` or a focused jump model test in `JumpAttemptTest.php`

Checklist:

- [ ] Add `POST_EXPIRY_ANSWER_GRACE_SECONDS = 180`.
- [ ] Add `isClosed()` and `allowsLateAnswerSave()`.
- [ ] Cover: future `expiration` + `active` → not closed, no late save;
- [ ] `expiration` 1 minute past + `active` → closed, late save true;
- [ ] `status === 'expiring'` → closed, late save true even if `expiration` is 10 minutes past;
- [ ] `status === 'expired'` and `expiration` 1 minute past → late save true;
- [ ] `status === 'expired'` and `expiration` 5 minutes past → late save false.

Done when:

- those helpers are the only place the 3-minute number lives on the jump backend.

### Step 3: Make Submit Close-Safe

Primary files:

- `app/Http/Controllers/JumpAttemptController.php`
- `tests/Feature/JumpAttemptTest.php`

Checklist:

- [ ] Keep open-jump already-finished → 403.
- [ ] Closed + in-progress + `question_list` inside window → persist, finish, 200, still ungraded if jump is `expiring`.
- [ ] Closed + already timeout-finished by the job + `question_list` inside window → persist, re-grade if jump is `expired`, 200, no second mastery pass.
- [ ] Closed + already finished + `question_list` outside window → do not change answers, still 200.
- [ ] Closed + already finished + no `question_list` body → 200, unchanged row.
- [ ] Open-jump submit still does not grade.

Done when:

- the job-then-client-submit race no longer 403s, and late `question_list` inside the window is stored.

### Step 4: Make Sync Close-Safe

Primary files:

- `app/Http/Controllers/JumpAttemptController.php`
- `resources/js/stores/jumpAttemptStore.js`
- `tests/Feature/JumpAttemptTest.php`

Checklist:

- [ ] Open jump: still 204 / still reject finished attempts.
- [ ] Closed + writable: persist and return `{ jump_closed: true, saved: true }`.
- [ ] Closed + not writable: no persist, `{ jump_closed: true, saved: false }`.
- [ ] Store: do not throw on `jump_closed`; surface it to the view.

Done when:

- a flush that races close cannot block the following submit.

### Step 5: Late Re-Grade Without Double Mastery

Primary files:

- `app/Jobs/ExpireJumps.php`
- `app/Http/Controllers/JumpAttemptController.php`
- `tests/Feature/ExpireJumpsTest.php`

Checklist:

- [ ] Extract attempt grading used by the job so submit can re-grade an already-expired attempt.
- [ ] Late save on `expired` that changed answers updates score/statuses and re-dispatches `AnalyseJump`.
- [ ] Late save on `expired` does not run `updateMasteryAndDifficulty` again.
- [ ] Late save on `expiring` does not grade and does not dispatch `AnalyseJump`.
- [ ] Existing “in-progress attempts are marked as timed out” and “attempts are graded with correct score” tests still pass.

Done when:

- disconnected students are still finalized by the job, and a connected student who sends later answers does not corrupt mastery.

### Step 6: Broadcast Close on Teacher Expire-Now

Primary files:

- `app/Http/Controllers/JumpController.php`
- `tests/Feature/ExpireJumpsTest.php` or `CourseTest.php`

Checklist:

- [ ] When an `active` jump is updated to `expiring`, or its `expiration` is moved into the past, broadcast `JumpExpired`.
- [ ] Delaying `expiration` into the future does not broadcast.
- [ ] Do not skip the job; the job still grades remaining in-progress attempts, sets `expired`, broadcasts again, and analyses.
- [ ] Learner duplicate-event handling stays idempotent.

Done when:

- expire-now stops learners without waiting for the scheduler tick.

### Step 7: Learner Attempt View Force-Stop

Primary files:

- `resources/js/components/views/jump-attempt-view.vue`
- `resources/js/stores/jumpAttemptStore.js`

Checklist:

- [ ] Add a jump-level deadline from `jump.expiration` for every in-progress attempt.
- [ ] Echo `JumpExpired` and the local deadline share one terminate helper.
- [ ] That helper: stop timers and sync loop → best-effort flush → submit with local `question_list` → `router.replace` JumpResults.
- [ ] Do not reset the dirty map before submit.
- [ ] **Terminer le saut** uses the same helper; a 200 for an already-finished closed attempt still redirects.
- [ ] Submit errors after close must not resurrect the answer grid.
- [ ] Personal extra-time countdown still works while the jump is open.

Done when:

- expire-now, scheduled expiration, `JumpExpired` after the job, and manual terminate-after-close all leave the attempt page with `question_list` sent.

### Step 8: Results Rejoin Hiding and Score Reveal

Primary files:

- `resources/js/components/views/jump-results-view.vue`
- `app/Http/Controllers/JumpRejoinDemandController.php`
- `tests/Feature/JumpRejoinDemandTest.php`

Checklist:

- [ ] Hide **Demander à reprendre** when the jump is closed (`expiring` included).
- [ ] Keep timeout hide while the jump is still open.
- [ ] Show score/correction only when `status === 'expired'`.
- [ ] Refetch on `JumpExpired` and on local `expiration` so rejoin disappears, then scores appear after grading.
- [ ] Rejoin API rejects closed jumps, including `expiring` and the 3-minute window.

Done when:

- a closed jump results page cannot send a rejoin demand from the UI or the API.

### Step 9: Teacher Observation

Primary files:

- `resources/js/components/views/course-details-view.vue`
- `resources/js/components/views/division-details-view.vue`

Checklist:

- [ ] Existing `JumpExpired` listeners still refetch / mark the jump finished.
- [ ] Expire-now now emits that event, so the teacher does not wait for the job to see the jump leave `active`.
- [ ] Incoming `JumpAttemptUpdated` summaries from job finalization / late submits still merge in place (feature #22).

Done when:

- a teacher watching a live jump sees it close without a full manual reload.

### Step 10: Focused Verification

Checklist:

- [ ] `php artisan test --compact tests/Feature/JumpAttemptTest.php`
- [ ] `php artisan test --compact tests/Feature/ExpireJumpsTest.php`
- [ ] `php artisan test --compact tests/Feature/JumpRejoinDemandTest.php`
- [ ] Run any jump update / course test that covers expire-now if Step 6 touched `JumpController`.
- [ ] `vendor/bin/pint --dirty`
- [ ] Manually verify the cases in §13.
- [ ] Confirm session expiry tests still pass if anything shared was touched (they should not have been): `php artisan test --compact tests/Feature/AttemptTest.php tests/Feature/ExpireKangourouSessionsTest.php`

Done when:

- the automated tests covering the touched jump backend paths pass and the manual learner/teacher checks match this specification.

## 12. Acceptance Criteria

- When a jump closes, a learner still on `jump-attempt-view.vue` is redirected to jump results.
- Unsaved local answers that arrive while `expiring`, or within 3 minutes of `expiration`, are stored, even if `ExpireJumps` already timeout-finished the attempt.
- Answers that arrive after the jump is `expired` and more than 3 minutes after `expiration` are not stored.
- Post-close submit never returns a client-facing error solely because the jump closed or the job already finished the attempt.
- **Terminer le saut** after close ends the attempt and redirects; it saves answers only inside the writable window.
- Open-jump double submit is still 403.
- New attempts after close are still 403.
- Rejoin after close (`expiring` included) is still 403, and **Demander à reprendre** is not shown on closed jump results.
- Jump results still hide scores until `status === 'expired'`.
- Feature #22 batch sync while the jump is open, extra time, and summary events still work.
- Disconnected in-progress attempts are still timeout-finalized and graded by the job.
- Mastery is not applied twice for one jump attempt.
- Session expiry is unchanged.

## 13. Testing Requirements

### Backend Tests

Add or update Pest feature tests. Keep existing tests that encode still-valid rules; change only tests whose expected result this spec explicitly replaces.

Jump helper tests:

- `isClosed()` / `allowsLateAnswerSave()` around `expiration`, `expiring`, and the 3-minute boundary;
- teacher update to `expiring` dispatches `JumpExpired`;
- delaying `expiration` does not dispatch `JumpExpired`.

`tests/Feature/JumpAttemptTest.php`:

- open jump, already finished submit → still 403;
- closed, in-progress submit with `question_list` while `expiring` → list persisted, finished, items still `pending`, score still 0;
- expired, job already timeout-finished, submit with a different `question_list` inside the window → list overwritten, score updated, 200, no second mastery pass;
- expired, already finished, submit with `question_list` outside the window (`expiration` 5 minutes ago) → list unchanged, 200;
- expired, already finished, submit without `question_list` → 200, unchanged;
- open-jump submit still does not grade;
- sync while jump is open still 204 and still rejects a finished attempt;
- sync while closed and writable persists and returns `jump_closed: true`, `saved: true`;
- sync while closed and not writable does not persist and returns `saved: false`;
- another user still cannot submit after close.

`tests/Feature/ExpireJumpsTest.php`:

- keep auto-timeout, grading, and `JumpExpired` broadcast tests.

`tests/Feature/JumpRejoinDemandTest.php`:

- `expiring` jump cannot create a rejoin demand;
- `expired` jump cannot create a rejoin demand, including inside the 3-minute window.

### Frontend Verification

Manually verify at minimum:

- learner on jump-attempt is redirected to jump results when the jump expires, and late local answers appear on the stored attempt;
- clicking **Terminer le saut** after close redirects and does not show an error;
- if the job finishes the attempt first, the client submit still succeeds and still records in-window answers;
- jump results hides **Demander à reprendre** as soon as the jump is closed, including `expiring`;
- scores appear after the job sets `expired`, without requiring a full reload if the results page is already open;
- teacher expire-now stops learners promptly;
- extra time still works before close;
- Kangourou session attempts are unaffected.

## 14. Definition of Done

This feature is complete when:

- jump close force-stops connected learners and records in-window unsaved answers;
- submit after close is a normal termination, not an error;
- closed jump results cannot request rejoin, and still wait for `expired` before showing scores;
- the expiry job still protects disconnected learners and still owns first-time grading and mastery;
- previously written jump attempt, rejoin, expire-job, and live-sync behaviour still pass their tests;
- session expiry is untouched;
- the implementation is covered by focused automated tests and the manual checks above.
