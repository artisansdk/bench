<?php

declare(strict_types=1);

namespace ArtisanSdk\Bench\Console;

use Illuminate\Console\Command as BaseCommand;
use Illuminate\Foundation\Application;

abstract class Command extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $prefix = 'bench';

    /**
     * Construct the command name with optional prefix.
     */
    public function __construct()
    {
        if (! $this->isStandalone()) {
            $this->signature = $this->prefix.':'.$this->signature;
        }

        parent::__construct();
    }

    /**
     * Is the application running as a standlone console.
     */
    protected function isStandalone(): bool
    {
        return Application::class !== \get_class(app());
    }

    /**
     * Get the base path.
     */
    protected function basepath(string $path): string
    {
        return getcwd().\DIRECTORY_SEPARATOR.$path;
    }
}
