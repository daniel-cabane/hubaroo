
*** NOTE : This has been tried and it introduced a lot of bugs. Any implementation will require a lot of manual testing and correcting ***

# Feature: Unify attempts

## Status

**Proposed implementation specification**

## 1. Objective and product intent

Hubaroo currently has two independently implemented concepts for a learner answering questions:

- a Kangourou session `Attempt`;
- a parcours jump `JumpAttempt`.

They represent the same product capability: a learner starts an assigned evaluation, answers a fixed question set under a time limit, submits (or is auto-submitted), receives a score, and can be monitored by a teacher. Their separate implementations have caused duplicated learner and teacher interfaces, stores, APIs, real-time events, timing logic, recovery behavior, and test coverage.

This feature replaces both implementations with one **unified attempt domain** for all newly created attempts. It must provide one learner attempt runner, one learner results experience, one attempt API and store, and one teacher monitoring/reporting experience. Sessions and jumps remain distinct parent products: they retain their own creation flows, membership/access rules, question-selection rules, expiry rules, and source-specific scoring rules.

### Why

- Remove duplicate code and prevent future behavior drift.
- Make active-attempt behavior consistent and reliable across sessions and jumps.
- Give teachers one coherent way to monitor and review learner attempts.
- Preserve source-specific pedagogical rules without maintaining two technical attempt systems.

## 2. Confirmed scope and decisions

| Decision | Requirement |
| --- | --- |
| Domain scope | Unify learner UI, teacher UI, Pinia/client store, backend API, persistence model, events, and tests. |
| Parent domains | Kangourou sessions and parcours jumps remain separate parent entities. They are referenced by a generic attempt; they are not merged. |
| Canonical persistence | Create a new neutral attempts table and canonical `Attempt` model. Do not extend the legacy session `attempts` table or the `jump_user` pivot. |
| New-attempt rollout | After release, all newly created session and jump attempts use the canonical model and API. |
| Historic records | Legacy session attempts and jump attempts are permanently deleted as part of the deployment. They are not migrated and must not remain accessible through learner, teacher, or API flows. |
| Active legacy records | All active legacy attempts are invalidated during deployment. Learners must start again if the parent session/jump remains active. |
| Learner results | Unify results and correction/review UI in this release. |
| Teacher experience | Unify teacher monitoring and reporting in this release. Provide both a filterable attempt table/detail view and a student-progress matrix. |
| Session question count | A new session attempt always contains exactly 26 questions. The runner must still use the persisted question-list length, never hard-coded navigation or modal limits. |
| Timer authority | The server is authoritative. It stores and validates an absolute attempt deadline; clients only display a countdown calculated from that deadline. |
| Recovery | Add recovery to both sources. Recovery is limited to the same browser through a secure browser-held ownership token; cross-device recovery is out of scope. |
| Blur security | Use one identical blur/tab-switch warning and auto-submit policy for sessions and jumps. |

## 3. Non-goals

- Changing how a Kangourou session or parcours jump is authored.
- Changing session privacy, jump enrolment, or teacher/class membership rules.
- Changing the question-selection algorithm for jumps.
- Changing the Kangourou scoring algorithm or jump mastery/scoring algorithm, except to route both through the unified attempt lifecycle.
- Recovering attempts across devices or browsers.
- Preserving or migrating legacy attempts, results, reports, APIs, route names, or browser storage.
- Redesigning parent pages beyond replacing their duplicate attempt-monitoring widgets with the unified teacher experience.

## 4. User journeys

### 4.1 Learner: start or resume a session attempt

1. A learner joins an active session using the existing session access rules (including guest/private-session rules).
2. The backend creates one canonical attempt linked to the session, snapshots its 26 questions, sets the deadline, generates a browser ownership token, and returns the normalized attempt payload.
3. The client saves the ownership token only in browser storage and opens the shared attempt runner.
4. If the same browser returns to the active attempt, it resumes after presenting the token. A different browser cannot resume it.
5. A duplicate start request returns the existing active or finished attempt outcome; it must never create a second attempt for the same learner/session.

### 4.2 Learner: start or resume a jump attempt

1. An authenticated enrolled learner opens an active jump.
2. The backend selects questions using the existing jump-selection rules, snapshots them in the canonical attempt, sets the deadline, generates a browser ownership token, and returns the normalized attempt payload.
3. The shared runner behaves identically to a session attempt.
4. The same-browser recovery rule applies. Authentication and division membership remain mandatory for every jump attempt request.

