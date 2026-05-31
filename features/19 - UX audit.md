## Audit

# WARNING !
This audit requires extensive preparation. Take the time to understand the app completely before exploring each functionnality individually. To have a better idea of what I was expecting, you can read the files in the /features folder. Then look at the code to see how it was implemented.

# Role & Objective:
You are an expert Senior UX/UI Researcher, EdTech Product Designer, and Trust & Safety Analyst with 15+ years of experience building and auditing educational software. Your objective is to conduct a rigorous User Experience (UX) audit of the educational application described below. 

You must evaluate it through three distinct lenses: general usability, teacher workflow efficiency, and potential avenues for student system abuse or exploitation.

# Context:
- App Name/Type: A gamified math puzzles web app
- Primary Users: Teachers (for assigning/monitoring) and Students (for learning/completing tasks).
- Target Student Age Group: 8 - 18 Y.O.
- Core Features: Organising "Kangourou sessions" (26 puzzles to solve in 50 minutes) or "Jumps" (like Kangourou sessions but customisable on time and nb of questions). The app has a Class system (jumps are only for classes)

# Audit Criteria & Scope:
Please analyze the user flows, UI descriptions, or screenshots provided below against the following four pillars:

1. General Usability & Cognitive Load (Nielsen’s Heuristics)
- Is the interface intuitive, or does it require excessive onboarding?
- Is the language age-appropriate for the target student demographic?
- Are the navigation paths clear, minimizing the "clicks-to-completion" for core tasks?

2. Pedagogical & Teacher Workflow Efficiency
- Teachers are chronically short on time. Does the UI allow them to launch assignments, view insights, and manage rosters with absolute minimal friction?
- Is the data on the teacher dashboard actually actionable, or is it a confusing wall of numbers?
- Does the interface address common classroom edge cases (e.g., adding a late-enrolling student, handling a student who forgot their password mid-lesson)?

3. Student Exploitation & System Abuse (Gamification & Cheating)
Students are highly creative at breaking systems. Identify loopholes where a student could:
- Cheat or look up answers (e.g., UI flaws exposing correct answers in the DOM/source code, or lack of strict window-blur restrictions during tests).
- Exploit gamification elements (e.g., "farming" points/XP by spamming easy tasks, clicking rapidly, or resetting the page).
- Disrupt the classroom (e.g., choosing inappropriate display names, spamming chat/feedback boxes, guessing other students' simple login PINs).

4. Accessibility (WCAG) & Inclusivity
- Is the application accessible for students with diverse needs (e.g., color contrast, screen reader friendliness, text size adjustments)?
- How well does the UI hold up on a low-end school-issued Chromebook or mobile device?

---

Required Output Format:
Provide your audit structured exactly as follows:

### 1. UX Health & Friction Score
- An overall usability score out of 100 for Teachers and a separate score out of 100 for Students, with a 2-sentence justification for each.

### 2. Deep-Dive UX Analysis (Teachers vs. Students)
Identify the primary points of friction. Group your findings using: [HIGH FRICTION], [MEDIUM FRICTION], or [UX ENHANCEMENT]. For each point:
- Describe the exact screen/flow where the issue occurs.
- The Problem: Explain why this causes cognitive load or delays.
- The Fix: Provide concrete UI/UX wireframe ideas or copy changes to resolve it.

### 3. Vulnerability & Abuse Vector Report
List all potential ways a student can exploit or break the intended flow of the app (Cheating, Farming, or Trolling). For each vector, provide:
- The Exploit: How a student would pull it off.
- The Consequence: How it affects the teacher or the classroom dynamic.
- The Defense: UX or technical guardrails to block the exploit.

### 4. Prioritized Action Plan
- A matrix mapping fixes by "Impact vs. Effort" (Quick Wins, Major Projects, Fill Ins, Thankless Tasks).

---

# Output
Do not try to fix any of the issues you find. Write a complete report in the file /features/19b - UX audit results.md