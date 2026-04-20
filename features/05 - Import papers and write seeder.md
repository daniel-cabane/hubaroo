## New feature

# Main idea
I want to import all Kangourou papers and write a seeder to create them on the production server


# Preparing the seeder
- Create a paperSeeder seeder which is called in production to create all the kangourou papers on the production server

# Importing papers
- Delete all papers and all questions from the db. Delete all images in the public/questions directory.
- Import all kangourou papers from 2004 to 2025 for all levels (be careful, for years 2010 and before, the url is slightly different and there is no 'p' paper. Note that /app/Services/PdfDownloader.php should already be coded for this)
- For each paper, add its reference to the paperSeeder