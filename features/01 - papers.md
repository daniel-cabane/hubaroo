## New feature

# Main idea
The app should get past papers from the french maths competition called 'Kangourou', extract questions and save them to database. It should find out the correct answer for each question. Finally, it should make a paper to list all the questions

# Preparation
Create a Question model. It should have the following fields
- image
- correct_answer
- tier

Create a Paper model. It should have the following fields
- title
- level

Create a relationship where a paper has many questions

# Step 1 : Find the paper and the solutions
Download a paper from the Kangourou website. The pdf can be found at the following url : https://www.mathkang.org/pdf/kangourou2011e.pdf
where yyyy is the year of the competition (from 2004 to 2025). l is the letter designating the level. The letter l can be e, b, c, p, j or s
For example, here are a couple of valid url : https://www.mathkang.org/pdf/kangourou2024b.pdf ; https://www.mathkang.org/pdf/kangourou2015e.pdf ; https://www.mathkang.org/pdf/kangourou2008j.pdf

The solutions for each paper can be found in the main table of the page https://www.mathkang.org/concours/solyyyyl.html (where yyyyl is the same as the paper).

# Step 2 : Create the questions
Use an AI model to recognize each of the 26 questions of the paper. Extract an image (screenshot) for each question. Create a new Question record for each question of the paper and use the image to save it to the database. the tier of the question is fixed as follows
- questions 1 - 8 = tier 1
- questions 9 - 16 = tier 2
- questions 17 - 24 = tier 3
- questions 25 - 26 = tier 4

# Step 3 : Create the paper
Give it the title of the paper : Kangourou Ecoliers 2011 and the level of 'E'
Add the questions of step 2 to this test (create a many to many relationship with the question order in the pivot table).

# Step 4 : Repeat for the other levels
There are 6 papers for each year, determined by the last letter before the .pdf extention. The letter can be : e, b, c, p, j or s
their urls are : https://www.mathkang.org/pdf/kangourou2011b.pdf ; https://www.mathkang.org/pdf/kangourou2011c.pdf, https://www.mathkang.org/pdf/kangourou2011p.pdf ; https://www.mathkang.org/pdf/kangourou2011j.pdf https://www.mathkang.org/pdf/kangourou2011s.pdf
Repeat steps 1 - 3 for these papers

# Repeat for the next years
Repeat steps 1 - 4 for years 2012 through to 2025.