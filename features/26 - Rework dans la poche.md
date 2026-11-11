# Rework: "Dans la poche" per class

**Status: Locked. Ready to implement.**

The review below is the product critique of the original note. Every row in section 0 is a product-owner decision and is normative. Sections 1–12 are the implementation contract.

This feature replaces the home pocket for students and adds one for teachers. It does not redesign the class page, the attempt pages, or the left-hand "Tentatives récentes" panel.

---

## Review

### Strengths & Core Takeaways

The objective is clear: today's pocket dumps every class into four shared columns, and the replacement is one button per non-archived class that opens onto that class's live work, current parcours, and a short history. The urgency order (open jump, open session, grade from the last day, review questions) and "one class open at a time" are enough to design the interaction.

### Ambiguities & Open Questions

The original note left the items below unspecified. They are closed in section 0 and written into sections 5–7. Nothing in this list is still awaiting a decision.

- Who it is for: students and teachers, with different panel contents.
- Invitations stay above the class buttons.
- Any class can be opened or closed. A newly opened jump or session switches the panel. A new grade or question does not.
- "Open" for a student includes an in-progress attempt ("Reprendre") and excludes a finished one.
- Active parcours matches the class page (highest jump id, sum of graded scores, last 3 graded jumps).
- Recent student sessions are this student's finished, class-linked attempts whose score is already visible.
- "A day" is 24 hours and "6 weeks" is 42 days, on the server clock.
- One wrapping row of buttons; the panel is the next full-width line. The two ASCII drawings are the same component.
- Live updates refetch `GET /api/my/pocket` when the division channel says something changed. Scores are never broadcast on that shared channel.

Limits that remain, on purpose:

- Priority 4 has no age. A class with an old public question (student) or an old unpublished suggestion (teacher) may be the one that starts open. Closing it stays closed for the rest of the visit.
- A later rename of an attempt can move a student's "graded just now" window, because there is no `graded_at` column.
- While a jump sits in `expiring`, it is neither an open jump nor a fresh grade. The pocket catches up when the job marks it `expired`.

### Additions & Suggestions

Folded into the contract below:

- One `GET /api/my/pocket` payload. The home page currently fans out one suggested-question request per class and derives jumps and sessions in the browser.
- Open work includes an in-progress attempt, with the label "Reprendre". A finished attempt is history. "Demander à reprendre" stays in the alert center.
- Archived parcours are ignored, same as the student class page.
- A jump past `expiration` is not open, even if the minute job has not flipped `status` yet (`Jump::isClosed()`).
- A delayed-correction session is not "done" until the session is expired and the score exists. An immediate-correction session can be "done" while the session is still active for classmates.
- Fresh grades that belong to another parcours than the active one still need a visible row, otherwise the class opens onto nothing that explains why.
- Realtime is "refetch my pocket", never a score pushed on the shared division channel.
- The jump correction modal is extracted and shared. It is not copied.
- The shell counter stops counting every review question.
- Empty, loading, and failed pocket states. `prefers-reduced-motion` disables the pulse.

### Recommended Deletions / Simplifications

- No second layout for "button on the left" versus "button on the right". One wrapping row, panel on the next line.
- The teacher pocket does not embed the gradebook, class averages, the suggestion modal, or the session-analysis modal. It links to the screens that already have them.
- No chart, no mastery number, no rejoin button, no "Autres sessions" bucket.
- No `graded_at` migration. Use the timestamps in section 5 and accept the rename limitation documented there.
- No per-jump or per-session Echo subscription from the home page. One division event means "reload".
- Review questions are not live in this feature.
- No new question-detail endpoint. The existing attempt `show` endpoint already feeds the modal.
- Do not keep the four-column layout and also add class buttons.

---

## 0. Decisions

| # | Decision | Answer | Status |
| --- | --- | --- | --- |
| 1 | Audience | Students and teachers. A user with the Teacher role gets the teacher pocket only, including when they also belong to a class as a learner. Same branch as `DivisionController::myIndex`. | Locked |
| 1b | Teacher panel | Same five slots as the student, class-level, no student scores and no class averages. Open jumps and the parcours recap link to the course page. An open session the teacher authored links to the session page; every other session links to the class page. Recent sessions are the last 3 linked sessions that expired in the last 42 days. "Questions" are unpublished suggestions, as a count per parcours. Priority 3 is a jump or session that ended in the last 24 hours. | Locked |
| 2 | Invitations | Keep them, as a block above the class buttons, inside the pocket, for whichever role is viewing. Accept and decline stay as they are. | Locked |
| 3 | Opening and closing | Any class can be opened by a click, including a quiet one, and the open class can be closed. On first load, auto-open the single highest-priority class. While the page is open, a jump or session that **newly** becomes open switches the panel to that class. A new grade or a new question never switches the panel. A panel the user closed stays closed until the next visit, unless a jump or session opens. | Locked |
| 4 | Active parcours (student) | The non-archived parcours that contains the jump with the highest id among jumps of status `active` or `expired`. Score is the sum of that student's graded jump scores on that parcours (class page `activeCourseTotal`). The recap is the last 3 **graded** jumps of that parcours. Each opens the existing correction modal. | Locked |
| 5 | Recent sessions (student) | Up to 3 sessions **linked to this class** that **this student** finished, score already visible, revealed in the last 42 days. A public session that is not linked to one of the student's classes stays out of the pocket. It remains in "Tentatives récentes". | Locked |

Every row above is Locked. Implement sections 1–12 as written. Section 3.1 is binding: this feature adds a pocket read model and one new broadcast. It does not change grading, force-stop, or the existing event payloads.

