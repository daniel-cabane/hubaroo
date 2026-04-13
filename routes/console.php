<?php

use App\Models\KangourouSession;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    KangourouSession::where('status', 'active')
        ->where('expires_at', '<=', now())
        ->update(['status' => 'expired']);
})->everyMinute()->name('expire-kangourou-sessions');
