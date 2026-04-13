## New feature

# Main idea
A user should be able to organise a "kangourou session" where he picks a paper (year and level) and the app generates a session to sit that specific paper. The session has a code (6 alphanumeric characters). Any user can join the session if he goes to the app and enters the code or if he goes to the url "/session/{code}". When visiting the url, it creates an attempt for the user
A logged in user has a session history and an attempt history, but guest users can also create sessions and attempts


# Kangourou Session
Make a KangourouSession model. The model should have
- paper_id (has one relationship)
- a code (6 alphanumeric characters)
- author_id (nullable)
- a status (draft, active, expired)
- a privacy setting (public, private)
- an expiry datetime (default 120 minutes after creation)
- preferences (json)

preferences include : 
- time limit (default 50 minutes)
- correction can be set to immediate or delayed (default delayed)
- grading rules (default is +3/+4/+5 for a correct answer on a tier 1/2/3 questions and penalty of a quater of the points for an incorrect answer). Tier 4 questions award 1 point if and only if all answers for questions 1 to 24 are correct.

If the user creating a kangourou session is logged in, the kangourou session is added to his kangourou session history.

While the kangourou session is active, it can be accessed at the url /kangourou/{code} (where {code} is the 6 alphanumeric character code mentionned above)


# Attempts
Any user accessing the kangourou session creates an Attempt. Make an Attempt model with
- session_id (belongsTo relationship)
- user_id (belongsTo relationship but nullable)
- unique code for recovery (6 alphanumeric characters)
- list of the 26 answers (json), each answer is a letter (nullable) and a status (unanswered, answered, correct, incorrect)
- status (inProgress, finished)

If the user is logged in, the attempt is added to his attempts history.


# Attempt frontend
When the user visits the kangourou session url, an attempt is created in the background and the attempt_id is stored in local storage. 
IMPORTANT : The list of correct answers is NEVER sent with the paper while the session is active
If the user leaves and comes back to the app and an attempt_id is present in local storage, it queries the database and if the session is still active, the app notifies the user and gives him a link to rejoin the attempt.
The attempt page is a carousel with all 26 questions. 
For each question, the image is displayed centered and below the question are 5 buttons for the answer (A, B, C, D or E)
The 26 numbers are displayed above the carousel (horizontally scrollable with if overflowing - no lift). Each number leads to the relevant question when clicked and each is color coded according to its status (unanswered or answered when session is active, correct or incorrect when session is finished).
Everytime the user selects an answers, it is saved to his attempt in the database in the background.
Each session has a time limit (defined in the preferences). A countdown should be displayed at the top of the page (ideally in the main tool bar). The user can toggle the display on and off until the last minute, after that the countdown is always on. Once the countdown reaches 0, the attempt is submitted and can no longer be edited by the user.
There should be an alarm and a 10s countdown when the tab is blurred (onBlur event listener) to prevent users to switch to another tab or application during the attempt.
At the bottom of the page is a submit button, which shows a modal for confirmation then submits the attempt.
Once submitted, the user can no longer edit his attempt. Once graded, he sees his score and the correction for each question.