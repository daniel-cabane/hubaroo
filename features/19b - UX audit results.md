# UX Audit Report — Hubaroo

> Auditor: Expert Senior UX/UI Researcher, EdTech Product Designer & Trust/Safety Analyst  
> Date: 2026-05-27  
> Stack: Laravel 12 · Vue 3 · Tailwind CSS v4 · Laravel Reverb  
> Target: French mathematics competition app (ages 8–18), teachers & students

---

## 1. UX Health & Friction Score

| Persona | Score | Justification |
|---------|-------|---------------|
| **Teachers** | **62 / 100** | The teacher workflow for launching a session is reasonably fast (3–4 clicks to create and activate), and the real-time attempt table is genuinely useful. However, the entire navigation system is buried inside a single profile-icon dropdown, there is no persistent dashboard, and several multi-step flows (e.g. setting a session as private then linking it to a class) require non-obvious save sequencing that can silently fail or trap the teacher behind a blocking overlay. |
| **Students** | **55 / 100** | The attempt experience itself (carousel, colour-coded nav circles, blur alarm, zoom-on-tap) is well-designed for the core task. The significant deductions come from a virtually empty landing page with no value proposition for first-time visitors, confusing jargon in several places ("Dans la poche", "Saut", "Parcours"), anxiety-inducing alarm UI, and near-zero accessibility for users with disabilities or on low-end devices. |

---

## 2. Deep-Dive UX Analysis

### Teachers

---

#### [HIGH FRICTION] T1 — Entire navigation lives in a single dropdown icon
>>> FIXED

**Screen / Flow:** Every page — the only navigation control is a circular user-icon button in the top-right corner.

**The Problem:** A teacher managing multiple live sessions must open a dropdown menu, read a list of 5+ items, and click to navigate between sections. There is no persistent breadcrumb, sidebar, or top nav bar. First-time users have no visual map of the app. The icon itself is nearly identical between guest and authenticated states (a generic silhouette vs a slightly larger ring icon), making it unclear whether one is logged in.

**The Fix:** Add a persistent top navigation row below the header for authenticated users with direct links to the core sections. For teachers: `Mes sessions | Mes classes | Voir un sujet`. For students: `Mes tentatives | Mes classes`. This removes 1–2 clicks from every navigation action and makes the mental model of the app immediately legible.

---

#### [HIGH FRICTION] T2 — Session creation doesn't confirm or preview the paper
>>> FIXED

**Screen / Flow:** `/kangourou/create` — Year + Level dropdowns, Status selector, Create button.

**The Problem:** A teacher selecting "Écoliers 2022" has no way to verify it's the right paper before creating the session. The level codes (e, b, c, p, j, s) are translated to long labels ("E — Écoliers (CE2-CM2)") but the teacher must still remember what year they want. There is no preview of questions, no display of question count, and no indication of what "Brouillon" vs "Active" means in the classroom context.

**The Fix:** Add a paper preview link (opening the `paper-view` in a side drawer or new tab) once year + level are selected. Display the full paper title as a confirmation line: *"Vous allez créer une session pour Kangourou Écoliers 2022 — 26 questions."* Add a tooltip on the Status selector explaining: *"Brouillon : invisible aux élèves. Active : les élèves peuvent rejoindre immédiatement."*

---

#### [HIGH FRICTION] T3 — Private session + class linking requires non-obvious save sequence
>>> KINDA FIXED

**Screen / Flow:** Session details → Paramètres tab → Privacy toggle → Class access toggles.

**The Problem:** When a teacher sets Privacy to "Privé" but has not yet saved, an opaque overlay appears on the class-access section with the message *"Enregistrer les modifications."* There is no Save button visible in context — the teacher must find and click an implicit auto-save trigger elsewhere, or realize the form requires a separate save step. During a live class this creates a stressful dead-end. If the teacher saves, then wants to toggle division access, each toggle fires a separate API call (implemented as individual buttons), which is fine but there is no confirmation that the toggle succeeded.

**The Fix:** 
1. Make the "save required" overlay a sticky banner at the bottom of the form with a prominent Save button and a clear message: *"Sauvegardez d'abord la confidentialité pour gérer l'accès par classe."*
2. Show a brief green checkmark animation next to each division toggle row after it succeeds.
3. Consider collapsing privacy + class access into a single "save" action.

---