---

## 1. Objective

A signed-in student or teacher opens the home page and sees "Dans la poche" grouped by class. Each class is one outlined button. At most one button is expanded.

For a student, the expanded panel shows, for that class only:

1. Jumps the student can start or resume now.
2. Kangourou sessions the student can join or resume now.
3. The current parcours: its total points, and the last three graded jumps. A jump opens the same correction modal as the class page.
4. Up to three sessions from the last six weeks whose score is visible.
5. Public review questions for that class.

For a teacher, the same five slots are class-level launchers. They do not show a personal score or another screen's gradebook. Section 5.9 defines them.

Archived classes are absent. "Class" means a non-archived division the viewer teaches (teacher) or belongs to (student).

The pocket stays current while the home page is open: a jump or session opening or closing, and a jump or session just ending or just graded, update the panel without a manual reload.

## 2. Why this change is needed

`home-view.vue` renders one strip with up to four columns shared across every class: invitations, active jumps, active sessions, public suggested questions. The student cannot tell which class an item belongs to except via a small caption. A class with no live item is invisible, so recent work and the current parcours are not on the home page at all.

The home page also builds that strip from several requests (`/api/my/divisions`, `/api/my/attempts`, and one `/api/divisions/{id}/public-suggested-questions` per class) and listens only to `SessionOpenedForDivision`, `SessionClosedForDivision`, `JumpActivated`, and `JumpReopened`. A jump that expires and a session that expires do not use those events, so the strip goes stale until the next full load.

## 3. Out of scope

- Guests, and removing the teacher actions already on the home page (create session, create jump). Those stay under the pocket.
- The class page, course page, attempt pages, results pages, and alert center. The only class-page edit is extracting the existing student jump modal so both screens use it.
- "Demander à reprendre", for jumps and for sessions.
- The slide-in "Tentatives récentes" panel. It keeps cross-class history, including sessions that are not linked to a class.
- Making review questions, new invitations, removed students, or newly archived classes update live. They appear on the next load of the pocket.
- A `graded_at` column, a new results endpoint, or a change to how scores are calculated.
- Editing `Functionnalities.md`.
- Persisting which class is open, or whether the shell is collapsed, across reloads.
- Changing a session code, renaming an attempt, or pushing review questions live.

## 3.1 Compatibility

Existing behaviour stays. The pocket is a new read model plus `PocketChanged`.

- Do not change `JumpExpired` or `SessionExpired`: same channels, same payloads (`jump_id`, `session_id`), same moments they fire today. The attempt page already force-stops on `JumpExpired` and on its local expiration deadline. The course and class pages refetch on `JumpExpired` expecting grades. The bulk `active` → `expiring` update stays silent, as it is now. Notifying the pocket at that moment is `PocketChanged` only.
- Do not grade, score, change attempt status, dispatch `UpdateMasteryAndDifficulty`, or dispatch `AnalyseJump` from pocket code. `ExpireJumps` mastery is not idempotent. The session expire-now path broadcasts `SessionExpired` and leaves grading to `ExpireKangourouSessions`. Pocket code only broadcasts.
- Do not reimplement `Jump::isClosed()`, `KangourouSession::isActive()`, `isExpired()`, or `shouldDelayGrading()`. Call them. A missing `preferences.correction` means delayed, which is what `shouldDelayGrading()` already does.
- `isClosed()` is false for a **draft**. Open jumps require `status === 'active'` and `! isClosed()`.
- Home is the only new Echo subscriber. Remove the division listeners in `home-view.vue` that patch `divisionStore`. `pocketStore` owns the home subscriptions and leaves them on home unmount. Do not leave those channels from anywhere else, and do not keep the store subscribed after navigation: `division-details-view.vue` joins the same division channels and `Echo.leave` drops every listener on that channel.
- A refetch replaces pocket data only. It does not reset the collapsed shell, the open class (except the rule in section 6.1), the invite form, the claim modal, or an open jump modal.

## 4. Current-state notes

Re-check these before editing. They were true when this spec was written.

- Pocket UI and data wiring live in `resources/js/components/views/home-view.vue`. The shell is a collapsible banner titled "Dans la poche". `showHighlights` starts `true`. The counter is invites + active jumps + public questions + active sessions.
- Students see the pocket when they have an invite, an active jump, a public question, or an active session. Teachers never see it: `activeSessions` returns `[]` for `is_teacher`, and the other columns are student-only. The teacher half of the `v-if` is dead.
- An "active jump" today is `division.active_jumps`: status `active`, and the student has **no** attempt row at all. An in-progress attempt disappears.
- An "active session" today is a session on the division with status `active` whose id is not in the student's attempt list. `expires_at` is not checked. An in-progress attempt disappears.
- Student parcours data on the class page (`CourseController::index` for a non-teacher): archived parcours excluded; jumps limited to `active` and `expired`; the default parcours is the one containing the highest jump id; the big score is the sum of `attempts[0].score` on expired jumps; the modal is inline in `division-details-view.vue` (`showJumpDetailModal`) and loads `GET /api/jump-attempts/{attempt}`.
- That modal's title number is the 1-based index of the jump among the parcours's **expired** jumps ordered by id. It is not `Jump::rank()` and not the `rank` value `CourseController` writes (that one counts `active` + `expired`).
- `Jump::isClosed()` is true when status is `expiring` or `expired`, or when `expiration` is already past. It is **false for a draft**. `isActive()` is status `active` only. An open jump is `active` and not closed.
- `KangourouSession::isActive()` is status `active` **and** `expires_at` in the future. `shouldDelayGrading()` is true when correction is `delayed` and the session is not expired. Immediate correction stores a score at submit time.
- `ExpireJumps` moves due jumps to `expiring` with a bulk update (no broadcast), then grades, sets `expired`, broadcasts `JumpExpired` on the **public** channel `jump.{id}`, and dispatches `AnalyseJump`.
- `ExpireKangourouSessions` sets the session `expired`, grades attempts that need it, and broadcasts `SessionExpired` on the **private** channel `session.{id}`. It does not notify `division.{id}`.
- Channel auth: a student may join `division.{id}` when they are a member. They may join `session.{id}` only when they already have an attempt (or they are the author). They may join the private `jump.{id}` channel as a member, but `JumpExpired` is broadcast on the public channel, not the private one.
- Public suggested questions: `SuggestedQuestionController::publicForDivision`, `is_public`, member-only. The home overlay shows the image and the star level. It does not reveal the answer. The payload still includes `correct_answer` today; the new pocket payload must not.
- Home already subscribes to `division.{id}` in `onMounted` and leaves those channels in `onUnmounted`.
- There is no frontend component test runner. Behaviour is locked with Pest feature tests. The UI is verified in the browser.

