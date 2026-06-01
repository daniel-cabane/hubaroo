## New feature

# Main idea
On the home page for students, replace the "Créer une session" mega button by a "Ask a random question" mega button.

# Implementation
For students on the home-view.vue, replace the 'Créer une session' mega button by a 'Envoyer une question' mega button.
The button opens a modal which displays a question for the student to solve.
Here is how the question is selected : 
- If there are some 'Questions à revoir' from the student classes, take those first
- If not, request the backend which selects 20 questions where difficulty >= student mastery ordered by difficulty (the 20 questions with the lowest difficulty while being above the student's mastery score). Then it plucks a random question out of those 20.

- On the modal there should be a switch to prioritise the 'Questions à revoir'. The switch is off and disabled if there are no 'Questions à revoir' but on by default otherwise
- The modal show offer the option to reveal the correct answer if the question is not from the 'Questions à revoir'.
- The modal should offer 3 buttons to pick a new question : easier, same level and harder, moving the reference score by 200 points each time.