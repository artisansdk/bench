<?php

declare(strict_types=1);

namespace ArtisanSdk\Bench\Console;

class Watch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watch
        {path* : The directory paths that should be watched.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Watch the project\'s PHP files for changes then re-run the test suites.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    }
}
