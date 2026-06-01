## Page revamp

# Main idea
I want to revamp the division-details-view.vue for teachers to make it more accessible.

# Implementation
Make 3 tabs underneath the header : Elèves, Sessions, Parcours.
- In the Elèves tab, put the student list and the accepting_students switch
- In the Sessions tab, put the session list. Display the sessions side by side in a carousel. Each session has a header and the 'Questions à revoir' analysis displayed under it (it is no longer hidden inside a modal). In the analysis, remove the 'Revue' column: simply add a green tick when the question has been reviewed. Add a view details button in the header which opens the students performance modal.
- In the Courses tab, put a large select at the top to select the course to display. By default, the course with the latest jump is selected. When a course is selected, steal the content from the course-details-view.vue to display the course details. Add the 'Nouveau' and 'Questions suggérées' buttons at the top.
