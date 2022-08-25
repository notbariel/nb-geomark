<?php

namespace App\Providers;

use App\Services\CountryShortcodeService;
use App\Services\GeoValidatorService;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\DomCrawler\Crawler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CountryShortcodeService::class, function () {
            return new CountryShortcodeService();
        });

        $this->app->bind(GeoValidatorService::class, function ($app) {
            return new GeoValidatorService(
                $app->make(CountryShortcodeService::class),
                new Crawler(),
                env('VITE_MAPBOX_API_TOKEN')
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
