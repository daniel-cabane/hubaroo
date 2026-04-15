## New feature

# Main idea
Implement a class system where teachers can create classes, invite students and publish kangourou sessions for their classes

# Preparation
- Upon registering, users have to chose a role (student or teacher). This role is affected to the user.
- Create a Division model (a division is a class, we just avoid the word "Class" which is reserved). This model should have :
    - teacher_id (relationship with User model)
    - a name
    - a code (6 alphanumeric characters)
    - prefrences (json object, null for now)
    - accepting_students (boolean, default true)
    - archived (boolean, default false)
- Set up a many to many relationship between classes and users (students)

# CRUD
- Teachers should be able to create classes.
- Upon creation the class code is chosen randomly.
- The teacher owning the class should be able to archive and delete it.
- The teacher owning the class should be able to edit the name and request to change the class code (new random code generated).
- The teacher owning the class should be able to toggle accepting_students. 

# Students
- The teacher owning the class should be able to send invites to students using their email address (no email is sent, the invite just pops up on the students interface)
- Students should be able to join the class using the class code.
- When accepting_students is false, no students can join the class via the class code.

# Kangourou sessions
- When a kangourou session is private, the author can open it for any of his classes individually.

# Interface
- Teachers should have a page with all their classes (one card per class, linking to the class details page)
- Students should have a class page with the class's kangourou sessions