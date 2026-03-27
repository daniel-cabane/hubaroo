<?php

namespace App\Console\Commands;

use App\Models\Paper;
use App\Models\Question;
use App\Services\KangourouScraper;
use App\Services\PdfDownloader;
use App\Services\QuestionExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportKangourouPaper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:kangourou-paper
            {--year=2024 : The year of the competition (2004-2025)}
            {--level=e : The level letter (e, b, c, p, j, s)}
            {--force : Re-import even if paper already exists}
            {--debug : Show debug output and extracted answers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Kangourou math competition papers with questions and answers';

    public function __construct(
        private PdfDownloader $pdfDownloader,
        private KangourouScraper $scraper,
        private QuestionExtractor $questionExtractor,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->option('year');
        $level = strtolower($this->option('level'));
        $force = $this->option('force');

        // Validate inputs
        if (!$this->validateInputs($year, $level)) {
            return self::FAILURE;
        }

        try {
            // Check if paper already exists
            $existingPaper = Paper::where('year', $year)->where('level', $level)->first();

            if ($existingPaper && !$force) {
                $this->info("Paper {$year}-{$level} already exists. Use --force to re-import.");
                return self::SUCCESS;
            }

            if ($existingPaper && $force) {
                $this->warn("Removing existing paper {$year}-{$level}...");
                $existingPaper->questions()->detach();
                $existingPaper->delete();
            }

            $this->info("Importing Kangourou paper {$year}-{$level}...");

            // Step 1: Download PDF
            $this->line("  • Downloading PDF...");
            $pdfContent = $this->pdfDownloader->download($year, $level);
            $tempPdfPath = tempnam(sys_get_temp_dir(), 'kangourou_');
            file_put_contents($tempPdfPath, $pdfContent);

            // Step 2: Scrape correct answers
            $this->line("  • Scraping correct answers from solutions page...");
            $answers = $this->scraper->scrape($year, $level);

            // Step 3: Extract questions from PDF
            $this->line("  • Extracting questions from PDF...");
            $questions = $this->questionExtractor->extract($tempPdfPath, $year, $level);

            // Step 4: Determine paper title and level name
            $levelNames = ['e' => 'Ecoliers', 'b' => 'Benjamin', 'c' => 'Cadet', 'p' => 'Pee-Wee', 'j' => 'Junior', 's' => 'Student'];
            $paperTitle = "Kangourou {$levelNames[$level]} {$year}";

            // Step 5: Create Paper record
            $this->line("  • Creating paper record...");
            $paper = Paper::create([
                'title' => $paperTitle,
                'level' => $level,
                'year' => $year,
            ]);

            // Step 6: Create Questions and link to Paper
            $this->line("  • Creating questions and linking to paper...");

            DB::transaction(function () use ($paper, $questions, $answers) {
                foreach ($questions as $index => $questionData) {
                    $questionNumber = $index + 1;

                    // Create question
                    $question = Question::create([
                        'image' => $questionData['image'],
                        'correct_answer' => $answers[$index] ?? 'A',
                        'tier' => $questionData['tier'],
                    ]);

                    // Attach to paper with order
                    $paper->questions()->attach($question->id, [
                        'order' => $questionNumber,
                    ]);
                }
            });

            // Cleanup
            @unlink($tempPdfPath);

            $this->info("✓ Successfully imported {$paper->title} with 26 questions!");
            Log::info("Imported Kangourou paper", [
                'title' => $paper->title,
                'year' => $year,
                'level' => $level,
                'question_count' => 26,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Failed to import paper: {$e->getMessage()}");
            Log::error("Kangourou paper import failed", [
                'year' => $year,
                'level' => $level,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Validate user inputs.
     */
    private function validateInputs(int $year, string $level): bool
    {
        if ($year < 2004 || $year > 2025) {
            $this->error("Year must be between 2004 and 2025.");
            return false;
        }

        if (!in_array($level, ['e', 'b', 'c', 'p', 'j', 's'])) {
            $this->error("Level must be one of: e, b, c, p, j, s");
            return false;
        }

        return true;
    }
}
