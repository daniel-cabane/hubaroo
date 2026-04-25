## New feature

# Main idea
Add proper names for students when joining a class

# Preparation
- Add a class_name column in the division_user table and add it (->pivot) to the relationship on User and Division

# Class name format
When implementing the class name, it has to follow these formating rules : 
- The first name has the first letter of each word upper case and the rest lower case. spaces are replaced by "-"
- The last name is all caps
For example :
- input first="leo", last="dicap" gives a class name of "Leo DICAP"
- input first="jean PAUL", last="La Glu de Colle" gives a class name of "Jean-Paul LA GLU DE COLLE"

# Implementation
- When a user joins a class, he has to input his first name and last name. The class name is then formated as indicated above and saved in the pivot table.
- When the teacher accesses a class, he does not see the users real names, but their class names in the class list.
- If a user is in a class but does not have a class name, his user name is affected as the class name.
- When a user attempts a session open to on of the classes he belongs to, his class name is used for the attempt name.
- A teacher can edit the class names of all students in his class (in the class details view).