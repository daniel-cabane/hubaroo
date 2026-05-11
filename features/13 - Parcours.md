## New feature

# Main idea
Teachers should be able to create Courses (Parcours) for their classes. A course is a series of steps which the teacher can create. A step is like a small Kangaroo session that includes only a limited number of randomly selected questions for each student based on their mastery level.

# Implementation
- Create a Course model (id, teacher_id, division_id, title)
- Create a Step model (id, course_id, nb_questions)