### 4.1 Files the agent should expect to touch

- `app/Http/Controllers/PocketController.php` — new invokable. Create with `php artisan make:controller`.
- `app/Events/PocketChanged.php` — new. Create with `php artisan make:event`.
- `routes/web.php` — `GET /api/my/pocket` inside the existing `auth` group, next to `divisions.myIndex`.
- `app/Jobs/ExpireJumps.php`
- `app/Jobs/ExpireKangourouSessions.php`
- `app/Http/Controllers/JumpController.php` — the branch that already broadcasts `JumpExpired`, and `destroy()` before the row is deleted.
- `app/Http/Controllers/KangourouSessionController.php` — the branch that already broadcasts `SessionExpired` because `expires_at` moved into the past, and `destroy()` before the row is deleted.
- `app/Http/Controllers/AttemptController.php` — `submit()`, only the immediate-correction score write, and only on the owner's user channel.
- `tests/Feature/PocketTest.php` — new. Create with `php artisan make:test --pest`.
- Existing expiry / session tests that assert the exact set of dispatched events, if they become incomplete once `PocketChanged` is dispatched. Update the assertion. Do not weaken it.
- `resources/js/stores/pocketStore.js` — new, same style as `divisionStore.js`.
- `resources/js/components/pocket/PocketPanel.vue` — new. One component for both roles; it branches on `viewer`.
- `resources/js/components/JumpAttemptDetailModal.vue` — extracted from the student modal in `division-details-view.vue`. Students only.
- `resources/js/components/views/division-details-view.vue` — use the extracted modal. Same title, date, score, and question list as today.
- `resources/js/components/views/home-view.vue` — render `PocketPanel` for students and teachers; stop deriving the old columns; keep claim-attempts, random question (students), create-session and create-jump (teachers), and the invite accept modal.

## 5. Data contract

`GET /api/my/pocket` returns only the authenticated user's data. Never another student's score, attempt, or name in a student payload. The teacher payload contains no per-student score and no attempt id.

The top-level `viewer` is `"student"` or `"teacher"`. A user with the Teacher role is `"teacher"` even if they also have a student membership. Everyone else authenticated is `"student"`.

