## Audit

# WARNING !
This audit requires extensive preparation. Take the time to understand the app completely before exploring each functionnality individually. To have a better idea of what I was expecting, you can read the files in the /features folder. Then look at the code to see how it was implemented

# Role & Objective:
You are an expert Principal Software Engineer, Senior Solutions Architect, and Cybersecurity Auditor with 15+ years of experience reviewing enterprise-grade applications. Your objective is to conduct a rigorous, exhaustive code audit of the application code provided below. 

# Auditing the app
Study the app and make a list of all of its functionnalities. Then, for each functionnality, assess how well it is implemented. Is it working well ? Is the code efficient, foolproof, and following best practices. 

Please analyze the code through the following five lenses, being brutally honest and thorough:

1. Code Quality, Maintainability & Readability
- Does the code follow standard best practices for this specific tech stack (e.g., DRY, SOLID principles)?
- Are naming conventions intuitive and consistent?
- Is the code self-documenting, or is it overly complex and lacking necessary comments?
- Is the folder/file structure scalable?

2. Performance & Optimization
- Are there any algorithmic inefficiencies (e.g., nested loops causing O(n²) complexity)?
- Are there potential memory leaks, redundant API calls, or unoptimized database queries (e.g., N+1 query problems)?
- For the frontend: Are there unnecessary re-renders or heavy bundle-size culprits?
- For the backend: Is asynchronous code handled efficiently without blocking the event loop?
- For the backend: Are there unnecessary calls to the database ?

3. Security & Vulnerabilities
- Are there any OWASP Top 10 vulnerabilities (e.g., SQL injection, XSS, CSRF, broken authentication)?
- Are sensitive data, API keys, or secrets hardcoded anywhere?
- Is user input properly sanitized and validated?

4. Error Handling & Edge Cases
- What happens if an API call fails or the database times out? Is there proper graceful degradation?
- Are try/catch blocks used effectively, or are errors being silently swallowed?
- Identify at least 3 edge cases the current logic fails to address.

5. Architecture & State Management
- Is data flowing predictably through the application?
- Are concerns separated correctly, or is there business logic bleeding into presentation layers?

---

Required Output Format:
Provide your audit structured exactly as follows:

### 1. Executive Summary
- Overall Code Health Score (0/100) with a 2-sentence justification.
- Top 3 critical issues that must be fixed immediately.

### 2. Deep-Dive Analysis
Group your findings by severity: [CRITICAL], [WARNING], or [OPTIMIZATION]. For every issue found, provide:
- File Name & Line Number (or code snippet).
- The Problem: Explain exactly why it is bad or inefficient.
- The Fix: Provide the exact, optimized refactored code to resolve it.

### 3. Architecture & Scalability Review
- A brief evaluation of the app's current architecture and whether it will survive scaling to 10k+ active users.

### 4. Actionable Checklist
- A bulleted list of fixes prioritized from "Do This First" to "Nice to Have."

---

# Output
Do not try to fix any of the issues you find. Write a complete report in the file /features/18b - 18 - Functionnality audit results.md