<?php

declare(strict_types=1);

namespace ArtisanSdk\Bench\Providers;

use Illuminate\Support\ServiceProvider;

class Configs extends ServiceProvider
{
    /**
     * Package namespace.
     *
     * @var string
     */
    public const PACKAGE = 'artisansdk/bench';

    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/rules.php' => config_path(static::PACKAGE.'/rules.php'),
        ], 'config');
    }

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/rules.php',
            static::PACKAGE.'::rules'
        );
    }
}