### 4.3 Learner: answer and submit

1. The runner displays all persisted questions, their answer state, current question, timer, and source-appropriate labels.
2. The learner can navigate by previous/next controls, question navigator, keyboard shortcuts, and direct question jump.
3. Every answer is saved through the unified API. The UI may update optimistically, but must visibly recover from a rejected or failed save.
4. Submission, deadline expiry, configured blur timeout, confirmed route exit, and page-close best-effort submission all invoke the same idempotent server-side finalization operation.
5. The server accepts finalization once, records the termination reason, grades the snapshot according to its source, publishes an update, and returns the normalized finished attempt.
6. The learner is redirected to the common results view.

### 4.4 Teacher: monitor and review

1. A teacher with access to the parent session or jump opens the common teacher attempts area.
2. The teacher can filter a unified table by source, parent, course/division, learner, status, termination reason, and date.
3. The teacher can open an attempt detail view containing learner identity, source context, timing, termination, answers, score, and correction visibility state.
4. The teacher can use a student-progress matrix that displays relevant session/jump attempts without duplicating source-specific layouts.
5. Live changes (created, answered where currently supported, submitted, expired, or rejoin state changes) update the applicable unified UI through one event contract.

## 5. Canonical attempt domain

### 5.1 Persistence model

Create a new canonical `attempts` table and an `Attempt` model. Legacy tables must be removed only after the new path is live and the planned destructive migration has been executed.

Each attempt must include at least:

| Field | Requirement |
| --- | --- |
| `id` | Primary key. |
| `source_type` | Restricted enum: `session` or `jump`. |
| `source_id` | ID of the parent `KangourouSession` or `Jump`. |
| `user_id` | Nullable only for permitted guest session attempts; required for jumps. |
| `display_name` | Snapshot of the learner name/class label used in teacher views. |
| `owner_token_hash` | Hash of the browser recovery token; never return or log the stored hash. |
| `questions` | Immutable ordered snapshot of the delivered questions and answer metadata. |
| `answers` | Answer state keyed by stable question snapshot ID, not a mutable visual position. |
| `status` | `in_progress` or `finished`. |
| `started_at` | Server timestamp at creation. |
| `deadline_at` | Server-calculated absolute UTC deadline. |
| `submitted_at` | Nullable server timestamp when finalized. |
| `score` | Nullable/typed score after grading. |
| `termination` | `none`, `submitted`, `timeout`, `blurred`, or `abandoned`. |
| `source_metadata` | Minimal immutable source context required for results and teacher reporting (for example session name, jump/course/division labels, correction policy). |
| timestamps | Standard creation/update timestamps. |

Required database constraints and indexes:

- a check/enum constraint for allowed source types, statuses, and termination values;
- one active-or-finished attempt per learner/source parent where the source access policy permits only one attempt;
- indexes for `source_type + source_id`, `user_id`, `status`, `deadline_at`, and teacher-report filters;
- an explicit, documented strategy for guest uniqueness (for example a source-specific guest identity hash) that does not rely on a mutable display name;
- a unique stable identifier for every snapshot question and every answer entry.

### 5.2 Question snapshot contract

The persisted question snapshot is the source of truth for the attempt. Parent paper/question records may change after an attempt starts without changing its delivered questions, accepted answers, image reference, tier/difficulty, or grading result.

Each question snapshot must contain at least:

- stable snapshot question ID;
- original question ID;
- rendered image/reference;
- tier/difficulty and answer type;
- allowed answer format/options;
- correct answer or server-only grading data, never exposed while correction must be hidden;
- display order;
- source-specific scoring metadata required to grade the attempt deterministically.

Session creation must snapshot exactly 26 paper questions. Jump creation must snapshot the dynamic result returned by `JumpQuestionSelector`. The client must rely on the snapshot length and answer type, not on `26`, `25`, `24`, or a “last two numeric questions” convention.

### 5.3 Source-specific rules preserved behind one lifecycle

| Concern | Session rule | Jump rule | Unified implementation |
| --- | --- | --- | --- |
| Access | Existing guest/private-session rules | Authenticated, enrolled division member | Source authorization strategy before any attempt operation. |
| Questions | Fixed 26-question paper | Dynamic selection based on learner mastery | Snapshot creator strategy. |
| Duration | Session preference duration plus allowed accommodation | Jump duration plus allowed accommodation | Server calculates and persists `deadline_at`. |
| Grading | Existing Kangourou point/penalty/tier rules | Existing jump difficulty/mastery rules | Source grading strategy invoked by one finalizer. |
| Corrections | Existing immediate/delayed correction policy | Existing jump expiry/correction policy | Common results payload with source policy flags. |
| Teacher context | Session and participant context | Course/division/jump context | Shared presentation contract with source metadata. |

