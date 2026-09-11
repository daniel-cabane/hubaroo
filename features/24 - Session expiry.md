# Fix: Session Expiry Force-Stop, Late Answer Save, and Rejoin Hiding

## 1. Objective

When a Kangourou session expires, every learner who is still taking it must be force-stopped, their latest local answers must be recorded, and they must land on the results page. Rejoin must not remain available after expiry.

This is the expiry counterpart of feature #23. Reuse the session submit contract that already accepts an authoritative `answers` array. Do not invent a third finalization path, and do not change jump expiry.

## 2. Product Decisions Locked for This Iteration

- This feature applies only to Kangourou sessions, not to jumps.
- The force-stop signal is the existing `SessionExpired` event on the private `session.{id}` channel, plus a local `expires_at` deadline on the learner client so guests and dropped Echo connections still stop.
- Force-stop means: stop the attempt UI, persist the latest local `answers`, and redirect to `Results` (`results-view.vue`). It does not mean deleting attempts or changing unrelated sessions.
- Unsaved local answers are sent through the existing submit path (`POST /api/attempts/{attempt}/submit` with the full `answers` array). Periodic sync is only a helper, not the terminate contract.
- The backend allows a 3-minute post-expiry write window, measured from `expires_at`, so a late client flush or submit still records answers if the session expired less than 3 minutes ago.
- After that window, the backend must not persist new answers, but it must still return a normal success response that lets the frontend terminate. Do not send a client-facing error for “session already expired” on submit or on a late sync that is only trying to finish.
- Clicking **Terminer la session** on `attempt-view.vue` after expiry uses the same contract: no error, end the attempt, save answers only if still inside the 3-minute window.
- On `results-view.vue`, hide **Demander à reprendre** whenever the session is expired. Do not wait for `correctionAvailable` to become true.
- Keep the expiry job’s current disconnected-student safety net: in-progress attempts that never reach the server are still auto-finalized as `timeout` and graded.
- Do not allow new attempts, and do not allow rejoin, after expiry. The 3-minute window is only for recording answers of attempts that already existed.

## 3. Why This Change Is Needed

Expiry already exists, but the learner-facing end of it is racy and incomplete.

The current contract is:

- `ExpireKangourouSessions` marks the session expired, auto-submits in-progress attempts from **server-side** answers, grades them, computes analysis, and broadcasts `SessionExpired` with only `{ session_id }`.
- Authenticated learners on `attempt-view.vue` listen to that event and call `autoSubmit('timeout')`, which flushes dirty answers then submits the local `answers` array.
- That submit currently 403s if the job already marked the attempt `finished`.
- Active sync currently 403s as soon as the session is no longer active, so the flush that runs just after expiry can fail.
- Guests never receive `SessionExpired` because the channel is private and Echo is wrapped in `authStore.isAuthenticated`. They only stop when their personal countdown hits 0.
- `results-view.vue` hides rejoin only when `termination === 'timeout'` or when correction data is already visible. A learner sitting on results when the session expires can still see **Demander à reprendre**.

This feature changes the contract to:

- expiry always force-stops connected learners, including guests;
- the latest local answers are recorded when they arrive inside a 3-minute grace window, even if the job already finalized the row;
- submit after expiry is success-oriented, never an error the UI gets stuck on;
- expired results never offer rejoin.

## 4. How This Differs From Adjacent Features

The implementing agent must not copy jump expiry or feature #23 blindly.

| Topic | Current session expiry | This feature | Do not copy |
| --- | --- | --- | --- |
| Force-stop | Echo `SessionExpired` for authenticated learners only | Echo **and** local `expires_at` deadline for every learner, including guests | Jump `JumpExpired` handler, which redirects **without** submitting answers |
| Final answers | Job uses whatever answers are already in the database | Client submit carries the authoritative local `answers` array from feature #23; job remains the fallback for disconnects | Reintroducing per-answer `PATCH /answer` as the expiry path |
| Already-finished submit | `403 This attempt has already been submitted.` | After expiry: return the normal success payload; persist new answers only inside the 3-minute window | Changing the **active-session** double-submit 403 |
| Active sync after expiry | `403 This session is no longer active.` | Inside the window: persist changes and tell the client to terminate. Outside the window: do not persist, still tell the client to terminate | Treating post-expiry sync as a retryable failure |
| Rejoin | Backend already rejects expired sessions; results UI can still show the button | Hide the button on results as soon as the session is expired | Changing jump rejoin, or allowing rejoin during the 3-minute window |
| Analysis / delayed grading | Computed when the job expires the session | Keep that. If a grace-window save changes answers after the job, re-grade that attempt and recompute analysis | Skipping the job’s auto-finalize, or double-dispatching mastery |

