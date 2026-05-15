## New feature

# Main idea
Teachers should be able to observe the students performances on jumps (from courses) both after the jump and during the jump in real time.

# Preparation
Every student action (answer updated, session submitted, timed out or blurred, etc.) should fire a jumpUpdated event

# Implementation
On the course view, for each jump, add an 'Voir les détails' option in the jump menu (opened by clicking on the status tag of the jump).
Clicking this option opens a modal where the list of all students in the class is displayed in a table giving all the details for the jump : status, time remaining, answers given
- If the jump is expired, the answers should be color coded (correct/incorrect)
- If the jump is active, the answers should be updated in real time (listening to the jumpUpdated event)