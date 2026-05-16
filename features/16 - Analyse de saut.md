## New feature

# Main idea
The app should analyse jumps and extract questions to show the class later.

# Preparation
Make a SuggestedQuestions model
- id
- course_id
- question_id
- level (tinyInt)
- isPublic (boolean, default false)

# Questions categories
Questions are stored in 3 levels (Niveau) relative to the class average mastery score (called M in the following formulas) :
- Niveau 1 : Difficulty in [M-100 ; M+199] (max 10 questions stored)
- Niveau 2 : Difficulty in [M+200 ; M+399] (max 5 questions stored)
- Niveau 3 : Difficulty in [M+400 ; M+799] (max 3 questions stored)

# Storing the questions
After grading a jump, send a job to the queue which :
- Recalculates the current class mastery average M (which is the average of student's mastery for all students in the class).
- Review the questions posed to each student in the class and picks up to 10/5/3 questions for each "Niveau" (see above) where the student hasn't given a correct answer.
- Save each question as a SuggestedQuestion for the course the jump belongs to. Make sure that the maximum number of questions stored is respected (see above). Delete the oldest questions first.

# Displaying the questions
- In the class details page (for the teacher) show an icon next to each course. Cliking this icon opens a modal showing the list of questions per level (use star rating). Clicking on the question displays it in an overlay with a button underneath to reveal the answer. Both in the overlay and on the listing, each question as a delete button and a 'make public' button.
The 'make public button' toggles the isPublic boolean. If a question is public, it is displayed to all student of the class in the Highlight overlay on the home page and on the class-details-view (just underneath the class name)