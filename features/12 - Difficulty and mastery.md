## New feature

# Main idea
Questions should have a difficulty score and users should have a mastery score. Both these scores should be dynamic, 
changing based on how well users solve questions.

# Levels recap
The levels of the kangaroo papers and their values are :
- e (value = 1)
- b (value = 2)
- c (value = 3)
- j (value = 4)
- p (value = 4)
- s (value = 5)

# Preparation
- Add a difficulty column (smallInteger) to the question table.
The difficulty of a question should follow this formula: difficulty = 300*level + 100*2^(tier-1) where level is the level of the kangaroo paper the question belongs to (see recap above) and tier is the question tier (1-4)
- Add a mastery colum (smallInteger - default null) to the users table.

# Implementation
- When a user registers as a student, ask for their birth year and calculate their age. Then affect the mastery level using this formula: mastery=max(age-8, 1)*150
- When a user submits a session, send a job to the queue. This job should review each question in the session which has an answer. For each question answered, calculate difference = user mastery - question difficulty
-- If difference<0 and answer is correct, add (-difference*0.1, round up) to user mastery and remove (-difference*0.01, round up) to question difficulty
-- If difference>0 and answer is incorrect, remove (difference*0.1, round up) to the user mastery and add (difference*0.01, round up) to the question difficulty
- Check that the rules explained above make sense: difficult questions should receive a high difficulty score and easy questions should receive a low difficulty score. Strong users should have a high mastery score and weak users should have a low mastery score.
- Note that question difficulty and user mastery are internal tools. They are not meant for user display.