Feature `XX - Unify attempt.md` is out of scope. Jump files are a reference for “event then leave the attempt UI”, not for the answer-persistence rules.

## 5. Current-State Notes for the Implementing Agent

Verified runtime facts at the time this spec was written. Re-check them before editing.

- `ExpireKangourouSessions` is scheduled every minute from `routes/console.php`.
- The job:
	- selects `status = active` and `expires_at <= now()`;
	- sets `status = expired`;
	- for each attempt: if `inProgress`, sets `termination = timeout`; if `inProgress` or `score === null`, calls `GradingService::gradeAndSave()` and dispatches `UpdateMasteryAndDifficulty`;
	- computes per-division analysis;
	- broadcasts `SessionExpired`.
- `SessionExpired::broadcastWith()` is `{ session_id }`. Keep that payload. Do not add answers.
- `KangourouSession::isActive()` is `status === 'active' && expires_at->isFuture()`.
- `KangourouSession::isExpired()` is `status === 'expired' || expires_at->isPast()`.
- `shouldDelayGrading()` is already false once `isExpired()` is true, including the case where `expires_at` has passed but `status` is still `active`. Keep that.
- `AttemptController::submit()` does **not** check `isActive()`. An in-progress attempt can already be submitted after expiry. The failure is the already-finished 403.
- `AttemptController::sync()` and `updateAnswer()` reject any session that is not `isActive()`.
- Feature #23 already made submit accept `answers`, and `attempt-view.vue` already sends `answers` on submit, timeout, blur, abandon, beacon, and `SessionExpired` auto-submit.
- `attempt-view.vue` sets `isSubmitting = true` **before** the expiry flush, so a 403 from sync does not recursively call `autoSubmit` again. The next line, `submitAttempt()`, is what 403s and leaves the learner stuck.
- The **Terminer la session** button lives in `resources/js/components/views/attempt-view.vue`, not in `session-view.vue`. `session-view.vue` is the join/rejoin landing page.
- `results-view.vue` shows **Demander à reprendre** only inside the delayed-correction notice (`!correctionAvailable`), and already hides it for `termination === 'timeout'`.
- `RejoinDemandController::store()` already returns 403 when `session->isExpired()`. Keep that.
- Teacher **Expire now** in `session-details-view.vue` only PATCHes `expires_at` to now. It does not set `status` to `expired` and does not broadcast `SessionExpired`. Learners keep the old `expires_at` in memory until the job runs (up to about one minute).
- Guests cannot subscribe to `PrivateChannel('session.{id}')`. Any Echo-only force-stop will miss them.
- `UpdateMasteryAndDifficulty` is not idempotent. Grading the same attempt twice from the expiry job and then from a late submit would apply mastery twice. Late re-grades must not dispatch that job again when the attempt was already scored.

### 5.1 Primary Implementation Surfaces

These are the first files the AI agent should inspect and are the most likely edit surfaces:

- `app/Models/KangourouSession.php`
	- add the 3-minute grace constant and a helper used by submit/sync.
- `app/Http/Controllers/AttemptController.php`
	- `submit()` and `sync()` are the two write paths that must become expiry-safe.
	- `updateAnswer()` should use the same helper if the endpoint is kept.
- `app/Jobs/ExpireKangourouSessions.php`
	- keep auto-finalize, grading, analysis, and `SessionExpired`.
	- analysis recompute must be reusable after a grace-window save.
- `app/Events/SessionExpired.php`
	- keep the current payload unless a teacher/learner listener truly needs more.