Both viewers receive `invites` in the same shape as `DivisionController::myInvites` (pending invites for the user's email, with `division`), plus `classes`.

Class order is alphabetical by name, case-insensitive, then `id`. Order does not change when a jump opens. Urgency is the badge and which panel is open, not the sort.

Every non-archived class of that viewer is included even when every list is empty. Teacher classes come from `teacherDivisions()`. Student classes come from `divisions()` (membership).

Archived parcours are excluded from every list below.

Sections 5.1–5.8 are the student class object. Section 5.9 replaces those lists for `viewer: "teacher"`. Shared rules: alphabetical classes, `priority` / `signal_at` / `badge_count` meaning, and the 24-hour and 42-day windows.

### 5.1 `open_jumps`

Include a jump when all of the following hold:

- its parcours is not archived and belongs to this class;
- `status === 'active'` and `! $jump->isClosed()`;
- the student has no attempt, or the attempt status is `inProgress`.

A finished attempt does not qualify, including when the jump is still active and rejoin would be possible.

Order by jump id descending. Fields:

| Field | Rule |
| --- | --- |
| `id` | jump id |
| `course_title` | parcours title |
| `nb_questions` | |
| `time` | minutes |
| `expiration` | ISO 8601 or null. The client uses this to hide the card if the deadline passes before the next refetch |
| `action` | `start` when there is no attempt, `resume` when status is `inProgress` |

Do not send `question_list`.

### 5.2 `open_sessions`

Include a session when all of the following hold:

- it is linked to this class;
- `KangourouSession::isActive()` is true;
- the student has no attempt on it, or that attempt's status is `inProgress`.

A finished attempt does not qualify, even if classmates can still join.

The same session linked to two of the student's classes appears in both. That is intentional.

Order by session id descending. Fields:

| Field | Rule |
| --- | --- |
| `id` | |
| `code` | needed for the existing routes |
| `paper_title` | paper title, or `"Session Kangourou"` when there is no paper |
| `expires_at` | ISO 8601. The client hides the card once this time has passed |
| `action` | `join` when there is no attempt, `resume` when status is `inProgress` |
| `attempt_id` | the in-progress attempt id, or null |

### 5.3 `active_course`

Consider jumps with status `active` or `expired` on non-archived parcours of this class. The active parcours is the one that owns the jump with the highest id. This matches the class page's default tab.

When no such jump exists, `active_course` is null.

| Field | Rule |
| --- | --- |
| `id`, `title` | parcours |
| `score` | integer. Sum of this student's attempt scores on **expired** jumps of this parcours. A missing attempt or a null score counts as 0. Same number as the class page |
| `recent_jumps` | up to 3, see below |

`recent_jumps` are expired jumps on this parcours for which this student has a finished attempt and a non-null score. Take the three highest jump ids. Each item:

| Field | Rule |
| --- | --- |
| `id` | jump id |
| `attempt_id` | this student's attempt |
| `number` | 1-based index among this parcours's expired jumps ordered by id ascending. Same number the class-page modal shows today |
| `score` | attempt score |
| `created_at` | jump `created_at`, ISO 8601 |
| `fresh` | true when this item's revealed time is within the last 24 hours |

Do not send `question_list`. The modal loads it on click.

Revealed time for a jump is the attempt's `updated_at`. Known limitation: a later write to that attempt row (for example a teacher renaming it) moves this timestamp. Do not add a column to fix that in this feature.

### 5.4 `recent_sessions`

Sessions linked to this class, this student's finished attempt, score not null, score already visible:

- Immediate correction: visible as soon as the attempt is finished. Revealed time is `attempt.updated_at` (same rename limitation as jumps).
- Delayed correction: visible only once the session is expired (`status === 'expired'` or `expires_at` in the past). Revealed time is `session.expires_at`. A finished attempt on a delayed session that is still running is **not** in this list and its score is not sent.

Keep rows whose revealed time is `>= now()->subDays(42)`. 42 × 24 hours, not calendar weeks. Order by revealed time descending. Limit 3 **after** the fresh-grade flag in section 5.6 is computed, so a fourth session graded today still counts as a fresh grade even though it is not displayed.

A session that is in `open_sessions` is not in this list.

Fields: `id`, `attempt_id`, `code`, `paper_title`, `score`, `revealed_at`, `fresh`.

### 5.5 `open_questions`

Public suggested questions (`is_public`) whose parcours is in this class and not archived. Same order as `publicForDivision`: level ascending, then newest first.

Fields: `id`, `level`, `course_title`, `question: { id, image }`.

Do not send `correct_answer`. The overlay shows the image only.

### 5.6 Signals

Compute these on the server so the client does not reimplement the windows. Times use the application timezone. "Last 24 hours" means `>= now()->subDay()`.

| Field | Rule |
| --- | --- |
| `priority` | `1` if `open_jumps` is non-empty. Else `2` if `open_sessions` is non-empty. Else `3` if this student has any fresh graded jump **on this class** (any parcours, not only the active one) or any fresh `recent_sessions` candidate (including ones past the display limit of 3). Else `4` if `open_questions` is non-empty. Else `null` |
| `signal_at` | For priority 1, max `updated_at` of the open jumps. For 2, max `updated_at` of the open sessions. For 3, max revealed time of the fresh grades. For 4, max `updated_at` of the public questions. Else null |
| `badge_count` | `open_jumps.length + open_sessions.length`. Grades and questions do not increment it |
| `fresh_grades` | Fresh graded jumps that are **not** already in `active_course.recent_jumps`, plus nothing from sessions (those carry `fresh` on `recent_sessions`). Cap 3. Each item: `attempt_id`, `course_title`, `number`, `score`, `created_at`. `number` uses the same expired-jump index as the recap, on that jump's own parcours |

`fresh_grades` exists so a class that auto-opens because a jump was graded does not open onto an unrelated parcours with no trace of that grade.

### 5.7 Query shape

One request. No per-class HTTP fan-out. Both roles load invites with the same query as `myInvites` (pending, matching email, division eager-loaded). Do not skip invites for teachers.

Load non-archived classes in one query (`teacherDivisions()` or `divisions()`). Then, for those ids only:

- Open and recent jumps: constrain `courses.archived = false` and the jump statuses above. For a student, eager-load **that user's** attempt with a column list of `id`, `jump_id`, `user_id`, `score`, `status`, `updated_at`. Never select `question_list`.
- Teacher `attempts_count` and `graded_attempts_count` are `withCount` / a constrained count. Do not load attempt rows or student models.
- Sessions: `paper:id,title` only. For a student, eager-load that user's attempt with `id`, `kangourou_session_id`, `status`, `score`, `updated_at`. Never select `answers`.
- Questions: one grouped query. Students select `id`, `level`, `course_id`, and the question `id` and `image`. Teachers select `course_id` and a count of `is_public = false`. Do not select `correct_answer` for either role.

Choosing the active parcours and slicing the last three jumps in PHP is fine. Do not add a cache, a new table, or an index for this feature.

The existing public-suggested-questions endpoint keeps returning `correct_answer`. The pocket payload omits it. The random-question modal keeps working from the pocket list; it already refuses to reveal suggested answers.

### 5.8 Example

Shape, not a fixture:

```json
{
  "viewer": "student",
  "invites": [],
  "classes": [
    {
      "id": 1,
      "name": "3e B",
      "open_jumps": [
        {
          "id": 90,
          "course_title": "Fractions",
          "nb_questions": 8,
          "time": 12,
          "expiration": "2026-11-09T16:00:00+00:00",
          "action": "resume"
        }
      ],
      "open_sessions": [],
      "active_course": {
        "id": 4,
        "title": "Fractions",
        "score": 36,
        "recent_jumps": [
          {
            "id": 80,
            "attempt_id": 501,
            "number": 4,
            "score": 12,
            "created_at": "2026-11-08T10:00:00+00:00",
            "fresh": true
          }
        ]
      },
      "recent_sessions": [],
      "open_questions": [
        {
          "id": 15,
          "level": 2,
          "course_title": "Fractions",
          "question": { "id": 300, "image": "questions/300.png" }
        }
      ],
      "fresh_grades": [],
      "priority": 1,
      "signal_at": "2026-11-09T15:00:00+00:00",
      "badge_count": 1
    }
  ]
}
```

## 5.9 Teacher class object

No `action`, no `attempt_id`, no `score`, no class average, no question image, no `correct_answer`.

### Open jumps

A jump on a non-archived parcours of this class with `status === 'active'` and `! $jump->isClosed()`. Draft, expiring, and expired jumps are absent. Order by id descending.

Fields: `id`, `course_id`, `course_title`, `nb_questions`, `time`, `expiration`, `attempts_count` (all attempt rows, not a list of students).

### Open sessions

A session linked to this class for which `KangourouSession::isActive()` is true. Order by id descending.

Fields: `id`, `code`, `paper_title` (or `"Session Kangourou"`), `expires_at`, `attempts_count`, `is_author` (true when `author_id` is the viewer).

The same session linked to two of the teacher's classes appears in both.

### Active parcours

Same election as the student: non-archived parcours owning the highest jump id among jumps of status `active` or `expired`. Null when none exists.

No total score.

`recent_jumps`: up to 3 expired jumps on that parcours with the highest ids. A jump with zero graded attempts is still included; it is the class's jump, not one student's. Fields: `id`, `course_id`, `number` (same expired-jump index as the student modal), `created_at`, `graded_attempts_count` (finished attempts with a non-null score), `fresh`.

`fresh` is true when the jump's `updated_at` is within the last 24 hours and the jump status is `expired`. This is the moment `ExpireJumps` saved `expired`. A later unrelated write to the jump row can move it. Do not add a column.

There is a short gap while status is `expiring`: the jump is neither open nor fresh. The next `PocketChanged` (fired again at `expired`) fills the recap. Do not invent an "en correction" row.

### Recent sessions

Sessions **still linked** to this class that have ended, whether or not anyone attempted them. Ended means `status === 'expired'`, or `status === 'active'` and `expires_at` is already past (teacher expire-now, before the minute job flips status). Drafts are excluded: a draft is created with `expires_at` two hours out and must not become a "recent session" when that timestamp passes. `expires_at >= now()->subDays(42)`. Order by `expires_at` descending. Limit 3 after the fresh flag is computed, so a fourth expiry inside the last day still sets priority 3.

This list does not look at the teacher's own attempts. A session still in `open_sessions` is absent here.

Fields: `id`, `paper_title`, `expires_at`, `attempts_count`, `is_author`, `fresh` (`expires_at` within 24 hours).

### Open questions

Unpublished suggested questions (`is_public` false) on non-archived parcours of this class, grouped by parcours. This is the teacher's bulb backlog. Public questions are the student's review list and are not repeated here.

Fields per parcours: `course_id`, `course_title`, `unpublished_count`. Omit a parcours whose count is 0.

### Signals

| Field | Rule |
| --- | --- |
| `priority` | `1` open jumps. Else `2` open sessions. Else `3` any jump on this class with status `expired` and `updated_at` within 24 hours, or any linked session that counts as ended (section above) with `expires_at` within 24 hours. Else `4` any unpublished suggestion. Else `null` |
| `signal_at` | Priority 1: max open-jump `updated_at`. Priority 2: max open-session `updated_at`. Priority 3: max of those fresh jump `updated_at` values and fresh `expires_at` values. Priority 4: max `updated_at` of the unpublished suggestions. Else null |
| `badge_count` | `open_jumps.length + open_sessions.length` |
| `fresh_grades` | Expired jumps in the last 24 hours that are not already in `active_course.recent_jumps`, cap 3. Fields: `kind: "jump"`, `course_id`, `course_title`, `jump_id`, `number`. Sessions are not duplicated here; they carry `fresh` on `recent_sessions` |

### Teacher example

```json
{
  "viewer": "teacher",
  "invites": [],
  "classes": [
    {
      "id": 1,
      "name": "3e B",
      "open_jumps": [
        {
          "id": 90,
          "course_id": 4,
          "course_title": "Fractions",
          "nb_questions": 8,
          "time": 12,
          "expiration": "2026-11-09T16:00:00+00:00",
          "attempts_count": 12
        }
      ],
      "open_sessions": [],
      "active_course": {
        "id": 4,
        "title": "Fractions",
        "recent_jumps": [
          {
            "id": 80,
            "course_id": 4,
            "number": 4,
            "created_at": "2026-11-08T10:00:00+00:00",
            "graded_attempts_count": 18,
            "fresh": true
          }
        ]
      },
      "recent_sessions": [],
      "open_questions": [
        { "course_id": 4, "course_title": "Fractions", "unpublished_count": 6 }
      ],
      "fresh_grades": [],
      "priority": 1,
      "signal_at": "2026-11-09T15:00:00+00:00",
      "badge_count": 1
    }
  ]
}
```

The student example in section 5.8 also includes `"viewer": "student"`.

## 6. UI contract

A viewer with at least one class or one invite sees the existing collapsible shell: title "Dans la poche", Afficher / Masquer, chevron, counter. The shell starts expanded. Guests do not see it. A teacher with no class and no invite does not see it.

The counter is `invites.length + sum(badge_count)`. It is not the number of review questions.

Inside the shell, top to bottom:

1. Invitations, when there are any. Same cards and Accept / Refuse behaviour as today, including the first-name / last-name modal. After a successful accept or decline, refetch the pocket.
2. The class buttons.
3. The open panel, as a full-width row inserted immediately after the open button in a wrapping flex layout (`flex-basis: 100%` on the panel). Following buttons move below the panel. On a single column this places the panel directly under its button. Do not implement a separate left-button and right-button template.

The open button shares the panel's border colour and reads as the tab for that panel. A small arrow on the panel's top edge, aligned to the button centre, is part of the layout. One positioning calculation from the button's `offsetLeft` is enough. Do not keep two ASCII variants as two components.

Buttons show the class name. `aria-expanded` and `aria-controls` point at the panel. At most one panel is open. Zero is allowed after the user closes it.

Badge: when `badge_count > 0`, show the count on the button and pulse that badge with `motion-safe:animate-pulse`. Grades and questions do not badge and do not pulse.

### 6.1 Which class starts open

On the first successful fetch of a visit:

- Consider classes with a non-null `priority`. Lower number wins.
- Tie-break: later `signal_at`, then name, then id.
- Open that class.
- When every priority is null, open nothing.

Clicking a closed button opens it and closes the previous one. Clicking the open button closes it.

After the first fetch, refetches replace the data in place. They change which class is open only in this case: some class has an open jump id or an open session id that was not in the previous payload. Switch to the highest-priority such class (the rule above, restricted to classes that gained an open jump or session). A new grade, a new question, or a jump disappearing does not switch the panel and does not reopen a panel the user closed.

Record the previous open-jump ids and open-session ids per class in the store. Do not store this in `localStorage`. A full reload is a new visit and runs the first-fetch rule again.

### 6.2 Student panel contents

Omit a section when its list is empty, except the active parcours block, which is shown whenever `active_course` is not null.

When `active_course` is null and every list is empty, the panel body is one line: "Rien à signaler pour cette classe."

Order, single column:

1. **Sauts ouverts.** Card per jump. Course title, "Commencer le saut" or "Reprendre le saut", "{n} questions · {time} min". The whole card routes to `{ name: 'JumpAttempt', params: { jumpId } }`.
2. **Sessions ouvertes.** Card per session. `paper_title`. "Rejoindre" routes to `{ name: 'Session', params: { code } }`. "Reprendre la session" routes to `{ name: 'Attempt', params: { code, attemptId } }`.
3. **Fresh grades strip**, when `fresh_grades` is non-empty. Heading "Vient d'être noté". Each row: "{course_title} · Saut {number} · {score}". Click opens the jump modal. These rows are the grades that are not already in the recap.
4. **Active parcours.** Title, "{score} pts", then up to three chips "Saut {number}" with the date (`fr-FR`, same formatting as the class page) and the score. A `fresh` chip shows the text "Nouveau". Click opens the jump modal. When `recent_jumps` is empty, show "Aucun saut terminé pour l'instant." and still show the title and "0 pts" when the score is 0.
5. **Sessions récentes.** Each row: paper title, score, date from `revealed_at`, "Nouveau" when `fresh`. Routes to `{ name: 'Results', params: { code, attemptId } }`.
6. **Questions à revoir.** Same star row as today. Click opens the existing image overlay (move that overlay with the pocket, or keep it in the home view and call it from the pocket). Do not reveal the answer.
7. A text link **Voir la classe** to `{ name: 'DivisionDetails', params: { id } }`.

Reuse the current card colours: success border for jumps, primary for sessions, warning for questions. Dark mode uses the existing `dark:` tokens already on the pocket. Do not add a component library.

The random-question modal keeps its "Parmi les questions à revoir" toggle. Its pool is the flat list of `open_questions` across classes, deduped by id. It must not issue the old per-division requests.

### 6.3 Loading and failure

The rest of the home page renders while the pocket loads. On failure, the shell shows "Impossible de charger la poche." and a button "Réessayer" that calls fetch again. Do not throw away the invite modal, the claim modal, or the teacher actions.

While a card's `expiration` or `expires_at` is in the past, hide that card locally and do not count it in the badge. A 30-second tick is enough. Do not poll the API on that tick. The next refetch reconciles with the server. The tick does not switch the open class.

### 6.4 Jump modal

`JumpAttemptDetailModal` is the current **student** modal (`showJumpDetailModal`), moved out of `division-details-view.vue` without a visual change: title "Saut {number}", jump date, loading state, score, question images, per-question status. Leave the teacher observation modal in that file.

Props are the attempt id, the display number, and the date. The modal fetches `GET /api/jump-attempts/{attempt}` when opened. The class page passes the number it already computes (`jumpDetailNumber`). The pocket passes `number` from the payload.

Closing the modal does not change which class is open. Teachers never open this modal from the pocket.

### 6.5 Teacher panel contents

Same shell, same empty line, same omission of empty sections, same "Voir la classe" link. No points total, no "Nouveau" score, no correction modal, no question images.

1. **Sauts ouverts.** Course title, "{n} questions · {time} min · {attempts_count} tentative(s)". The card routes to `{ name: 'CourseDetails', params: { id: classId, courseId } }`.
2. **Sessions ouvertes.** Paper title and attempt count. When `is_author` is true, "Suivre la session" routes to `{ name: 'SessionDetails', params: { id } }`. Otherwise the card routes to `{ name: 'DivisionDetails', params: { id: classId } }`. `SessionDetails` is author-only; do not send a non-author there.
3. **Fresh strip**, heading "Vient de se terminer". Each jump row: "{course_title} · Saut {number}". Routes to that parcours's `CourseDetails`.
4. **Active parcours.** Title only. Up to three chips "Saut {number}" with the date and "{graded_attempts_count} tentative(s) notée(s)". "Nouveau" when `fresh`. Each chip routes to `CourseDetails`. When `recent_jumps` is empty: "Aucun saut terminé pour l'instant."
5. **Sessions récentes.** Paper title, date from `expires_at`, attempt count, "Nouveau" when `fresh`. Every row routes to `DivisionDetails`. The class page is where "Voir les performances" lives. Do not rebuild that analysis on the home page.
6. **Questions suggérées.** One row per parcours: "{course_title} · {unpublished_count} à publier". Routes to `CourseDetails`.

Pluralise from the count: 1 uses "tentative" and "tentative notée"; 0 and every other count use "tentatives" and "tentatives notées". "Copie" is not a word this app uses.

Create-session and create-jump stay on the home page below the pocket. The random-question control stays student-only.

## 7. Realtime contract

Subscribe from the home page only, with the same calls the app already uses:

- `Echo.private('division.' + id)` for each class in the latest payload. Channel auth already allows a teacher onto divisions they teach and a student onto divisions they belong to.
- `Echo.private('App.Models.User.' + authUserId)` once. This channel already exists in `routes/channels.php`.

Leave exactly those channels when the home page unmounts, and leave a division channel that disappeared from the payload. Debounce 300ms, then refetch. Reset the debounce on each event so a burst becomes one request. Apply section 6.1 after the refetch.

Listen for `SessionOpenedForDivision`, `SessionClosedForDivision`, `JumpActivated`, `JumpReopened`, and `PocketChanged`. Do not subscribe the home page to `jump.{id}` or `session.{id}`. Those listeners stay on the attempt, results, class, and course screens.

`PocketChanged` implements `ShouldBroadcast` (queued, same as `JumpActivated`), not `ShouldBroadcastNow`. Broadcast name `PocketChanged`. Payload is `{ "division_id": <id or null> }` only. No score, no attempt, no student id.

| Place | Channel | When |
| --- | --- | --- |
| `ExpireJumps` | each affected division, once | After the bulk `active` → `expiring` update, one event per distinct division. The bulk update itself stays a single query. Do not add `JumpExpired` here. Again after the grading loop, one event per distinct division whose jump was just saved `expired`. Keep the existing per-jump `JumpExpired` and `AnalyseJump` |
| `JumpController::update` | that jump's division | Only inside the branch that already broadcasts `JumpExpired` (`! wasClosed && fresh->isClosed()`). Not when a jump is activated or reopened (`JumpActivated` / `JumpReopened` already refetch the pocket). Not when only `nb_questions`, `time`, or `growth` change |
| `JumpController::destroy` | that jump's division | After resolving the division id, before delete |
| `ExpireKangourouSessions` | each linked division, once | After grading and `SessionExpired` for that session have run, coalesced to distinct divisions for the whole job. A refetch before `gradeAndSave` would miss the score |
| `KangourouSessionController::update` | each linked division | Same condition as today's `SessionExpired`: this request moved `expires_at` from a live session into the past. Delaying `expires_at` sends nothing. Do not grade here |
| `KangourouSessionController::destroy` | each linked division | Resolve division ids first, then delete |
| `AttemptController::submit` | `App.Models.User.{attempt.user_id}` only | `shouldDelayGrading()` is false and this request wrote a score. Classmates and the teacher do not refetch because of one student's submit. The teacher attempt count on an open session catches up on the next class-wide event or the next load. The session page already receives `AttemptUpdated` |

Do not dispatch `PocketChanged` from answer sync, from `AnalyseJump`, from toggling a suggestion public, or from a delayed submit (the expiry job covers that score).

Client-side hiding of a past `expiration` / `expires_at` covers the gap before the minute job. Division `PocketChanged` covers the status flip and grading. The user channel covers an immediate score while the home page is open in another tab; navigating back to home also refetches in `onMounted`.

## 8. Authorization

- Route middleware: the existing `auth` group. Guests receive 401.
- `viewer` is `teacher` exactly when `hasRole('Teacher')` is true. Student memberships of that user are ignored for the class list.
- A teacher receives only divisions they teach (`teacher_id`) that are not archived.
- A student receives only divisions where they are in `division_user` and `archived` is false.
- Student attempt ids in the payload are only that student's rows. The teacher payload has no attempt ids and no per-student scores.
- A class the viewer does not teach or belong to is omitted. Do not 404 individual classes.
- The pocket must not be the thing that authorizes the modal fetch or `SessionDetails`. `JumpAttemptController::show` and `KangourouSessionPolicy::view` already do. The teacher UI must not link a non-author to `SessionDetails`.

## 9. Acceptance criteria

Backend:

- Guest request is rejected.
- A teacher who is also attached as a student receives `viewer: "teacher"` and their taught classes, not the learner score payload.
- A teacher does not receive another teacher's class.
- Teacher open jump is present when the jump is not closed, with `attempts_count` and no student rows. It is absent for draft, expiring, expired, and past `expiration`.
- Teacher open session uses `isActive()`, includes `is_author`, and carries no student names.
- Teacher recent sessions are linked sessions that have ended (`expired`, or `active` with `expires_at` past), within 42 days, including one the teacher did not personally take, limited to 3, ordered by `expires_at`. A draft is absent even after its `expires_at`.
- Teacher `open_questions` counts unpublished suggestions only. A public suggestion does not increment the count. No question image and no answer is present.
- Teacher `priority` 3 follows jump `updated_at` / session `expires_at` from section 5.9, not a personal score.
- The teacher payload contains no `score` and no `attempt_id`.
- Archived division is absent. Archived parcours contributes no jumps, no score, no questions.
- Open jump appears for no attempt and for `inProgress`, with the matching `action`.
- Open jump is absent when the attempt is finished, the jump is draft, expiring, expired, or `expiration` is past while status is still `active`.
- Open session appears for no attempt and for `inProgress`, and is absent once the attempt is finished or `isActive()` is false.
- A session not linked to the class is absent, including one this student finished by public code.
- The same session linked to two of the student's classes appears in both.
- Active parcours is the one with the highest qualifying jump id. Its score matches the sum of graded jump scores, with missing attempts as 0.
- `recent_jumps` is at most 3, graded only, highest ids, and `number` matches the class-page expired-jump index.
- Delayed session with a finished attempt and a null-or-hidden score is absent from `recent_sessions` while the session is still running, and appears after expiry with `revealed_at === expires_at`.
- Immediate session appears in `recent_sessions` before the session expires, when the attempt is finished and the score is non-null.
- Rows older than 42 days are absent. A 4th session inside the window is absent from the list.
- `priority`, `badge_count`, `fresh`, and `fresh_grades` follow section 5.6. A fresh jump on a parcours that is not the active one is in `fresh_grades` and not duplicated in `recent_jumps`.
- Question payload has no `correct_answer`. A non-public suggestion is absent.
- Another student's score and attempt id are absent.
- `PocketChanged` is dispatched from each place in section 7, and its payload has only `division_id`.
- Two jumps in one class expiring in the same `ExpireJumps` run produce one division event for the expiring phase and one for the expired phase, not one per jump.
- An immediate `submit` dispatches `PocketChanged` on that user's channel and not on `division.{id}`. A delayed submit dispatches neither.
- Moving `expires_at` later does not dispatch `PocketChanged`. Activating a jump does not dispatch `PocketChanged` (`JumpActivated` still fires).
- Existing `JumpExpired` and `SessionExpired` broadcasts still happen, on the same channel, with the same payload, and not from the new bulk-expiring notification.

Frontend (browser, student account, desktop and a narrow viewport):

- Shell counter matches section 6. Two classes wrap, and the panel sits on the next line under the open button, full width.
- First load opens the priority-1 class when one class has an open jump and another has only questions.
- Clicking the other class moves the panel. Clicking the open class closes it.
- Starting a jump from a second browser session (or the teacher activating one) while this page is open adds the card, pulses the badge, and moves the panel only according to section 6.1.
- A graded jump the student just finished shows "Nouveau" after the expiry job, without a manual reload, and opens the same modal as the class page.
- A delayed session does not show a score in the pocket before expiry, and shows the score after expiry without a manual reload.
- Invite accept still asks for first and last name and then the new class button appears.
- Random question still offers the suggested-question toggle when the student has public questions.
- Teacher home shows the pocket above create-session and create-jump. An open jump on that pocket lands on the course page. An open session they authored lands on the session page. A recent session lands on the class page. Unpublished suggestions show a count, not question images.
- `prefers-reduced-motion: reduce` shows the badge and does not pulse.
- Dark mode: borders and text use the same tokens as the current pocket.

## 10. Tests

Create `tests/Feature/PocketTest.php` with Pest. Use existing factories. Cover every backend bullet in section 9, including a teacher who is also attached to a class, a second teacher's isolation, unpublished versus public suggestions, a closed-but-still-`active` jump, delayed versus immediate correction, the 42-day cutoff, the limit of 3, cross-student isolation, and `PocketChanged` dispatch.

Extend the existing expiry tests only far enough to expect `PocketChanged` beside the events they already assert.

No new JavaScript test runner.

Run, from the project root:

```bash
php artisan test --compact tests/Feature/PocketTest.php
vendor/bin/pint --dirty
```

If an expiry test was edited, run that file too.

## 11. Implementation order

1. Write `PocketTest` for the payload rules and watch it fail.
2. Add `PocketController`, the route, and the payload in section 5 until those tests pass.
3. Add `PocketChanged` and the dispatch sites. Extend tests.
4. Extract `JumpAttemptDetailModal` and point the class page at it. Confirm the class page modal is unchanged.
5. Add `pocketStore` and `PocketPanel`. Wire the home page for both roles. Delete the four-column pocket markup and the per-division question fetch.
6. Run Pint and the tests in section 10.
7. Walk the browser list in section 9. Fix what fails and re-check.

## 12. Copy

Use these strings. Do not invent English UI.

| Place | Copy |
| --- | --- |
| Shell title | Dans la poche |
| Shell actions | Afficher, Masquer |
| Empty panel | Rien à signaler pour cette classe. |
| Load error | Impossible de charger la poche. |
| Retry | Réessayer |
| Jumps | Sauts ouverts |
| Jump actions | Commencer le saut, Reprendre le saut |
| Jump meta | {n} questions · {time} min |
| Sessions | Sessions ouvertes |
| Session actions | Rejoindre, Reprendre la session |
| Fresh strip | Vient d'être noté |
| Fresh marker | Nouveau |
| No graded jump | Aucun saut terminé pour l'instant. |
| Score | {n} pts |
| Recent sessions | Sessions récentes |
| Questions (student) | Questions à revoir |
| Questions (teacher) | Questions suggérées |
| Teacher question row | {course_title} · {n} à publier |
| Teacher session action | Suivre la session |
| Teacher fresh strip | Vient de se terminer |
| Teacher jump meta | {n} questions · {time} min · {attempts} tentative(s) |
| Teacher graded count | {n} tentative(s) notée(s) |
| Class link | Voir la classe |
| Modal title | Saut {number} |