No source-specific `if` chains may be embedded throughout the runner, results component, or teacher components. Differences must be encapsulated by explicit source strategies/adapters and represented in the normalized payload.

## 6. Unified backend API and events

### 6.1 API requirements

Replace source-specific attempt endpoints with a single versioned/clearly named API surface. Exact paths may follow current route conventions, but the API must use one response/request schema and one authorization layer.

Required operations:

| Operation | Behavior |
| --- | --- |
| Start | Creates or returns the learner’s canonical attempt for a session or jump. It returns `409` with a normalized existing-attempt outcome when duplicate creation is attempted. |
| Load/resume | Requires normal source authorization plus the same-browser owner token for an in-progress learner attempt. Teacher access is authorized separately and must not require the learner token. |
| Save answer | Validates attempt state, deadline, question snapshot ID, answer format, ownership, and source authorization. It must reject answers after finalization or expiry. |
| Finalize | Idempotently finalizes the attempt. Repeated requests return the already-finalized canonical result rather than a conflicting second finalization. |
| Results | Returns only information allowed by the correction policy. Never expose correct answers before policy allows it. |
| Recovery | Finds the same-browser active attempt using the locally held owner token. It must not allow recovery from a short public code or a user-supplied attempt ID alone. |
| Teacher list/detail/matrix | Returns only attempts for parents/classes the teacher is authorized to view. Filters and pagination are server-side. |

`deadline_at` is authoritative. The server must reject answer saves/finalization after this instant and must finalize the attempt as `timeout` when appropriate. A client-provided remaining timer is display/telemetry only and must never extend an attempt.

### 6.2 Concurrency, reliability, and security

- Finalization must be transactional and idempotent; grading, status update, score persistence, and emitted event must describe the same final state.
- Prevent an answer-save request in flight from overwriting a just-finalized attempt.
- Validate answer identifiers against the stored snapshot, not an untrusted numeric index.
- Use the server clock only for timing. Client clock changes, background-tab throttling, refreshes, and reconnects must not extend time.
- Hash ownership tokens at rest; return the raw token only at creation/approved recovery; use secure browser storage appropriate to the existing SPA authentication model; clear it on finalization.
- Do not transmit complete client-side question lists on submit. The server already owns the immutable snapshot and should accept only unsaved answer mutations needed for an atomic finalization.
- Maintain throttling for start, answer, recovery, and finalization operations. Use an exponential retry/clear error state for transient answer-save failures.
- Ensure all API error responses have one documented normalized structure usable by the store and UI.

### 6.3 Real-time events

Replace `AttemptUpdated` and `JumpAttemptUpdated` with one normalized event contract. The event must carry the canonical attempt ID, source type/parent reference, state transition, timestamp, and only the data required by its audience.

- Learner channels must never disclose another learner’s state or hidden corrections.
- Teacher channels must be authorized by source parent/class access.
- Session/jump event channel names may remain distinct internally if authorization requires it, but consumers must receive the same payload shape.

## 7. Unified frontend architecture

### 7.1 Components and state

Replace the duplicated learner and results views with:

- `AttemptRunnerView`: common route-level loader/recovery orchestration;
- `AttemptRunner`: question navigation, answer controls, timer, modal flows, keyboard handling, blur guard, page-exit behavior, and state rendering;
- `AttemptResultsView`: score, answer review, correction visibility, source context, and relevant next action;
- common teacher attempts area: filterable table/detail view and student-progress matrix;
- one Pinia attempt store that manages the normalized API contract and owns no source-specific endpoint logic.

Source adapters may exist only at boundaries for session/jump parent context, labels, start inputs, and source-specific navigation after results. They must not duplicate runner, results, or teacher presentation logic.

### 7.2 Required learner behavior