- `app/Http/Controllers/KangourouSessionController.php`
	- teacher `update()` of `expires_at` into the past should force-stop immediately, not wait for the scheduler.
- `resources/js/components/views/attempt-view.vue`
	- SessionExpired / local deadline / Terminer la session / redirect.
- `resources/js/stores/attemptStore.js`
	- submit and sync must treat post-expiry success as success, including already-finished rows.
- `resources/js/components/views/results-view.vue`
	- hide rejoin when the session is expired; refresh correction when expiry happens while the page is open.
- `tests/Feature/AttemptTest.php`
	- primary backend tests for submit/sync grace-window behavior.
- `tests/Feature/ExpireKangourouSessionsTest.php`
	- keep existing job tests passing; add coverage only if the job API changes.
- `tests/Feature/KangourouSessionTest.php`
	- helper tests and teacher expire-now broadcast if that path is added.

Secondary impact review surfaces:

- `resources/js/components/views/session-view.vue`
	- still has **Demander à reprendre** for an existing finished attempt; hide or disable it when the session is expired so the button cannot 403.
- `resources/js/components/views/session-details-view.vue` and `resources/js/components/views/division-details-view.vue`
	- teacher UIs currently do not listen to `SessionExpired`; mark the open session expired in place so monitoring does not stay stuck on `active`.
- `app/Http/Controllers/RejoinDemandController.php`
	- should stay expired-forbidden; do not open a rejoin window during the 3 minutes.
- `tests/Feature/RejoinDemandTest.php`
	- `cannot create a rejoin demand for an expired session` must still pass.
- `tests/Feature/DifficultyMasteryTest.php`
	- uses the expiry job; must not start double-counting mastery.

These should not be the first edit targets, but review them if submit, events, or session status handling change.

## 6. Scope

### In Scope

- force-stopping every in-progress Kangourou attempt when the parent session expires;
- redirecting those learners to `results-view.vue`;
- persisting the learner’s latest local answers through submit, including after the job already timeout-finalized the row, if `expires_at` is less than 3 minutes in the past;
- making post-expiry **Terminer la session** and `SessionExpired` auto-submit return a normal success payload instead of an error;
- making post-expiry sync fail closed for writes outside the window, but fail open for frontend termination;
- hiding **Demander à reprendre** on results when the session is expired;
- broadcasting `SessionExpired` immediately when a teacher moves `expires_at` into the past;
- covering guests via a local `expires_at` deadline;
- backend tests for the grace window, already-finished post-expiry submit, and unchanged active-session rules.

### Out of Scope

- jump expiry, jump rejoin, or `JumpExpired` behavior;
- allowing new attempts after expiry;
- allowing rejoin during or after the 3-minute window;
- changing delayed-correction rules, scoring math, shuffle, or blur security;
- changing the `AttemptUpdated` summary payload from feature #23;
- replacing `ExpireKangourouSessions` with a client-only finalize;
- unifying session and jump attempts;
- making `UpdateMasteryAndDifficulty` a full rebuild.

## 7. User-Visible Behavior

### 7.1 Learner Still On the Attempt Page

When the session expires, whether by scheduled `expires_at`, by the expiry job, or by the teacher clicking expire-now:

- the attempt UI stops immediately;
- pending local answers are flushed best-effort, then submitted with the full local `answers` array;
- the learner is redirected to `Results` for that `code` and `attemptId`;
- the learner must not remain able to change answers;
- the learner must not see a red error toast that blocks termination.

This includes:

- authenticated Echo `SessionExpired`;
- guest or offline local `expires_at` countdown;
- clicking **Terminer la session** after the session has already expired;
- personal timer timeout that happens to coincide with session expiry (existing `autoSubmit('timeout')`).

If submit reports success because the attempt was already finished, still redirect. The page the learner wants is results, not a stuck attempt.

### 7.2 Learner On the Results Page

If the session is expired:

- **Demander à reprendre** is not rendered, regardless of termination reason;
- pending / approved / denied rejoin copy is also hidden, because rejoin is no longer possible;
- if delayed correction had been waiting, refetch the session/attempt so the existing correction UI can appear without a manual refresh.

If the session is still active:

- keep today’s rejoin UI, including hiding it for `termination === 'timeout'`.

