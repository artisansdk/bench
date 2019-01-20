<?php

namespace ArtisanSdk\Bench\Console;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test
        {path* : The directory paths that should be watched.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the test suites including linting.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    }
}
