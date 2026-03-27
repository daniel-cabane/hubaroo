<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenAI\Client;
use OpenAI\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return (new Factory)
                ->withApiKey(config('services.openai.api_key'))
                ->make();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