### 7.3 Learner On the Join Page

`session-view.vue` already shows “Cette session a expiré” for new joiners. If the 409 existing-attempt branch is showing **Demander à reprendre** for an expired session, hide that button too. The backend already rejects the demand.

### 7.4 Disconnected Learners

If the client never receives the event and never submits:

- the expiry job still timeout-finalizes and grades from server-side answers, as today;
- those learners see results the next time they open the attempt or results route.

### 7.5 Teachers

Teacher live tables already receive `AttemptUpdated` summaries when attempts finish. After expiry they should also see the session itself become expired without a full page reload, using `SessionExpired`. Do not rebuild the observation tables in this feature.

## 8. Functional Requirements

### 8.1 Grace Window

Add one backend constant and use it everywhere:

```php
public const POST_EXPIRY_ANSWER_GRACE_SECONDS = 180;
```

Put it on `KangourouSession` with a named helper, for example `allowsLateAnswerSave(): bool`.

The window is:

- `expires_at` is in the past, **and**
- `now() < expires_at + 180 seconds`.

Use `expires_at`, not `status`. The job may not have flipped `status` yet when the first late request arrives.

| Request | Session still active (`expires_at` future) | Inside grace window | After grace window |
| --- | --- | --- | --- |
| New attempt | allowed under current rules | rejected as expired, unchanged | rejected as expired, unchanged |
| Rejoin demand | current rules | rejected as expired, unchanged | rejected as expired, unchanged |
| Active sync | current feature #23 rules | persist changes if attempt is writable per §8.3 | do not persist changes; tell client to terminate |
| Submit with answers | current rules; finished → 403 | persist answers, finish, return success | do not persist new answers; still finish if needed; return success |
| Submit without answers, already finished | 403 | return current finished attempt as success | return current finished attempt as success |

### 8.2 `SessionExpired` Force-Stop

Keep broadcasting `SessionExpired` from `ExpireKangourouSessions` after the session is marked expired.

Also broadcast `SessionExpired` when an authorized teacher updates `expires_at` to a past timestamp (the expire-now path). If the job later broadcasts the same event again, the learner client must ignore the duplicate because it is no longer `inProgress` / already `isSubmitting`.

Do not broadcast answers on `SessionExpired`. The client already has them locally.

On `attempt-view.vue`:

- authenticated: keep the Echo listener;
- everyone, including guests: start a session-level deadline from `session.expires_at` when the attempt is in progress;
- both paths call the same terminate helper;
- that helper must be idempotent (`isSubmitting` / `!isInProgress` guards stay).

Do not copy the jump handler that only `router.replace`s. Session expiry must still submit answers.

### 8.3 Submit After Expiry

`POST /api/attempts/{attempt}/submit` is the terminate contract.

Keep:

- owner check;
- active session + already finished → 403 (existing test);
- delayed vs immediate grading via `shouldDelayGrading()`;
- feature #23 authoritative `answers` normalization;
- `AttemptUpdated` summary broadcast on a real state change.

Change:

- if the session is expired (`isExpired()`), never 403 for “already finished”;
- if answers are present and `allowsLateAnswerSave()` is true, persist them even when the row is already `finished`;
- then, if the session is expired, grade immediately because `shouldDelayGrading()` is already false;
- if the attempt was already scored (`score !== null`) before this late save, re-run `gradeAndSave()` so score/statuses match the new answers, but **do not** dispatch `UpdateMasteryAndDifficulty` again;
- if this is the first grade (`score === null`), dispatch mastery as today;
- if answers are present but the grace window has closed, ignore the new answers and return the current finished attempt in the normal `{ message, score, attempt }` shape;
- if the attempt is still `inProgress` after expiry, finish it with the provided termination (default `submitted` from the request, or `timeout` from auto-submit) even outside the window, using already-stored answers when late answers are rejected.

Termination rules for the race with the job:

