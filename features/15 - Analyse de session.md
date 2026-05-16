## New feature

# Main idea
The app should analyse Class' Kangourou sessions and display success ratio per question for the teacher and allow him to display questions from the paper

# Preparation
Add a 'analysis' column (json, nullable) to the division_kangourou_session and add this as a pivot attribute to the relationship

# Implementation
When a Kangourou session expire, after all the students submissions have been graded, calculate the success ratio for each question amongst the students of the class. save the list as an array of objects { question_id, succes_ratio, reviewed } in the analysis column (reviewed is a boolean which is false by default).
In the class details page (for the teacher), next to each expired session, display an eye icon button (if the analysis is not null). When clicked it opens a modal with the list of question, their success ratio color coded, whether or not they have been reviewed (togglable) and a button to view the question. 
When the view button question is pressed, the question should be displayed as an overlay with 80vw. Show a button underneath to reveal the correct answer and a toggle to change the reviewed attribute.