#### [HIGH FRICTION] T4 — Session list ("Mes sessions") provides no actionable quick-glance data
>>> KINDA FIXED

**Screen / Flow:** `/my/sessions` — list of session cards showing: title, code, status badge, expiry date/time, delete button.

**The Problem:** The list does not show the number of attempts, the number of students who completed vs are still in-progress, or which class the session is linked to. A teacher returning to the page between lessons cannot tell at a glance which sessions need attention. For a teacher with 15+ sessions (realistic across a school year), there is no pagination control, sort, or filter.

**The Fix:**
- Add attempt count to each card: *"3 tentatives (2 terminées)"*
- Add a linked class tag when the session is private and linked to a division
- Add a sort by status/date and a search/filter by title
- Add visual emphasis (e.g., a pulsing green dot) on actively running sessions

---

#### [MEDIUM FRICTION] T5 — Code refresh button sits unsafely next to the displayed code
>>> IGNORED

**Screen / Flow:** Session details header — session code in large monospace text, with a small RefreshCw icon immediately to its right, no gap, no confirmation.

**The Problem:** A single mis-click on the refresh icon during a live session would invalidate the code that students are currently using to join. There is no confirmation dialog. Students who haven't joined yet would receive an error if they type the old code. The button's small size (w-5 h-5) on a touch screen makes this accident-prone.

**The Fix:** Gate code regeneration behind a confirmation modal: *"Générer un nouveau code ? Le code actuel (EEOQ5B) sera immédiatement invalide."* Only allow code regeneration for draft sessions, or add a strong warning when the session is active.

---

#### [MEDIUM FRICTION] T6 — No high-level dashboard or "now" view for teachers
>>> IGNORED

**Screen / Flow:** Home page after login — shows "Dans la poche" section with course shortcut cards, plus "Créer" and "Rejoindre" action tiles.

**The Problem:** The "Dans la poche" section title is French colloquial slang ("in the bag/pocket"). Its meaning is not self-explanatory. It lists course names only (e.g., "Ollie — 0 sauts") with no indication of whether any are active or need action. The teacher's most urgent current need — *is a session live right now? How many students have joined?* — is not visible on the home page at all unless an active session is in the "Sessions actives" subsection. There is no single "command center" view.

**The Fix:** Rename "Dans la poche" to a clear label like *"Accès rapide"* or *"En ce moment"*. Add a dedicated "Session en cours" card at the top that shows the live session code, attempt count, and a direct link to the session details. Prioritize urgency over shortcuts.

---

#### [MEDIUM FRICTION] T7 — "Voir les détails" jump observation is hidden behind a three-level menu

**Screen / Flow:** Course details page → Jump table header → Click on status tag → Dropdown menu → "Voir les détails".

**The Problem:** The jump observation modal (real-time student progress during a jump) is the most time-sensitive piece of data on this page. To access it, a teacher must click the jump's status tag (which looks like a read-only badge), wait for a dropdown to appear, then click "Voir les détails". The status tag serving as a dropdown trigger is not visually discoverable — it looks like a static indicator, not an interactive control.

**The Fix:** Add an explicit "Observer" eye-icon button column in the jump table header row, always visible when the jump is active. Reserve the status-tag dropdown for administrative actions (activate, edit expiry, delete).

---

#### [MEDIUM FRICTION] T8 — Deleting an attempt has no confirmation or undo

**Screen / Flow:** Session details → Tentatives tab → trash icon per attempt row.

**The Problem:** Clicking the trash icon immediately fires the delete request with no confirmation. A teacher who accidentally deletes a student's attempt during a live session has no way to recover it. The trash icon is visually similar in weight to the edit (pencil) icon next to it, increasing the chance of a mis-click.

**The Fix:** Add a confirmation dialog: *"Supprimer la tentative de [nom] ? Cette action est irréversible."* Consider adding a soft-delete with a 30-second undo toast instead.

---

#### [UX ENHANCEMENT] T9 — "Mes sessions" doesn't link back to the associated class

**Screen / Flow:** Session details page — "Retour" link goes to `/my/sessions`, not to the linked class.

**The Problem:** When a teacher manages sessions in the context of a class, navigating to a session and back loses the class context. There is no breadcrumb like *"Classe → dolore molestiae non → Session"*.

**The Fix:** When a session is linked to a division, show a secondary back-link: *"← Retour à la classe [nom]"* in addition to the standard "Mes sessions" link.

---