- if the job already set `termination = timeout` and the client then submits inside the window with new answers, keep `timeout` when the client sent `timeout` (SessionExpired / timer). If the client explicitly sent `submitted` because they clicked **Terminer la session**, keep `submitted`. Do not revert a pre-expiry `submitted` / `blurred` / `abandoned` to `timeout`.
- if the attempt was finished **before** expiry with a student termination, and a duplicate submit arrives after expiry, return success without changing answers unless `allowsLateAnswerSave()` and the request includes `answers`. Prefer not to overwrite a completed pre-expiry paper with an empty/partial body. Only apply `answers` when the request actually contains that key.

After a grace-window save that changed answers on an already-expired session, recompute that session’s division analysis with the same algorithm as the expiry job. Extract the current `computeAnalysis()` logic so both call sites share it. Do not duplicate the scoring rules.

### 8.4 Sync After Expiry

`PATCH /api/attempts/{attempt}/sync` must stop being a hard 403 the moment `expires_at` passes.

Inside the grace window:

- if the attempt is `inProgress`, or is `finished` with `termination = timeout` from the job, apply the change list exactly as today’s sync does;
- return a JSON body the frontend can understand, not silent 204, for example `{ "session_expired": true, "saved": true }`;
- broadcast `AttemptUpdated` only when something actually changed, same as feature #23.

Outside the grace window, or if the attempt is finished with a non-timeout termination and the session is expired:

- do not persist changes;
- return `{ "session_expired": true, "saved": false }` with HTTP 200;
- do not 403.

While the session is still active, keep 204, validation errors, finished-attempt 403, and unauthorized 403 unchanged.

Frontend:

- `flushAnswerChanges` must not throw the learner out of `autoSubmit` / `handleSubmit` when it receives `session_expired`;
- if `session_expired` arrives while the attempt is still in progress and the user has not already submitted, stop the sync loop and run the same terminate helper (submit + redirect);
- do not keep retrying a 3-second loop after expiry.

### 8.5 Results Rejoin Button

On `results-view.vue`:

- compute session expiry from `session.status === 'expired'` **or** `expires_at` in the past;
- hide the entire rejoin block when expired;
- keep the current `termination === 'timeout'` hide for active sessions;
- if the page is open when expiry happens, set the expired flag immediately. Authenticated users may listen to `SessionExpired`; everyone can use a local `expires_at` timer;
- then refetch attempt + session so delayed correction can appear.

Do not hide the score/review UI. Do not change jump results.

## 9. Data Contract

No new routes.

### 9.1 Submit

Keep `POST /api/attempts/{attempt}/submit`.

Success body stays:

```json
{
	"message": "Attempt submitted and graded.",
	"score": 96.5,
	"attempt": { }
}
```

After expiry, `message` / `score` / `attempt` must still be present even when the attempt was already finished. The frontend redirect depends on a non-throwing response, not on a new field.

`attempt` in the JSON body is the full attempt (existing show/submit shape, including masked/unmasked answers via `maskCorrectionIfNeeded()`). That is different from the `AttemptUpdated` broadcast summary. Do not mix them.

### 9.2 Sync After Expiry

Active-session success remains `204 No Content`.

Post-expiry response:

```json
{
	"session_expired": true,
	"saved": true
}
```

`saved` is `true` only when the grace window accepted the writes.

### 9.3 Event

Keep:

```json
{ "session_id": 45 }
```

on private `session.{id}`, event name `SessionExpired`.

## 10. Implementation Guardrails

- Reuse `submit()` as the authoritative persist+terminate path from feature #23. Do not add `POST /attempts/{id}/finalize-expired`.
- Do not reopen the private `session.{id}` channel to guests. Use `expires_at` on the client instead.
- Do not persist answers after the 3-minute window, even if the client still has dirty state.
- Do not 403 the terminate path after expiry. 403 is how the current UI gets stuck.
- Do not dispatch `UpdateMasteryAndDifficulty` a second time for an attempt that already has a score.
- Do not skip the job’s timeout finalize. Disconnected students still need it.
- Do not allow rejoin inside the grace window.
- Do not change jump controllers, jump events, or jump views.
- Do not regress feature #23: batch sync while active, summary `AttemptUpdated`, extra_time listener, guest ownership rule, delayed correction masking while the session is still active.
- Keep `cannot create an attempt for an expired session` and `cannot create a rejoin demand for an expired session`.
- If `updateAnswer()` remains, give it the same post-expiry helper as sync so leftover callers cannot wedge a client, but do not revive it in the learner UI.

