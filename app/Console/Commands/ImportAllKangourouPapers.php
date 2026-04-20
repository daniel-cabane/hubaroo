<?php

namespace App\Console\Commands;

use App\Models\Paper;
use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportAllKangourouPapers extends Command
{
    protected $signature = 'import:all-kangourou-papers
            {--from=2004 : Start year}
            {--to=2025 : End year}';

    protected $description = 'Import all Kangourou papers from 2004-2025 and generate the PaperSeeder';

    public function handle(): int
    {
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        // Step 1: Clean slate
        $this->warn('Deleting all existing papers, questions, and images...');
        Paper::query()->each(function (Paper $paper) {
            $paper->questions()->detach();
        });
        Question::query()->delete();
        Paper::query()->delete();

        $questionsDir = public_path('questions');
        if (File::isDirectory($questionsDir)) {
            File::cleanDirectory($questionsDir);
        }

        $this->info('Database and images cleared.');

        // Step 2: Import all papers
        $failed = [];
        $imported = 0;

        for ($year = $from; $year <= $to; $year++) {
            $levels = $year <= 2010
                ? ['e', 'b', 'c', 'j', 's']
                : ['e', 'b', 'c', 'p', 'j', 's'];

            foreach ($levels as $level) {
                $this->newLine();
                $this->info("=== Importing {$year}-{$level} ===");

                $exitCode = $this->call('import:kangourou-paper', [
                    '--year' => $year,
                    '--level' => $level,
                    '--force' => true,
                ]);

                if ($exitCode === self::SUCCESS) {
                    $imported++;
                } else {
                    $failed[] = "{$year}-{$level}";
                    $this->error("  ✗ Failed {$year}-{$level}");
                }
            }
        }

        // Step 3: Generate seeder
        $this->newLine(2);
        $this->info('Generating PaperSeeder...');
        $this->call('generate:paper-seeder');

        // Summary
        $this->newLine();
        $this->info('============================');
        $this->info("Import complete: {$imported} papers imported.");

        if (! empty($failed)) {
            $this->warn('Failed papers ('.count($failed).'):');
            foreach ($failed as $f) {
                $this->line("  - {$f}");
            }
        }

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }
}
