<?php

use App\Models\Paper;
use App\Models\Question;
use App\Services\KangourouScraper;
use App\Services\PdfDownloader;
use App\Services\QuestionExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Disable real HTTP requests
    Http::preventStrayRequests();
    
    // Use temporary storage for tests
    Storage::fake('local');
});

// Helper function to create a minimal PDF
function createMinimalPdf(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\nxref\n0 1\n0000000000 65535 f\ntrailer\n<< /Size 1 /Root 1 0 R >>\nstartxref\n0\n%%EOF";
}

test('import kangourou paper with valid year and level', function () {
    // Mock the PDF downloader
    $this->mock(PdfDownloader::class, function ($mock) {
        $pdfContent = createMinimalPdf();
        $mock->shouldReceive('download')
            ->with(2024, 'e')
            ->once()
            ->andReturn($pdfContent);
    });

    // Mock the scraper
    $this->mock(KangourouScraper::class, function ($mock) {
        $answers = array_fill(0, 26, 'A');
        $answers[0] = 'A';
        $answers[1] = 'B';
        $answers[2] = 'C';
        $mock->shouldReceive('scrape')
            ->with(2024, 'e')
            ->once()
            ->andReturn($answers);
    });

    // Mock the question extractor
    $this->mock(QuestionExtractor::class, function ($mock) {
        $questions = [];
        for ($i = 1; $i <= 26; $i++) {
            $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
            $questions[] = [
                'image' => "questions/2024_e_q{$i}.png",
                'tier' => $tier,
            ];
        }
        $mock->shouldReceive('extract')
            ->once()
            ->andReturn($questions);
    });

    $this->artisan('import:kangourou-paper --year=2024 --level=e')
        ->assertExitCode(0);

    // Verify paper was created
    $paper = Paper::where('year', 2024)->where('level', 'e')->first();
    expect($paper)->not->toBeNull();
    expect($paper->title)->toBe('Kangourou Ecoliers 2024');
    expect($paper->level)->toBe('e');
    expect($paper->year)->toBe(2024);

    // Verify questions were created and linked
    expect($paper->questions()->count())->toBe(26);

    // Verify tiers are assigned correctly
    $tier1Questions = $paper->questions()->wherePivot('order', '<=', 8)->get();
    expect($tier1Questions->count())->toBe(8);
    expect($tier1Questions->first()->tier)->toBe(1);

    $tier2Questions = $paper->questions()->wherePivot('order', '>=', 9)->wherePivot('order', '<=', 16)->get();
    expect($tier2Questions->count())->toBe(8);
    expect($tier2Questions->first()->tier)->toBe(2);

    $tier3Questions = $paper->questions()->wherePivot('order', '>=', 17)->wherePivot('order', '<=', 24)->get();
    expect($tier3Questions->count())->toBe(8);
    expect($tier3Questions->first()->tier)->toBe(3);

    $tier4Questions = $paper->questions()->wherePivot('order', '>=', 25)->get();
    expect($tier4Questions->count())->toBe(2);
    expect($tier4Questions->first()->tier)->toBe(4);
});

test('skips import if paper already exists', function () {
    // Create an existing paper
    $existing = Paper::create([
        'title' => 'Kangourou Ecoliers 2024',
        'level' => 'e',
        'year' => 2024,
    ]);

    $this->artisan('import:kangourou-paper --year=2024 --level=e')
        ->assertExitCode(0);

    // Verify no duplicate was created
    expect(Paper::where('year', 2024)->where('level', 'e')->count())->toBe(1);
});

test('re-imports paper when force flag is used', function () {
    // Mock the services
    $this->mock(PdfDownloader::class, function ($mock) {
        $pdfContent = createMinimalPdf();
        $mock->shouldReceive('download')->andReturn($pdfContent);
    });

    $this->mock(KangourouScraper::class, function ($mock) {
        $answers = array_fill(0, 26, 'A');
        $mock->shouldReceive('scrape')->andReturn($answers);
    });

    $this->mock(QuestionExtractor::class, function ($mock) {
        $questions = [];
        for ($i = 1; $i <= 26; $i++) {
            $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
            $questions[] = ['image' => "q{$i}.png", 'tier' => $tier];
        }
        $mock->shouldReceive('extract')->andReturn($questions);
    });

    // Create initial paper
    $initial = Paper::create([
        'title' => 'Old Title',
        'level' => 'e',
        'year' => 2024,
    ]);
    $initialId = $initial->id;

    // Re-import with force
    $this->artisan('import:kangourou-paper --year=2024 --level=e --force')
        ->assertExitCode(0);

    // Verify old paper was replaced
    expect(Paper::where('year', 2024)->where('level', 'e')->count())->toBe(1);
    $newPaper = Paper::where('year', 2024)->where('level', 'e')->first();
    expect($newPaper->title)->toBe('Kangourou Ecoliers 2024');
    expect($newPaper->id)->not->toBe($initialId);
});

test('rejects invalid year', function () {
    $this->artisan('import:kangourou-paper --year=2000 --level=e')
        ->assertExitCode(1);

    expect(Paper::count())->toBe(0);
});

test('rejects invalid level', function () {
    $this->artisan('import:kangourou-paper --year=2024 --level=x')
        ->assertExitCode(1);

    expect(Paper::count())->toBe(0);
});

test('accepts all valid levels', function () {
    $validLevels = ['e', 'b', 'c', 'p', 'j', 's'];

    foreach ($validLevels as $level) {
        // Mock the services
        $this->mock(PdfDownloader::class, function ($mock) {
            $pdfContent = createMinimalPdf();
            $mock->shouldReceive('download')->andReturn($pdfContent);
        });

        $this->mock(KangourouScraper::class, function ($mock) {
            $answers = array_fill(0, 26, 'A');
            $mock->shouldReceive('scrape')->andReturn($answers);
        });

        $this->mock(QuestionExtractor::class, function ($mock) {
            $questions = [];
            for ($i = 1; $i <= 26; $i++) {
                $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
                $questions[] = ['image' => "q{$i}.png", 'tier' => $tier];
            }
            $mock->shouldReceive('extract')->andReturn($questions);
        });

        $this->artisan('import:kangourou-paper --year=2024 --level=' . $level)
            ->assertExitCode(0);
    }

    expect(Paper::count())->toBe(count($validLevels));
});