### 10.1 Ask for Clarification Only If One of These Conditions Occurs

- a late save that overwrites a timeout-finalized attempt cannot recompute analysis without a new persistent schema;
- `UpdateMasteryAndDifficulty` cannot be skipped on re-grade without leaving mastery permanently wrong for that attempt, and making it idempotent would become a large rewrite;
- teacher expire-now cannot broadcast `SessionExpired` without also flipping `status`, and flipping `status` would skip job analysis;
- a current consumer of submit’s 403-on-finished behavior depends on that error **after** expiry (not while the session is active).

## 11. Suggested Implementation Sequence for the AI Agent

Execute these steps in order. Do not start frontend redirects before submit is expiry-safe; otherwise the client will keep hitting 403. After each substantive step, run the narrowest relevant tests.

### Step 1: Verify the Current Runtime Path

Primary files:

- `app/Jobs/ExpireKangourouSessions.php`
- `app/Events/SessionExpired.php`
- `app/Models/KangourouSession.php`
- `app/Http/Controllers/AttemptController.php`
- `resources/js/components/views/attempt-view.vue`
- `resources/js/stores/attemptStore.js`
- `resources/js/components/views/results-view.vue`
- `tests/Feature/AttemptTest.php`
- `tests/Feature/ExpireKangourouSessionsTest.php`

Checklist:

- [ ] Confirm submit already accepts `answers` and already works for in-progress attempts on an expired session.
- [ ] Confirm submit 403s when the attempt is already finished, including the job-timeout case.
- [ ] Confirm sync 403s when `!isActive()`.
- [ ] Confirm `SessionExpired` auto-submit already sends `answers` then `router.replace({ name: 'Results' })`.
- [ ] Confirm guests do not subscribe to the session channel.
- [ ] Confirm results rejoin visibility rules.
- [ ] Write down any mismatch between this spec and the current code before proceeding.

Done when:

- the agent can describe the race between the expiry job and client submit in one paragraph, including who 403s and who loses unsaved answers.

### Step 2: Add the Grace-Window Helper

Primary files:

- `app/Models/KangourouSession.php`
- `tests/Feature/KangourouSessionTest.php`

Checklist:

- [ ] Add `POST_EXPIRY_ANSWER_GRACE_SECONDS = 180`.
- [ ] Add `allowsLateAnswerSave(): bool` based on `expires_at`.
- [ ] Cover: future expiry → false; 1 minute past → true; 3 minutes minus 1 second → true; 3 minutes plus 1 second → false; `status` still `active` but `expires_at` past and inside window → true.

Done when:

- the helper is the only place the 3-minute number lives on the backend.

### Step 3: Make Submit Expiry-Safe

Primary files:

- `app/Http/Controllers/AttemptController.php`
- `tests/Feature/AttemptTest.php`

Checklist:

- [ ] Keep active-session already-finished → 403.
- [ ] Expired + in-progress + answers inside window → persist, finish, grade, 200.
- [ ] Expired + already finished by the job (`timeout`) + answers inside window → persist, re-grade, 200, no second mastery dispatch.
- [ ] Expired + already finished + answers outside window → do not change answers, still 200.
- [ ] Expired + already finished + no answers body → 200, unchanged row.
- [ ] Active session submit behavior, delayed correction, and extra_time are unchanged.

Done when:

- the job-then-client-submit race no longer 403s, and late answers inside 3 minutes are stored.

### Step 4: Make Sync Expiry-Safe

Primary files:

- `app/Http/Controllers/AttemptController.php`
- `resources/js/stores/attemptStore.js`
- `tests/Feature/AttemptTest.php`

Checklist:

- [ ] Replace the expired-session sync test that currently expects 403 for `expired()` (1 minute ago, which is inside the window).
- [ ] Inside window: persist and return `{ session_expired: true, saved: true }`.
- [ ] Outside window: no persist, `{ session_expired: true, saved: false }`.
- [ ] Active session: still 204 / still reject finished attempts.
- [ ] Store: do not throw on `session_expired`; surface it to the view.

