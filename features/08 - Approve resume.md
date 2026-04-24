## New feature

# Main idea
When a user comes back to a session for which he has an attempt, a request is sent to the session author before he can join it again.

# Preparation
Create a RejoinDemand request (id, attempt_id)

# Implementation
Noone should be able to have multiple attempts for any given session, even guests. 
When a user (logged in or guest) tries to rejoin a session he has an attempt for, it creates a RejoinDemand.
This Creates an alert on the session author's side. The list alerts lives in an "alert center" which is in the main app bar. Use real time notification for new alerts and alerts update. The alert should give the teacher all relevant information about the attempt (name, time remaining, last updated at, reason for termination, number of answered questions, etc.). 
The attempt also give the author two options : accept or deny. It should also give the option to add or remove time for the student if the demand is accepted.
If the demand is accepted, the student is allowed back in the session. The attempt is updated to show it is ongoing. The student is directly allowed back in the session using real time notification.
If the demand is denied, it is simply deleted.