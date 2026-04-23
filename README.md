# Hubaroo

An interactive platform for the [Kangourou des Mathématiques](https://mathkang.org) competition. Teachers can create live competition sessions from archived papers (2004–2025), organize students into classes, and track results in real time.

## Features

- **Competition Papers** — Browse all Kangourou papers by year and level (CE1–Terminale). Questions are extracted from official PDFs and displayed without answers.
- **Live Sessions** — Create a session with a shareable 6-character code. Guests and registered students can join. Fully configurable: time limit, shuffle mode, correction timing, and scoring penalties.
- **Real-time Updates** — Student joins, answer changes, and session events are broadcast instantly via WebSockets (Laravel Reverb + Echo).
- **Classes (Divisions)** — Teachers create classes, invite students by email or join code, and assign sessions to specific classes.
- **Automatic Grading** — Attempts are scored using the official Kangourou scoring system (tiered points + penalty fractions + tier-4 bonus).
- **Admin Panel** — Manage paper metadata and assign user roles.

## Tech Stack

| Layer | Technologies |
|---|---|
| Backend | PHP 8.2, Laravel 12, Spatie Permission |
| Frontend | Vue 3, Tailwind CSS v4, Pinia, Vue Router |
| Real-time | Laravel Reverb, Laravel Echo, Pusher.js |
| PDF Processing | Spatie PDF, PDF.js, mupdf, Sharp |
| Testing | Pest 3, PHPUnit 11 |
| Dev Tools | Laravel Pint, Laravel Sail, Vite 7 |

## Getting Started

### Prerequisites

- PHP 8.2+
- Node.js 20+
- A supported database (MySQL / SQLite)

### Installation

```bash
# Clone the repository
git clone https://github.com/your-org/hubaroo.git
cd hubaroo

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations and seed the database
php artisan migrate --seed

# Build frontend assets
npm run build
```

### Development

```bash
# Start all services (server + queue + Vite)
composer run dev
```

### Testing

```bash
php artisan test --compact
```

## Paper Import

Papers are imported from [mathkang.org](https://mathkang.org). Question images are extracted from the official PDFs and correct answers are scraped from the solutions pages.

```bash
# Import a single paper
php artisan kangourou:import-paper {year} {level}

# Import all papers (2004–2025, all levels)
php artisan kangourou:import-all
```

## Scoring System

Scores follow the official Kangourou rules and are customizable per session:

| Tier | Questions | Correct | Incorrect |
|---|---|---|---|
| 1 | 1–8 | +3 | -0.75 (default) |
| 2 | 9–16 | +4 | -1 (default) |
| 3 | 17–24 | +5 | -1.25 (default) |
| 4 | 25–26 | +1 bonus | only if Q1–24 all correct |

## License

MIT
