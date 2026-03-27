<?php

namespace App\Console\Commands;

use App\Services\KangourouScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class TestKangourouScraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kangourou:test-scraper
            {--year=2024 : The year of the competition (2004-2025)}
            {--level=e : The level letter (e, b, c, p, j, s)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Kangourou solution scraper and show extracted answers';

    public function __construct(
        private KangourouScraper $scraper,
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

        if ($year < 2004 || $year > 2025) {
            $this->error("Year must be between 2004 and 2025.");
            return self::FAILURE;
        }

        if (!in_array($level, ['e', 'b', 'c', 'p', 'j', 's'])) {
            $this->error("Level must be one of: e, b, c, p, j, s");
            return self::FAILURE;
        }

        $this->info("Testing Kangourou scraper for {$year}-{$level}...");
        $url = "https://www.mathkang.org/concours/sol{$year}{$level}.html";
        $this->line("URL: $url");

        try {
            // Fetch page
            $this->line("Fetching page...");
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                $this->error("Failed to fetch page: HTTP {$response->status()}");
                return self::FAILURE;
            }

            $html = $response->body();
            $this->line("Page size: " . strlen($html) . " bytes");

            // Try scraper
            $this->line("");
            $this->info("Attempting to scrape with KangourouScraper...");
            $answers = $this->scraper->scrape($year, $level);

            $this->info("✓ Successfully extracted " . count($answers) . " answers!");
            $this->line("");
            $this->line("Answers: " . implode(", ", $answers));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Scraper failed: {$e->getMessage()}");

            // Try to show what was extracted
            $this->line("");
            $this->info("Debugging information:");
            try {
                $response = Http::timeout(10)->get($url);
                $html = $response->body();
                $this->showDebuggingInfo($html);
            } catch (\Exception $debugError) {
                $this->warn("Could not fetch page for debugging: {$debugError->getMessage()}");
            }

            return self::FAILURE;
        }
    }

    private function showDebuggingInfo(string $html): void
    {
        $crawler = new Crawler($html);

        // Check for tables
        $tables = $crawler->filterXPath('//table');
        $this->line("Tables found: " . $tables->count());

        if ($tables->count() > 0) {
            $this->line("First table cells (raw content):");
            $cells = $tables->first()->filterXPath('//td | //th');
            $this->line("Total cells in first table: " . $cells->count());
            
            $cellTexts = [];
            $answerCells = [];
            $cells->each(function (Crawler $cell, $i) use (&$cellTexts, &$answerCells) {
                $text = trim($cell->text());
                if (!empty($text)) {
                    $cellTexts[] = $text;
                    if (preg_match('/^[A-E]$/', $text)) {
                        $answerCells[] = $text;
                    }
                }
            });
            
            $this->line("Found " . count($answerCells) . " answer cells");
            $this->line("First 30 cells: " . implode(", ", array_slice($cellTexts, 0, 30)));
            $this->line("Answers found: " . implode(", ", $answerCells));
        }

        // Check for lists
        $lists = $crawler->filterXPath('//ol | //ul');
        $this->line("Lists found: " . $lists->count());

        if ($lists->count() > 0) {
            $this->line("First list items:");
            $items = $lists->first()->filterXPath('//li');
            $itemTexts = [];
            $items->each(function (Crawler $item) use (&$itemTexts) {
                $text = trim($item->text());
                if (!empty($text)) {
                    $itemTexts[] = $text;
                }
            });
            $this->line("  " . implode("\n  ", array_slice($itemTexts, 0, 10)));
        }

        // Show page body first 500 chars
        $this->line("");
        $this->line("Page body (first 500 chars):");
        $body = $crawler->filterXPath('//body')->html();
        $body = html_entity_decode($body);
        $this->line("  " . substr($body, 0, 500));
    }
}
