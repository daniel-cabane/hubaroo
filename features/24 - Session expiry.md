# Feature fix : Session expiry

When a session expires, I want the following to happen : 
- An event is fired which force terminates all currently active session. Students are redirected to the result-view page. The student unsaved answers are sent back to the backend and recorded.
- On the result-view page, when the session is expired, the "Demander à reprendre" button is hidden
- On the backend, allow a small margin of error in case a bug occured : if a student client sends unsaved answers for a session which has expired less than 3 minutes ago, save these answers and then sends a response back which terminates the students' session on the front end.
- This should also apply if a student clicks "Terminer la session" (from the session-view) when the session has already expired : do not send an error back but just end the session and send the normal response back. If the session has expired less than 3 minutes ago, also save the unsaved answers.