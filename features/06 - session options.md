## New Feature


# Main idea
I want to add some options to allow users to personnalise their sessions

# New options
In the session details, add the following editable options : 
- Time limit (number in minutes - default 50)
- Blur security (boolean - if false, the blur and focus event listeners in the attempt-view will be disabled - default true)
- Only count tier 4 questions if all questions before are correct (boolean - default true)
- Shuffle questions (see below)

# Shuffle questions
This option determins in which order questions are displayed for students who attempt this session. If picked, questions are only shuffled by the client on loading the attempt-view. The objective is that two different students should each get their shuffled order of questions (to prevent copying).
Once the attempt is finished, when the students comes back to see his results, the questions should be displayed in the original order.
To reiterate : shuffling is done by the front end code on the students machine when doing an attempt. Answers are sent to the backend with the orginal question number. When the attempt is finished, there is no need to store the shuffle : all questions will be displayed in the original order afterwards.
This options have 4 possible settings : 
- No shuffle (questions are displayed in normal order - default)
- By tier (questions are shuffled by tier : tier 1 questions stay together but appear in random order, followed by tier 2 questions in random order, followed by tier 3 questions in random order)
- Shuffle tiers 1 - 3 (questions from tiers 1 - 3 are all shuffled together and appear in random order, followed by tier 4 questions)
- Complete shuffle (all questions are shuffled together and appear in random order)
If the questions are shuffled, there should be a note on the attempt-view to let the students know.