Done when:

- a flush that races expiry cannot block the following submit, and unsynced answers inside the window still reach the database if submit later omits them.

### Step 5: Keep Analysis and Mastery Correct

Primary files:

- `app/Jobs/ExpireKangourouSessions.php`
- `app/Http/Controllers/AttemptController.php`
- `tests/Feature/ExpireKangourouSessionsTest.php`
- `tests/Feature/DifficultyMasteryTest.php` if mastery assertions exist

Checklist:

- [ ] Extract analysis computation so submit can reuse it after a late save.
- [ ] Late save that changes answers recomputes analysis.
- [ ] Already-scored late save does not dispatch `UpdateMasteryAndDifficulty` again.
- [ ] Existing “job auto-submits in-progress attempts” and “job grades delayed attempts” tests still pass.

Done when:

- disconnected students are still finalized by the job, and a connected student who sends later answers does not corrupt mastery or leave stale analysis.

### Step 6: Broadcast Expiry on Teacher Expire-Now

Primary files:

- `app/Http/Controllers/KangourouSessionController.php`
- `tests/Feature/KangourouSessionTest.php`

Checklist:

- [ ] When `expires_at` is updated to a past time on an active session, broadcast `SessionExpired`.
- [ ] Do not broadcast when expiry is delayed into the future.
- [ ] Do not skip the job; the job still flips `status`, grades remaining in-progress attempts, and computes analysis.
- [ ] Learner duplicate-event handling stays idempotent.

Done when:

- expire-now stops learners without waiting for the one-minute scheduler tick.

### Step 7: Learner Attempt View Force-Stop

Primary files:

- `resources/js/components/views/attempt-view.vue`
- `resources/js/stores/attemptStore.js`

Checklist:

- [ ] Add a session-level deadline from `session.expires_at` for every in-progress attempt, including guests.
- [ ] Echo `SessionExpired` and the local deadline share one terminate helper.
- [ ] That helper: stop timers and sync loop → best-effort flush → submit with local `answers` → `router.replace` Results.
- [ ] **Terminer la session** uses the same helper; a 200 for an already-finished expired attempt still redirects.
- [ ] Submit errors that are not network failures after expiry must not resurrect the answer grid.
- [ ] Extra-time `AttemptUpdated` listener still works while the session is active.

Done when:

- authenticated, guest, expire-now, scheduled expiry, and manual terminate-after-expiry all leave the attempt page with answers sent.

### Step 8: Results and Join Rejoin Hiding

Primary files:

- `resources/js/components/views/results-view.vue`
- `resources/js/components/views/session-view.vue`

Checklist:

- [ ] Hide **Demander à reprendre** (and pending/approved/denied rejoin copy) when the session is expired.
- [ ] Keep timeout hide while the session is active.
- [ ] Refetch session/attempt on expiry so delayed correction can appear.
- [ ] Hide the join-page rejoin button when the loaded session is expired.

Done when:

- an expired results page cannot send a rejoin demand from the UI.

### Step 9: Teacher Session Status on Expiry

Primary files:

- `resources/js/components/views/session-details-view.vue`
- `resources/js/components/views/division-details-view.vue`

Checklist:

