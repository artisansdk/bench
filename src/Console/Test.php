<?php

namespace ArtisanSdk\Bench\Console;

use Symfony\Component\Process\Process;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test
        {path?* : The directory paths that should be linted.}
        {--rules= : The path to the fixer rules config file.}
        {--cache= : The path to the cache file. Omit to disable cache.}
        {--suite=* : The test suites that should be executed.}
        {--filter=* : The filter for which tests should be executed.}
        {--processes=1 : The number of test processes to run in parallel.}
        {--no-coverage=true : The tests should be ran without coverage (if enabled).}';

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
        $ansi = $this->option('ansi') || ! $this->option('no-ansi');
        $this->getOutput()->getFormatter()->setDecorated($ansi);

        $this->info('Running tests...');

        $this->test($ansi);

        $this->lint($ansi);
    }

    /**
     * Run the tests.
     *
     * @param bool $ansi
     */
    protected function test(bool $ansi)
    {
        $processes = (int) $this->option('processes');

        $runner = $processes > 1 ? 'paratest' : 'phpunit';

        $arguments = [
            $this->basepath('vendor/bin/'.$runner),
        ];

        if ('phpunit' === $runner) {
            $arguments[] = '--order-by=defects';
            $arguments[] = '--stop-on-defect';
        }

        if ('paratest' === $runner) {
            $arguments[] = '--processes='.$processes;
            $arguments[] = '--stop-on-failure';
        }

        if ($this->option('ansi', false)) {
            $arguments[] = '--colors'.('phpunit' === $runner ? '=always' : '');
        }

        if ($this->option('no-coverage', false)) {
            $arguments[] = '--no-coverage';
        }

        $suites = $this->option('suite', []);
        if (! empty($suites)) {
            $arguments[] = '--testsuite='.
                ('paratest' === $runner
                    ? head($suites)
                    : implode(',', $suites));
        }

        $filters = $this->option('filter', []);
        if (! empty($filters)) {
            $arguments[] = '--filter='.implode(',', $filters);
            if ('paratest' === $runner) {
                $arguments[] = '--functional';
            }
        }

        $process = new Process($arguments);

        $process->mustRun(function ($type, $buffer) {
            $output = Process::ERR === $type ? 'error' : 'write';
            $this->getOutput()->$output($buffer);
        });
    }

    /**
     * Lint the source paths.
     *
     * @param bool $ansi
     */
    protected function lint(bool $ansi)
    {
        $paths = $this->argument('path', []);
        if (! empty($paths)) {
            $this->getOutput()->write(PHP_EOL);

            $arguments = [
                'path'                         => $paths,
                $ansi ? '--ansi' : '--no-ansi' => true,
                '--pretend'                    => true,
            ];

            if ($cache = $this->option('cache')) {
                $arguments['--cache'] = (string) $cache;
            }

            if ($rules = $this->option('rules')) {
                $arguments['--rules'] = (string) $rules;
            }

            $prefix = $this->isStandalone() ? '' : $this->prefix.':';
            $this->call($prefix.'fix', $arguments);
        }
    }
}