- Use `questions.length` in all navigation, question-jump validation, answered counts, progress display, and keyboard bounds.
- Determine answer input from the snapshot’s `answer_type`/metadata. Numeric questions can appear anywhere in the question list.
- On screen-reader, keyboard, desktop, and mobile layouts, focus must move predictably when opening/closing the keypad, question jump modal, submit modal, and blur warning.
- If an answer save fails, restore the confirmed server state or visibly mark it as unsaved and provide a retry path. Never silently lose an answer.
- Display the timer consistently. Hiding the normal countdown must not hide it during the final warning threshold.
- The shared blur policy is: when an active attempt loses app focus/visibility, show one countdown warning; cancel it on return; automatically finalize as `blurred` if the configured grace period elapses. The grace period and enablement must be server-provided configuration, not duplicated hard-coded values.
- Route leave and page unload must use the unified finalizer. Browser unload is best effort only; server deadline enforcement remains the fallback.

## 8. Unified teacher experience

### 8.1 Attempt table and detail

The teacher area must provide a single paginated table containing attempts from sessions and jumps. Required fields include learner, source type, source/parent label, class/division where applicable, status, score when available, deadline/submission time, termination reason, and correction visibility state.

Required filters: date range, source type, session/jump parent, course/division, learner, status, termination, and score availability. Filter/query state must be shareable in the URL when consistent with existing application conventions.

The detail view must show the question/answer review according to the same correction policy used by the learner results page, and must clearly identify the source and scoring context.

### 8.2 Student-progress matrix

Provide one teacher-controlled matrix view for learner progress across relevant session/jump attempts. It must:

- group/filter by the selected course, division, session, or jump context;
- avoid an N+1 request/query pattern;
- show loading, empty, inaccessible, and partial-data states;
- link each cell to the common attempt detail;
- use a documented score display where scoring scales differ, rather than implying session and jump scores are directly comparable.

## 9. Lifecycle, error states, and edge cases

The implementation must define and test the following outcomes:

| Situation | Required outcome |
| --- | --- |
| Parent is draft, inactive, expired, or deleted | Starting is rejected with a clear message; existing active attempt is finalized/redirected according to source expiry policy. |
| Attempt is already finished | No answer mutations; results view is shown. |
| Deadline elapses in an open tab | Server finalizes once as `timeout`; client receives/loads final state and redirects to results. |
| Deadline elapsed while offline | The next API response rejects mutation and returns/causes the finished `timeout` state. |
| Two tabs in same browser | Only the browser-token owner can resume; concurrent request handling must prevent conflicting answers/finalizations. Document whether a second tab is blocked or read-only. |
| Different browser/device | Cannot resume; show a clear same-browser recovery message. |
| Duplicate start | Return the existing attempt outcome; never create a second one. |
| Refresh | Same browser resumes from owner token and recalculates the display countdown from `deadline_at`. |
| Answer request races submit | Final server state wins; no answer is accepted after finalization. |
| Repeated submit/beacon/event | Idempotently return the same final attempt and do not grade or update mastery twice. |
| Network failure | Preserve unsaved state visibly, retry safely, and prevent misleading “saved” UI. |
| Blur/focus events duplicate | One warning/countdown only; never create multiple intervals or multiple finalization requests. |
| Correction is delayed/hidden | Results, learner review, events, and teacher views omit correct answers until policy permits them. |
| Question image unavailable | Render an accessible error state without exposing answers or breaking navigation. |
| Zero or malformed question list | Do not create an attempt; return a source configuration error and record it for operators. |
| Session-specific fixed count is not 26 | Reject attempt creation as an invalid session configuration. |
| Jump question selection returns fewer/more questions | Persist and render the returned valid list; enforce its configured source constraints server-side. |

## 10. Data deletion and deployment plan

This feature deliberately removes all legacy attempt history. This is destructive and must be planned as a deployment requirement, not an incidental code cleanup.

1. **Pre-deployment communication:** notify affected users that all legacy session/jump attempt history and active attempts will be removed at the deployment window.
2. **Backup:** take and verify a database backup before any destructive migration. The backup is operational-only and is not a supported application-access path after release.
3. **Maintenance/transaction boundary:** stop new legacy attempt creation before deletion to prevent records being created between export/check and destructive migration.
4. **Invalidate:** mark/finalize or remove all active legacy attempts so no legacy runner can resume after release.
5. **Delete:** remove legacy attempt records, related rejoin demands, legacy browser recovery keys, legacy reports dependent on those records, and obsolete storage/API routes. Delete `jump_user` attempt data only after confirming it contains no unrelated relationship data.
6. **Schema rollout:** create the canonical table, constraints, indexes, models, factories, policies, API endpoints, events, and frontend route bindings.
7. **Post-deployment verification:** confirm a new session attempt and a new jump attempt can be started, resumed in the same browser, answered, auto-submitted, finalized, graded, viewed by the learner, and monitored by the authorized teacher.
8. **Rollback plan:** define whether rollback restores the verified backup or keeps the application unavailable. A code-only rollback after destructive deletion is insufficient.