#### [UX ENHANCEMENT] T10 — Session analysis eye icon is undiscoverable on division detail page

**Screen / Flow:** Class details (teacher view) → Sessions tab → eye icon next to expired sessions.

**The Problem:** The analysis feature (success ratio per question) is a high-value pedagogical tool. But its entry point is a small eye icon next to each session row. There is no tooltip, label, or callout to indicate what clicking it does. First-time teachers would likely never discover this feature.

**The Fix:** Replace the bare icon with a labelled button: *"Analyse"* or add a tooltip on hover. Consider adding a "Nouvelles analyses disponibles" badge on the class card in the divisions list after sessions expire.

---

### Students

---

#### [HIGH FRICTION] S1 — Landing page for unauthenticated users communicates nothing

**Screen / Flow:** `/` for guest — single card: "Rejoindre une session" with a login-arrow icon.

**The Problem:** A student visiting for the first time sees a single unlabelled card with a brief description. There is no app name tagline, no explanation of what Hubaroo is, no instructions ("Ask your teacher for a session code"), no motivation to register. The student's only call-to-action assumes they already have a code. Students arriving via a link directly would see this page and not know whether they are in the right place.

**The Fix:** Add a hero section above the join card with: the app name/tagline ("Concours Kangourou en ligne"), a one-sentence explanation, and a "Rejoindre une session → Entrez le code donné par votre professeur" instruction. For logged-out users, show a secondary link: *"Pas encore de compte ? Inscrivez-vous gratuitement"*.

---

#### [HIGH FRICTION] S2 — Blur alarm is unnecessarily alarming for younger students (ages 8–12)

**Screen / Flow:** Attempt in progress — tab loses focus → full-screen black overlay with large red countdown (`10`, `9`, `8`...) and text "Revenez !".

**The Problem:** An 8–10-year-old student who accidentally clicks outside the browser window (e.g., on the taskbar, or on a notification popup) will see a full-screen black overlay with a giant red countdown, which can be genuinely frightening. The first experience trains students to panic on any accidental tab switch. On school computers where background notifications are common (Teams, printer alerts, etc.), this will fire frequently. The emotional cost is high.

**The Fix:** Soften the visual design: use a warm amber tone instead of red/black, reduce the countdown to a moderate display (not `text-6xl`), and use a less threatening message: *"Vous avez quitté la page — revenez dans X secondes pour continuer."* Consider adding a "Ce n'est pas grave !" reassurance line for the first blur event.

---

#### [HIGH FRICTION] S3 — Question difficulty exposed in jump results (violates spec intent)

**Screen / Flow:** Jump results view (`jump-results-view.vue`) — each question card shows *"Difficulté : [number]"* (e.g., "Difficulté : 1300").

**The Problem:** The feature specification for Feature 12 explicitly states: *"question difficulty and user mastery are internal tools. They are not meant for user display."* Showing a raw difficulty score to students (especially 8–12 year olds) is confusing and meaningless without context. It also exposes internal algorithmic parameters that could be gamed by sophisticated students who learn to correlate difficulty scores with question content.

**The Fix:** Remove the "Difficulté : [number]" line from the jump results view entirely. If a tier-like indicator is desired for student feedback, use the star-rating system already used for Suggested Questions (1–3 stars for levels 1–3).

---

#### [HIGH FRICTION] S4 — Timer is hidden by default with no clear affordance

**Screen / Flow:** Attempt view timer bar — shows "Afficher le chrono" button.

**The Problem:** The countdown timer is hidden by default and requires the student to click "Afficher le chrono" to see it. Students may not notice this option exists and therefore have no sense of remaining time until the last minute when it becomes mandatory. For younger students (8–12) who are not experienced test-takers, this is a significant disadvantage — they won't learn to pace themselves because they can't see the clock.

**The Fix:** For sessions where the time limit is ≤ 60 minutes (the standard Kangourou case), show the timer by default. Allow students to hide it if they prefer. This is the opposite of the current default and better serves the majority use case.

---

#### [MEDIUM FRICTION] S5 — "Dans la poche" section title is jargon-heavy for young students

**Screen / Flow:** Home page (logged-in students) — collapsible section with invites, active jumps, public questions.

