# Artisan Commands Manual

This document provides detailed documentation for all custom Artisan commands in the Hubaroo application.

---

## Table of Contents

1. [import:kangourou-paper](#importkangourou-paper)
2. [kangourou:test-scraper](#kangouroutest-scraper)

---

## import:kangourou-paper

### Description
Imports Kangourou math competition papers with questions and answers from the official Kangourou website. This command automates the entire process of fetching papers, extracting question images, scraping correct answers, and storing them in the database.

### Signature
```bash
php artisan import:kangourou-paper
    {--year=2024 : The year of the competition (2004-2025)}
    {--level=e : The level letter (e, b, c, p, j, s)}
    {--force : Re-import even if paper already exists}
    {--debug : Show debug output and extracted answers}
```

### Options

| Option | Description | Default | Required |
|--------|-------------|---------|----------|
| `--year` | The competition year to import (range: 2004-2025) | `2024` | No |
| `--level` | Single-letter level code | `e` | No |
| `--force` | Re-import the paper even if it already exists in the database; deletes old paper and questions first | N/A | No |
| `--debug` | Show detailed debug output and extracted answers during import | N/A | No |

### Level Codes

| Code |   Name  |  Age Group |
|------|---------|------------|
| `e` | Ecoliers | Elementary |
| `b` | Benjamin | Ages 10-11 |
| `c` | Cadet    | Ages 12-13 |
| `p` | Pro      | Ages 15-18 |
| `j` | Junior   | Ages 15-16 |
| `s` | Student  | Ages 17+   |

### How It Works

The command performs the following steps:

1. **Validation** - Verifies that the year is between 2004-2025 and level is valid
2. **Existence Check** - Checks if the paper already exists (respects `--force` flag)
3. **PDF Download** - Downloads the official Kangourou paper PDF from the website
4. **Answer Scraping** - Scrapes the correct answers from the solutions page
5. **Question Extraction** - Extracts question images from the PDF
6. **Database Creation** - Creates Paper and Question records in the database with proper relationships
7. **Cleanup** - Removes temporary PDF files

### Usage Examples

#### Import a paper for the first time
```bash
php artisan import:kangourou-paper --year=2024 --level=c
```
Imports the 2024 Cadet (age 12-13) level paper.

#### Re-import an existing paper
```bash
php artisan import:kangourou-paper --year=2023 --level=j --force
```
Forcefully re-imports the 2023 Junior level paper, replacing the existing one.

#### Import with debug output
```bash
php artisan import:kangourou-paper --year=2022 --level=e --debug
```
Imports the 2022 Elementary level paper and displays debug information.

#### Import multiple papers (sequential commands)
```bash
php artisan import:kangourou-paper --year=2024 --level=e
php artisan import:kangourou-paper --year=2024 --level=b
php artisan import:kangourou-paper --year=2024 --level=c
php artisan import:kangourou-paper --year=2024 --level=p
php artisan import:kangourou-paper --year=2024 --level=j
php artisan import:kangourou-paper --year=2024 --level=s
```
Imports all six levels for 2024.

### Output

#### Successful Import
```
Importing Kangourou paper 2024-c...
  • Downloading PDF...
  • Scraping correct answers from solutions page...
  • Extracting questions from PDF...
  • Creating paper record...
  • Creating questions and linking to paper...
✓ Successfully imported Kangourou Cadet 2024 with 26 questions!
```

#### Paper Already Exists
```
Paper 2024-c already exists. Use --force to re-import.
```

#### Error Example
```
✗ Failed to import paper: [error message]
```

### Database Impact

- **Creates** a new `Paper` record with:
  - `title`: e.g., "Kangourou Cadet 2024"
  - `level`: single character code (e, b, c, p, j, s)
  - `year`: 2024
  
- **Creates 26** `Question` records, each with:
  - `image`: path to extracted question image file
  - `correct_answer`: the correct answer (A, B, C, D, or E)
  - `tier`: question difficulty tier (1-4):
    - Tier 1: Questions 1-8 (easiest)
    - Tier 2: Questions 9-16
    - Tier 3: Questions 17-24
    - Tier 4: Questions 25-26 (hardest)

- **Creates 26 relationships** in the `paper_question` pivot table with:
  - `paper_id`
  - `question_id`
  - `order`: 1-26 (question sequence)

### Error Handling

The command includes error handling for:
- Invalid year (outside 2004-2025 range)
- Invalid level code
- Failed PDF download
- Failed answer scraping
- Failed question extraction
- Database transaction failures

All errors are logged to `storage/logs/laravel.log`.

### Notes

- The command automatically deletes the temporary PDF after import
- All database operations are wrapped in a transaction for data integrity
- If `--force` is used and the paper exists, the old paper and all associated questions are deleted and replaced
- The command expects the official Kangourou website structure to remain consistent
- Each import creates exactly 26 questions (the standard Kangourou contest length)

### Dependencies

- `PdfDownloader` service (downloads PDF files)
- `KangourouScraper` service (extracts answers from solutions page)
- `QuestionExtractor` service (extracts images from PDF)
- Database with `papers`, `questions`, and `paper_question` tables

---

## kangourou:test-scraper

### Description
Tests the Kangourou solution scraper service and displays extracted answers. This is a debugging tool used to verify that the scraper can successfully fetch and parse answers from the official Kangourou solutions page.

### Signature
```bash
php artisan kangourou:test-scraper
    {--year=2024 : The year of the competition (2004-2025)}
    {--level=e : The level letter (e, b, c, p, j, s)}
```

### Options

| Option | Description | Default | Required |
|--------|-------------|---------|----------|
| `--year` | The competition year to test (range: 2004-2025) | `2024` | No |
| `--level` | Single-letter level code | `e` | No |

### Level Codes
Same as [import:kangourou-paper](#level-codes).

### How It Works

1. **Validation** - Verifies year (2004-2025) and level (e, b, c, p, j, s)
2. **URL Construction** - Builds the solutions page URL: `https://www.mathkang.org/concours/sol{year}{level}.html`
3. **HTTP Request** - Fetches the HTML page with a 10-second timeout
4. **Scraping Attempt** - Calls the `KangourouScraper` service to extract answers
5. **Result Display** - Shows the extracted answers or debugging information

### Usage Examples

#### Test scraper for a specific paper
```bash
php artisan kangourou:test-scraper --year=2024 --level=c
```
Tests if the scraper can extract answers from the 2024 Cadet solutions page.

#### Test with default values (2024 Elementary)
```bash
php artisan kangourou:test-scraper
```
Tests the most recent year and default level.

#### Test a historical paper
```bash
php artisan kangourou:test-scraper --year=2015 --level=j
```
Tests scraping from an older competition.

### Output

#### Successful Scrape
```
Testing Kangourou scraper for 2024-c...
URL: https://www.mathkang.org/concours/sol2024c.html
Fetching page...
Page size: 45823 bytes

Attempting to scrape with KangourouScraper...
✓ Successfully extracted 26 answers!

Answers: A, B, C, D, E, A, B, C, D, E, A, B, C, D, E, A, B, C, D, E, A, B, C, D, E, A
```

#### Failed Scrape with Debugging
```
Testing Kangourou scraper for 2024-c...
URL: https://www.mathkang.org/concours/sol2024c.html
Fetching page...
Page size: 45823 bytes

Attempting to scrape with KangourouScraper...
✗ Scraper failed: Could not extract answer pattern

Debugging information:
Tables found: 2
First table cells (raw content):
Total cells in first table: 52
Found 26 answer cells
First 30 cells: 1, A, 2, B, 3, C, 4, D, 5, E, ...
Answers found: A, B, C, D, E, A, B, C, D, E, A, B, C, D, E, A, B, C, D, E, A, B, C, D, E, A
```

#### Network Error
```
Testing Kangourou scraper for 2024-c...
URL: https://www.mathkang.org/concours/sol2024c.html
Fetching page...
✗ Scraper failed: Failed to fetch page: HTTP 404
```

### Debugging Information

If the scraper fails, the command automatically displays debugging information including:
- Number of tables and lists found in the HTML
- Cell and list item content from the page
- Extracted text content (first 500 characters of page body)
- Found answer patterns (A-E letters)

This helps diagnose what went wrong:
- **No tables found** → Page structure may have changed
- **Few answer cells** → Pattern extraction may be failing
- **HTTP error** → Website may be down or URL may be incorrect

### Use Cases

1. **Before running import** - Verify the scraper works before doing a full import
2. **Debugging import failures** - If an import fails, run this to isolate the issue
3. **Website structure changes** - If the official Kangourou website structure changes, use this to detect the problem
4. **Testing connectivity** - Verify network access to the official Kangourou website

### Error Scenarios

| Error | Cause | Solution |
|-------|-------|----------|
| Invalid year (outside 2004-2025) | Year is out of range | Use year between 2004 and 2025 |
| Invalid level | Level code is not valid | Use valid code: e, b, c, p, j, s |
| HTTP error (e.g., 404) | Page not found or website down | Check if year/level combination exists; verify website is accessible |
| No answers extracted | Scraper failed to parse HTML | Check debugging output; may indicate website structure changed |
| Network timeout | Server took too long to respond | Check internet connection; may indicate website is slow |

### Dependencies

- `KangourouScraper` service (parses HTML and extracts answers)
- `Http` client (fetches web pages)
- `Symfony\Component\DomCrawler\Crawler` (parses HTML)

### Notes

- This command does NOT modify the database—it's read-only
- Network timeout is set to 10 seconds
- The command validates input before attempting to fetch
- Useful for testing when setting up the system or diagnosing import issues

---

## Summary

| Command | Purpose | Modifies DB | Common Use |
|---------|---------|-------------|-----------|
| `import:kangourou-paper` | Import competition papers with questions | Yes | Building the paper database |
| `kangourou:test-scraper` | Test if scraper works for a paper | No | Debugging import issues |

Both commands include comprehensive error handling, validation, and logging to help diagnose issues with the Kangourou data import pipeline.