## 11. Acceptance criteria

### Functional

- [ ] New session and jump attempts are created only in the canonical persistence model.
- [ ] Both sources use the same normalized API, Pinia store, learner runner, results view, and teacher attempts UI.
- [ ] Session attempts always snapshot 26 questions; jump attempts snapshot the selector’s question list.
- [ ] The runner has no hard-coded 26-question navigation, answered-count, direct-jump, or numeric-question-position assumptions.
- [ ] Each source retains its existing access, selection, scoring, correction, and parent-expiry rules through documented source strategies.
- [ ] The server persists and enforces an absolute deadline for every active attempt.
- [ ] Same-browser recovery works for session and jump attempts; another browser/device cannot recover an active attempt.
- [ ] Identical blur warning and auto-finalization behavior applies to both sources.
- [ ] A finalization request is idempotent and grading/mastery side effects happen once.
- [ ] Common results correctly respect correction visibility for both sources.
- [ ] Authorized teachers can use both the common table/detail view and the common student-progress matrix.
- [ ] Legacy attempts, APIs, UI routes, stores, events, recovery keys, and records are removed after the planned destructive rollout.

### Quality and performance

- [ ] Teacher table and matrix endpoints paginate/filter on the server and avoid N+1 model/query behavior.
- [ ] Answer operations validate only snapshot IDs/answer formats and do not repeatedly load unnecessary parent data.
- [ ] Events and API responses omit hidden corrections and unrelated learner data.
- [ ] The learner flow works at supported mobile widths and with keyboard-only navigation.
- [ ] All error, empty, loading, expired, unauthorized, offline, and retry states have explicit UI behavior.

### Test requirements

- [ ] Feature tests cover start, duplicate start, load/resume, authorization, answer mutation, deadline enforcement, explicit submission, timeout, blur, abandonment, idempotency, correction masking, and deletion of legacy access paths for both sources.
- [ ] Tests prove session guest/private access and jump authenticated/enrolled access continue to differ correctly.
- [ ] Tests cover same-browser token recovery and rejection from another browser/tokenless request.
- [ ] Tests cover 26-question sessions, variable jump counts, numeric questions at arbitrary positions, question snapshot immutability, and malformed source configuration.
- [ ] Tests cover teacher table filters, attempt detail authorization, matrix authorization, and no hidden-correction leakage.
- [ ] Browser/component tests cover navigation, direct question jump bounds, timer display, failed answer save/retry, submit confirmation, blur behavior, and results rendering.
- [ ] Regression tests confirm scoring and mastery side effects are applied exactly once.

## 12. Implementation sequence

1. Document the normalized attempt API, ownership-token protocol, source strategy interfaces, event payload, and destructive migration plan.
2. Add the canonical schema/model/factory/policies and source strategy services with focused tests.
3. Implement unified start/load/save/finalize/results APIs, server-authoritative deadlines, idempotency, and normalized events; test both source strategies.
4. Build the shared Pinia store and learner runner/results components against fixture payloads; remove fixed-index assumptions.
5. Wire session and jump parent flows to the common runner and results routes.
6. Build the unified teacher table/detail and matrix, then replace source-specific teacher monitoring components.
7. Run full regression coverage for sessions, jumps, grading, mastery, correction visibility, rejoin/recovery, and real-time updates.
8. Execute the communicated destructive deployment, clear legacy browser storage, remove the legacy code paths, and perform post-deployment smoke tests.

## 13. Removal inventory

After the unified implementation passes verification, delete rather than preserve compatibility shims for:

- the separate `JumpAttempt` persistence model and its `jump_user` attempt storage, subject to confirmation that no non-attempt relationship data remains there;
- legacy session and jump attempt records, rejoin-demand records, and recovery storage;
- separate attempt and jump-attempt API routes/controllers/requests/events/stores;
- `jump-attempt-view.vue`, `attempt-view.vue`, `jump-results-view.vue`, and `results-view.vue` after replacement by their shared equivalents;
- duplicated teacher attempt panels/tables/matrices replaced by the common teacher area;
- source-specific route names and browser storage keys used only by legacy attempts;
- tests that exercise legacy behavior, replacing them with canonical equivalent coverage.

Do not delete session/jump parent models, parent authoring flows, question selection, source grading algorithms, or source-level authorization logic.