**The Problem:** "Dans la poche" is French idiomatic slang meaning "it's in the bag." This makes sense as a metaphor for the teacher (shortcuts to what's coming up) but is confusing for an 8-year-old student. The section also bundles together three completely different types of content (class invitations, active jumps, review questions) under a single header with no visual hierarchy.

**The Fix:** Rename to *"Pour vous"* or *"À faire"* for students. Split the sub-sections more clearly: *"Invitations"* (action required), *"Sauts disponibles"* (time-sensitive), *"Questions à revoir"* (optional). Use distinct icons and colours per category.

---

#### [MEDIUM FRICTION] S6 — Course/Jump vocabulary ("Parcours", "Saut") unexplained

**Screen / Flow:** Student home page, division details, jump attempt view — terms "Parcours", "Saut", "Sauter" used throughout.

**The Problem:** The app uses "Parcours" (course/track) and "Saut" (jump/leap) as primary feature names. While these names are consistent, they are never explained to new students. A student arriving at their class page and seeing "Antoine — 17 sauts" with no context has no idea what a "saut" is or why their teacher's course is called a name.

**The Fix:** Add a brief tooltip or inline description on first encounter: *"Un Saut est un mini-test personnalisé. Vous recevrez des questions adaptées à votre niveau."* Consider a one-time onboarding tooltip sequence (similar to Duolingo) for new students in a class.

---

#### [MEDIUM FRICTION] S7 — "Demander à reprendre" available even after voluntary submission

**Screen / Flow:** Results page after a student has submitted their attempt intentionally (`termination: 'submitted'`).

**The Problem:** The results page shows a "Demander à reprendre" button when `attempt.termination !== 'timeout'`. This means a student who clicked "Terminer la session" and confirmed the modal can immediately request to rejoin and potentially change their answers. The burden falls on the teacher to refuse in the alert center. During a large session (30+ students), a teacher managing the class will receive spurious rejoin requests.

**The Fix:** Only show the rejoin request button when `termination === 'blurred'` (kicked out by the blur alarm) or when the student knows they left accidentally. If `termination === 'submitted'`, replace the rejoin section with a clear message: *"Vous avez soumis votre session. Aucune modification n'est possible."*

---

#### [MEDIUM FRICTION] S8 — Guest user receives no feedback about the auto-save status of their answers

**Screen / Flow:** Attempt view — student selects an answer, answer dots update locally, but no visual indicator confirms the answer reached the server.

**The Problem:** Answers are saved automatically in the background via PATCH requests. If a student's connection drops momentarily, they have no way to know their answer was not saved. On a school Wi-Fi network (often unreliable), this can lead to lost answers. The student would only discover the issue after submission when they see 0 answered questions.

**The Fix:** Add a subtle "Enregistrement..." / "✓ Sauvegardé" status indicator in the top bar that appears briefly after each answer selection. On network error, show a persistent warning banner: *"Problème de connexion — vos réponses ne sont pas sauvegardées. Vérifiez votre réseau."*

---

#### [MEDIUM FRICTION] S9 — Jump attempt shows account name instead of class name in the timer bar

**Screen / Flow:** `jump-attempt-view.vue` timer bar center — shows `{{ authStore.user?.name }}`.

**The Problem:** The Kangourou session attempt view correctly shows the attempt name (`attemptStore.attempt?.name`) in the center of the timer bar — this is the class name (e.g., "Jean DUPONT") that identifies the student in the teacher's table. The jump attempt view instead shows the account name (`authStore.user?.name`), which may differ from the class name. This causes the teacher's real-time observation table to show different names than what the student expects to be identified as.

**The Fix:** Replace `authStore.user?.name` with the class name from the jump attempt record, falling back to `authStore.user?.name` only if no class name is set.

---

#### [UX ENHANCEMENT] S10 — Rejoin flow state is split across two pages

**Screen / Flow:** Student submits, goes to results page, sees rejoin state. Approval notification arrives in the alert bell (🔔 icon) in the header.

**The Problem:** After a student requests to rejoin, the approval notification arrives in the Alert Center bell icon at the top of the page. The results page does show `rejoinApproved` reactive state, but only if the WebSocket event is received while that page is open. If the student navigates away and back, the state may be lost. More importantly, students who don't know to watch the bell icon will not know they've been approved.

**The Fix:** When a rejoin is approved, show a prominent full-page overlay (not just the alert bell) with a direct "Rejoindre maintenant" button. Do not require the student to find the alert center.

---

#### [UX ENHANCEMENT] S11 — No "what happens next" explanation on the post-attempt waiting screen

**Screen / Flow:** Results page before session expiry — *"La correction sera disponible à la fin de la session."*

**The Problem:** A student who finishes in 25 minutes and sees this message has no idea when the session expires. There is no displayed expiry time. The student does not know if they need to stay on the page, come back later, or if they'll receive a notification.

**The Fix:** Show the session expiry time: *"La correction sera disponible le [date/heure]. Vous serez notifié(e) dès qu'elle est prête."* If real-time WebSocket events are broadcasting the session expiry, trigger an in-page reload automatically when the session expires — which would allow the correction to appear without requiring the student to manually refresh.

---

## 3. Vulnerability & Abuse Vector Report

---

### [CHEATING] V1 — Guest name field allows impersonation and offensive content

**The Exploit:** On `session-view.vue`, when a student joins a public session as a guest, they are prompted to "Entrez votre nom pour commencer" with no validation, no minimum length, no content filtering, and no maximum length restriction enforced client-side (the input has `maxlength="255"`). A student can enter: their teacher's name, another student's name, offensive terms, or HTML/JS injection strings.

**The Consequence:** The teacher's attempt table displays these names to all class members during real-time observation. An offensive name disrupts the classroom. Impersonating a classmate causes confusion in grading. The name appears permanently in the session record.

**The Defense:**
1. Enforce a minimum length of 2 characters and a maximum of 50 characters server-side.
2. Apply a basic profanity filter (or at minimum block strings starting with `<`, `{`, `\`).
3. For sessions linked to a class, only allow authenticated students who are members of that class to join (rather than accepting any guest with a typed name).
4. Allow the teacher to rename any attempt in the table (already implemented via the edit pencil icon — this is good).

---

### [CHEATING] V2 — Question images accessible via direct URL after loading

**The Exploit:** Question images are served at `/questions/[filename]` (e.g., `/questions/2022_e_q01.png`). Once a student's browser has loaded an attempt page, all 26 image URLs are present in the browser's network request history. A student can copy the image URL and share it with friends via phone, or upload it to a group chat, even if the page prevents right-clicking (`oncontextmenu="return false"`).

**The Consequence:** Questions are shared externally, defeating the purpose of the timed competition. This is particularly likely for well-known papers (Kangourou competition papers are public) but also applicable if the images were custom.

**The Defense:** This is largely unavoidable for any image-based quiz app — images that the browser renders are accessible. Mitigation options:
1. Serve images through a signed, time-limited URL (using Laravel's `Storage::temporaryUrl()`), so the URL expires after the attempt ends.
2. Add a visible watermark with the student's name/attempt ID on the rendered image (server-side or via CSS overlay) so shared screenshots can be traced.
3. Accept this as an inherent limitation of the format (since the source material is publicly available competition papers anyway).

---

### [CHEATING] V3 — Blur security only fires on `window` focus events, bypassable via mobile split-screen or picture-in-picture

**The Exploit:** The blur security listens to `window` `blur` and `focus` events in the browser. On many mobile devices and modern browsers, picture-in-picture video overlays, Android split-screen mode, and iOS slide-over mode do not consistently fire `blur` events on the main window. A student can open a calculator app or another browser in split-screen and research answers without triggering the alarm.

**The Consequence:** The blur security feature gives teachers false confidence that students are not switching contexts, while sophisticated students on mobile bypass it trivially.

**The Defense:**
1. Add a `visibilitychange` event listener (more reliable on mobile) in addition to the `blur`/`focus` events.
2. In the teacher's pre-session configuration, add a note in the blur-security toggle's help text: *"Cette sécurité est efficace sur ordinateur. Elle peut être contournée sur certains appareils mobiles."*
3. Consider adding tab visibility monitoring via `document.hidden` as a supplementary check.

---

### [CHEATING] V4 — Answer pre-submission does not lock individual answers

**The Exploit:** A student who finishes 15 of 26 questions can continue to change all 15 answers repeatedly until the session expires. There is no per-question locking. A student could fill in all 26 answers with educated guesses, wait to see the timer expire (at which point the session would be auto-submitted), and if immediate correction is enabled, see which guesses were correct before the teacher reviews the paper.

**The Consequence:** In sessions with immediate correction enabled, a student who submitted gets correction feedback. If the session is still active for other students, this creates an unfair advantage for the first submitter over those still working.

**The Defense:** This is an inherent design trade-off (the app allows changing answers before submission, which is the expected behavior for exam software). The key guardrail is that **corrections should never be shown while the session is active** (delayed correction by default). Ensure the "Correction différée" default is prominently labeled during session creation so teachers understand the implication of choosing "Immédiate".

---

### [FARMING] V5 — Jump score farming through mastery score inflation

**The Exploit:** The mastery and difficulty algorithm rewards correct answers by increasing user mastery and decreasing question difficulty (making future questions easier). A student who consistently answers the easiest questions correctly will see their mastery score gradually inflate, which makes them appear more advanced than they are. Because jump question selection targets `mastery - 100` for the first question, an inflated mastery score means the student gets harder questions they may struggle with — but the system itself can be gamed if a student deliberately avoids harder questions (which they can see coming by the jump growth parameter).

**The Consequence:** The adaptive difficulty system loses precision, and the teacher's view of student performance (via the jump score table) becomes misleading for that student.

**The Defense:** The mastery algorithm's design already partially addresses this (incorrect answers on "easy" questions also reduce mastery). However:
1. Add a floor to mastery reduction so students aren't discouraged by harsh downward swings.
2. Consider showing teachers the mastery score alongside the score in the course details table (currently mastery is entirely hidden from display).

---

### [FARMING] V6 — Guest attempts can be created indefinitely for the same session code

**The Exploit:** A guest user on `session-view.vue` is only blocked from creating a duplicate attempt if an `attempt_id` for that session exists in the browser's `localStorage`. Clearing `localStorage`, opening an incognito window, or simply using a different device allows the same guest to create a new attempt for the same session code — potentially multiple times.

**The Consequence:** A student who got a bad score can re-attempt the session under a new name, diluting the teacher's attempt table with duplicates. The student picks their best result. The teacher sees multiple "anonymous" attempts and cannot tell which is the student's genuine first attempt.

**The Defense:**
1. For sessions linked to a class (authenticated-only sessions), this is partially mitigated since authenticated users are blocked from duplicate attempts by the `user_id` check.
2. For public guest sessions, this is inherent to the guest model. Mitigation: add a browser fingerprint (or at least IP address tracking) to flag multiple attempts from the same device. Show the teacher a warning: *"Cette tentative pourrait être un doublon (même IP)"*.
3. Allow teachers to mark attempts as "invalid" without deleting them, so the teacher can keep the history while flagging duplicates.

---

### [TROLLING] V7 — Account display name ("Modifier le nom") has no restrictions

**The Exploit:** Any authenticated user can change their account display name via the "Modifier le nom" modal in the account menu (accessible in `App.vue`). This name is used as the attempt name when joining sessions (for logged-in users) and as the student identifier in the jump timer bar. There is no profanity filter, minimum length, or character restriction.

**The Consequence:** A student can set their display name to an offensive term, another student's full name, or the teacher's name. This name then appears in the teacher's session observation table, the jump observation modal, and all historical attempt records.

**The Defense:**
1. Apply the same name formatting rules as class names: first letter of each word uppercase, rest lowercase, max 50 characters.
2. Soft-block common offensive terms.
3. Allow teachers to override the attempt name for any attempt in their sessions (already possible for Kangourou sessions — extend this to jump attempts).

---

### [TROLLING] V8 — Bug report text is unvalidated and teachers/admins must read it

**The Exploit:** Authenticated students can submit up to 5 bug reports per user (the limit is 5 "unsolved" reports). The comment field is a free-text `textarea` with no stated length limit on the form (the model validation should enforce `max:1000` but this is not verified from the code). A student can submit 5 reports containing offensive content, slurs, or spam.

**The Consequence:** Admins and potentially teachers (if they have admin access) see this content when reviewing the bug report section.

**The Defense:**
1. Add a character limit display on the textarea (e.g., "250/1000 characters").
2. Add a basic content moderation note: *"Les signalements injurieux ou abusifs peuvent entraîner la suspension du compte."*
3. Consider adding a rate limit per IP on the bug report endpoint (independent of user rate limiting).

---

### [TROLLING] V9 — Session code guessing allows uninvited students to join public sessions

**The Exploit:** Public sessions are joinable by anyone who knows the 6-character alphanumeric code. The code space is 36^6 ≈ 2.1 billion combinations, which is too large for real-time brute force (rate limiting prevents this). However, session codes are generated with `Str::random(6)` which uses PHP's `mt_rand` under the hood in some versions — this is not cryptographically random. More importantly, teachers sharing session codes verbally in a classroom means students in adjacent rooms or corridor "overhear" codes. Students who learn a code can join from home and inflate the attempt table.

**The Consequence:** Uninvited participants pollute the session's attempt data. For competitions, this distorts class-level statistics.

**The Defense:**
1. For sessions linked to a class, enforce that only authenticated class members can join (regardless of whether the code is public or private). Currently, privacy settings control this, but teachers creating sessions may not understand the distinction.
2. Add a "participant approval" option: instead of auto-creating an attempt on code entry, the teacher must approve each new participant (an existing feature for rejoin requests — adapt it for first joins).
3. Change `Str::random()` to `Str::upper(Str::random(6))` using `random_bytes()` as the entropy source (this is already Str::random in Laravel, which uses `random_bytes` internally since Laravel 9).

---

## 4. Prioritized Action Plan

### Impact vs. Effort Matrix

| Quadrant | Item | Issue | Impact | Effort |
|----------|------|-------|--------|--------|
| **Quick Wins** (High Impact, Low Effort) | Fix S3 | Remove difficulty score from jump results | High — violates spec intent | Very Low — delete 1 line |
| | Fix S7 | Restrict "Demander à reprendre" to non-voluntary terminations | High — reduces teacher spam | Low — 1 condition change |
| | Fix T5 | Add confirmation dialog on session code refresh | High — prevents data loss during live sessions | Low — add modal |
| | Fix T8 | Add confirmation dialog on attempt delete | Medium — prevents accidental loss | Low — add modal |
| | Fix S9 | Show class name in jump attempt timer bar | Medium — consistency, identity | Low — 1 variable change |
| | Fix V7 | Add name validation on "Modifier le nom" modal | High — abuse prevention | Low — add server-side validation |
| | Fix S11 | Show expiry time on post-attempt waiting screen | Medium — reduces student anxiety | Low — add a computed date display |
| **Major Projects** (High Impact, High Effort) | Redesign T1 | Add persistent navigation bar for authenticated users | High — core UX issue | High — layout refactor |
| | Redesign S1 | Build a meaningful landing page for guests | High — conversion and guidance | Medium-High — new view section |
| | Redesign S5/S6 | Add student onboarding flow (tooltips/coach marks) | High — feature discoverability | High — new component system |
| | Implement S8 | Add answer save status indicator | High — trust, reliability | Medium |
| **Fill Ins** (Lower Impact, Low Effort) | Fix T9 | Add "← Retour à la classe" link from session details | Low — navigation improvement | Very Low |
| | Fix T6 | Rename "Dans la poche" to "En ce moment" | Low — clarity | Very Low |
| | Fix S2 tone | Soften blur alarm visual design | Medium — emotional UX | Low — CSS + copy changes |
| | Fix T2 | Add paper preview/confirmation on session creation | Medium — reduces teacher errors | Low-Medium |
| **Thankless Tasks** (Low Impact, High Effort) | Redesign T3 | Redesign private session + class linking UX | Medium — workflow improvement | High |
| | V2 | Implement signed image URLs | Low — inherent limitation | High — storage changes |
| | V3 | Improve mobile blur detection | Low — partial defense | Medium |
| | WCAG | Full accessibility audit + screen reader support | High (moral obligation) | Very High |

---

### Critical Accessibility Notes (WCAG 2.1)

The following accessibility issues are not categorized in the friction matrix above but are obligations for a product serving children aged 8–18 in a school context:

1. **No ARIA labels on icon-only buttons**: The account menu button, the fullscreen code button, the code refresh button, the recent attempts panel toggle, and the alert bell all rely on visual icons alone. Screen readers cannot identify their function.

2. **Question content is image-only**: All 26 competition questions are images. There is no alt text describing the mathematical content, making the entire competition section unusable for visually impaired students.

3. **No dark mode toggle in UI**: Dark mode is OS-preference only. Students in low-light school environments cannot manually switch.

4. **No font size controls**: No ability to increase text size beyond the browser's default zoom.

5. **Color as sole status indicator**: In the attempt view, question status (answered / correct / incorrect) is communicated by color only (green/red/grey circles). A colorblind student cannot distinguish "correct" from "incorrect" circles.

**Minimum required fix:** Add shape differentiation to status indicators (e.g., a checkmark on correct, an X on incorrect, an empty circle on unanswered) in addition to color.