- [ ] Listen to `SessionExpired` on an already-open session channel.
- [ ] Set local `status` to `expired` in place. Do not refetch the entire details payload unless that is already the current expiry UX.
- [ ] Incoming `AttemptUpdated` summaries from job finalization / late submits still merge in place (feature #23).

Done when:

- a teacher watching a live session sees it expire without a manual refresh.

### Step 10: Focused Verification

Checklist:

- [ ] `php artisan test --compact tests/Feature/AttemptTest.php`
- [ ] `php artisan test --compact tests/Feature/ExpireKangourouSessionsTest.php`
- [ ] `php artisan test --compact tests/Feature/KangourouSessionTest.php`
- [ ] `php artisan test --compact tests/Feature/RejoinDemandTest.php`
- [ ] Run any mastery test that uses the expiry job if Step 5 touched grading.
- [ ] `vendor/bin/pint --dirty`
- [ ] Manually verify the cases in §13.

Done when:

- automated tests for the touched backend paths pass, and the manual learner/teacher checks match this specification.

## 12. Acceptance Criteria

- When a session expires, a learner still on `attempt-view.vue` is redirected to results.
- Guests are force-stopped too, without requiring Echo.
- Unsaved local answers that arrive within 3 minutes of `expires_at` are stored, even if `ExpireKangourouSessions` already timeout-finished the attempt.
- Answers that arrive more than 3 minutes after `expires_at` are not stored.
- Post-expiry submit never returns a client-facing error solely because the session expired or the job already finished the attempt.
- **Terminer la session** after expiry ends the attempt and redirects; it saves answers only inside the window.
- Active-session double submit is still 403.
- New attempts after expiry are still 403.
- Rejoin after expiry is still 403, and **Demander à reprendre** is not shown on expired results.
- Delayed correction, immediate correction, extra time, guest ownership, and feature #23 batch sync while the session is active still work.
- Disconnected in-progress attempts are still timeout-finalized and graded by the job.
- Mastery is not applied twice for one attempt.
- Jump expiry is unchanged.

## 13. Testing Requirements

### Backend Tests

Add or update Pest feature tests. Keep existing tests that encode still-valid rules; change only tests whose expected result this spec explicitly replaces (notably sync-on-expired-session).

`tests/Feature/KangourouSessionTest.php`:

- `allowsLateAnswerSave()` true/false around the 3-minute boundary;
- `allowsLateAnswerSave()` uses `expires_at` even when `status` is still `active`;
- teacher update of `expires_at` into the past dispatches `SessionExpired`;
- delaying expiry does not dispatch `SessionExpired`.

`tests/Feature/AttemptTest.php`:

- active session, already finished submit → still 403;
- expired, in-progress submit with `answers` inside window → answers persisted, finished, graded;
- expired, job already timeout-finished, submit with different `answers` inside window → answers overwritten, score updated, 200;
- same case does not dispatch a second `UpdateMasteryAndDifficulty` (fake the bus / assert job count);
- expired, already finished, submit with `answers` outside window (`expires_at` 5 minutes ago) → answers unchanged, 200;
- expired, already finished, submit without `answers` → 200, unchanged;
- delayed-correction attempt submitted before expiry still waits; after expiry the existing job tests remain the source of truth;
- sync inside window on an expired in-progress attempt persists and returns `session_expired: true`, `saved: true`;
- sync outside window does not persist and returns `saved: false`;
- sync while session is active still 204 and still rejects a finished attempt;
- guest can still submit after expiry under the current ownership rule;
- authenticated user still cannot submit another user’s attempt after expiry.

`tests/Feature/ExpireKangourouSessionsTest.php`:

- keep auto-submit, delayed grading, analysis, and `SessionExpired` broadcast tests;
- add a test only if analysis was extracted: job still writes the same pivot shape.

`tests/Feature/RejoinDemandTest.php`:

- expired session still cannot create a rejoin demand, including inside the 3-minute window.

### Frontend Verification

Manually verify at minimum:

- authenticated learner on attempt-view is redirected to results when the session expires, and late local answers appear in the stored attempt;
- guest learner on attempt-view is redirected at `expires_at` without Echo;
- clicking **Terminer la session** after expiry redirects and does not show an error;
- if the job finishes the attempt first, the client submit still succeeds and still records in-window answers;
- results page hides **Demander à reprendre** as soon as the session is expired, including when the learner was already waiting on delayed correction;
- delayed correction appears after expiry without requiring a full reload if the results page is already open;
- join page does not offer rejoin for an expired session;
- teacher expire-now stops learners promptly;
- teacher observation marks the session expired on `SessionExpired`;
- extra time still works before expiry;
- jump attempts are unaffected.

## 14. Definition of Done

This feature is complete when:

- session expiry force-stops connected learners and records in-window unsaved answers;
- submit after expiry is a normal termination, not an error;
- expired results cannot request rejoin;
- the expiry job still protects disconnected learners;
- previously written attempt, rejoin, delayed-correction, mastery, and live-sync behavior still pass their tests;
- the implementation is covered by focused automated tests and the manual checks above.
