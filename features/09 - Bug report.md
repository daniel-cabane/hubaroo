## New feature

# Main idea
Allow users to send bug reports about the app

# Preparation
Create a BugReport model
- id
- user_id
- comment
- status (new, important, tbd, fixed, irrelevant)

# Implementation
- Guests cannot submit bug reports.
- Add a Bug report button in the profile menu which opens a modal with a text area for the user to type his report and send it.
- Limit to 5 unsolved bug report per user. Hide the bug report option after that.
- On the admin page, add a section for bug reports. Admin can update the status and delete bug reports.