## New feature

# WARNING 
This is a large feature. Study the description given in this file carefully. Make sure you understand the logic of it and take your time to implement it well, following all best practices. Write and run tests wherever you think it's relevant. Make sure the work on this feature does not compromise the previous functionalities of the app.

# Main idea
Teachers should be able to create 'Courses' (Parcours) for their classes. A course is a series of Jumps (Sauts) that the teacher can create. A 'Jump' is like a small Kangaroo session that includes only a limited number of randomly selected questions for each student based on their mastery level.

# Preparation
- Create a Course model : id, division_id, title, archived (boolean, default false)
- Create a Jump model : id, course_id, nb_questions, time, status (draft, active, expired), expiration (timestamp), growth (tinyInteger)
- Create a course_user table for the relationship (for students) with pivot column score (integer).
- Create a jump_user table for the relationship (for students) with pivot column question_list (json) and column score (integer)

# Implementation
When on a specific Class page, the teacher can create a course. All the class' courses are displayed as cards on the class page. The cards are router links leading to the specific Course page (teacher only).
Courses should be deletable when they have no jumps. Otherwise they're only archivable. Jumps are deletable but there is a special warning if any student attempt.
At the top of the Course page is a button for a new Jump. This opens a modal where the teacher can choose the time (default 15 minutes, min 5 minutes, max 60 minutes) and the number of questions (default 7, min 1, max 30) of this jump. There is a switch which, if on, calculates the nb of questions based on the time picked (1 question for each 2 minutes). The switch is on by default and when it is on, it disables the nb of question input. There also is a slider for the 'growth' (min: 0, max: 10) which measures how difficult each question gets after the third (see below). By default the growth is 3. Finally, show a select for the status (draft, active with active being the default). Draft jumps can be activated. The expiry time is calculated upon activation of the jump following the formula : expiry time = now + time for the jump + 15 minutes.

Once a new jump is activated, students can see it on their home page and can attempt the jump. When the student attemps a jump, it randomly selects questions for this student (different students of the class may get different questions). The questions are selected following this process :
- All questions from previous jumps from this course for this student are eliminated
- All questions from Kangaroo sessions done by this class before are also eliminated
- The first question should have a difficulty level which is approximately 100 points below the student's mastery level (select the closest possible)
- The second and third questions have a difficulty level which is approximately equal to the student's mastery level (select the closest possible)
- Each subsequent question should have a a higher difficulty level than the last one, gradually increasing so that the last question difficulty is min(2300, m + 200*g) (m is the student's mastery score, g is the jump growth)

When the question list has been selected, a record in the jump_user table is created with the question_list being an array of object (each object representing a question) with the following structure : { id (the question id), status (default 'pending'), difficulty (the question difficulty at the time of selection) }. score is set to 0.

The student attempts the jump just like a Kangaroo session (but with fewer questions). Re-use and adapt the attempt-view.uve (originally for kangaroo sessions) for the user to attempt the jump.
Just like for Kangaroo sessions, there should be a functionnality for the student to ask to rejoin the attempt if he terminated by mistake (with real time notification for both parties).
Just like for Kangaroo sessions, the score and correction are only displayed once the jump has expired (the teacher can edit the expiry time and make the jump expire instantly if necessary).

Once the jump expires, each student gets a score equal to the sum of the difficulty level for each question he answered correctly. the record in the jump_user table is updated : for each element in the question_list, update the status property (correct or incorrect based on student answer status). Update the score.

On the class page students should be able to see the history of their jumps for all courses.

On the Course page (for the teacher), there should be a table listing all the students and their scores for each jump. Each score should be clickable. When clicked, it opens a modal showing all the details from the jump (including the questions, their status, score etc.).