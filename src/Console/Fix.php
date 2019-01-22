<?php

namespace ArtisanSdk\Bench\Console;

use ArtisanSdk\Bench\Providers\Configs;
use PhpCsFixer\Config;
use PhpCsFixer\Console\Command\FixCommandExitStatusCalculator as Calculator;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Output\ErrorOutput;
use PhpCsFixer\Console\Output\ProcessOutput;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Finder;
use PhpCsFixer\Report\ReportSummary;
use PhpCsFixer\Runner\Runner;
use PhpCsFixer\ToolInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

class Fix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix
        {path* : The directory paths that should be fixed.}
        {--rules= : The path to the fixer rules config file.}
        {--cache= : The path to the cache file. Omit to disable cache.}
        {--pretend : Only pretend to fix the files.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically fix the linting of PHP files within the project.';

    /**
     * Inject the dependencies.
     *
     * @param \PhpCsFixer\Error\ErrorsManager                    $errors
     * @param \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher
     * @param \Symfony\Component\Stopwatch\Stopwatch             $stopwatch
     * @param \PhpCsFixer\ToolInfo                               $info
     */
    public function __construct(ErrorsManager $errors, EventDispatcher $dispatcher, Stopwatch $stopwatch, ToolInfo $info)
    {
        parent::__construct();

        $this->errors = $errors;
        $this->dispatcher = $dispatcher;
        $this->stopwatch = $stopwatch;
        $this->info = $info;
        $this->rules = null;
        $this->resolver = null;
        $this->finder = null;
        $this->config = null;
        $this->runner = null;
        $this->timer = null;
        $this->progress = null;
        $this->fixed = [];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $mode = ($this->option('pretend') ? 'linter' : 'fixer');
        $this->info('Running '.$mode.'...');

        $this->setup(
            $this->argument('path'),
            $this->option('rules'),
            $this->option('cache'),
            (bool) $this->option('pretend')
        );

        $this->fix();

        $this->summarize($this->fixed);

        $this->report();

        return $this->calculate(
            count($this->fixed),
            count($this->errors->getInvalidErrors()),
            count($this->errors->getExceptionErrors())
        );
    }

    /**
     * Render the error report.
     */
    protected function report()
    {
        $errors = new ErrorOutput($this->getOutput());

        $invalid = $this->errors->getInvalidErrors();
        if (count($invalid) > 0) {
            $errors->listErrors('linting before fixing', $invalid);
        }

        $exception = $this->errors->getExceptionErrors();
        if (count($exception) > 0) {
            $errors->listErrors('fixing', $exception);
        }

        $lint = $this->errors->getLintErrors();
        if (count($lint) > 0) {
            $errors->listErrors('linting after fixing', $lint);
        }
    }

    /**
     * Render the summary report of files fixed.
     *
     * @param array $fixed
     *
     * @return mixed
     */
    protected function summarize(array $fixed)
    {
        $this->progress()->printLegend();

        $output = $this->getOutput();

        $report = $this->resolver()
            ->getReporter()
            ->generate(new ReportSummary(
                $fixed,
                $this->timer()->getDuration(),
                $this->timer()->getMemory(),
                OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity(),
                $this->resolver()->isDryRun(),
                $output->isDecorated()
            ));

        if ($output->isDecorated()) {
            return $output->write($report);
        }

        return $output->write($report, false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Fix the files.
     *
     * @return array
     */
    protected function fix(): array
    {
        $this->start();
        $this->fixed = $this->runner()->fix();
        $this->stop();

        return $this->fixed;
    }

    /**
     * Start the timer.
     */
    protected function start()
    {
        $this->stopwatch->start(__CLASS__);
    }

    /**
     * Stop the timer.
     */
    protected function stop()
    {
        $this->stopwatch->stop(__CLASS__);
    }

    /**
     * Get the timer.
     */
    protected function timer()
    {
        if ( ! $this->timer) {
            $this->timer = $this->stopwatch->getEvent(__CLASS__);
        }

        return $this->timer;
    }

    /**
     * Calculate the exit code.
     *
     * @param int $fixed
     * @param int $invalid
     * @param int $error
     *
     * @return int
     */
    protected function calculate(int $fixed, int $invalid, int $error): int
    {
        return (new Calculator())
            ->calculate(
                $this->resolver()->isDryRun(),
                $fixed > 0,
                $invalid > 0,
                $error > 0
            );
    }

    /**
     * Setup the environment to run the fixer.
     *
     * @param array  $paths
     * @param string $rules
     * @param string $cache
     * @param bool   $pretend
     *
     * @return \PhpCsFixer\Runnder\Runner
     */
    protected function setup(array $paths, $rules, string $cache = null, bool $pretend = false): Runner
    {
        $this->config($paths, $rules, $cache);

        $this->resolver($pretend);

        $this->progress();

        return $this->runner();
    }

    /**
     * Get the progressive output.
     *
     * @return \PhpCsFixer\Console\Output\ProcessOutput
     */
    protected function progress(): ProcessOutput
    {
        if ( ! $this->progress) {
            $this->progress = new ProcessOutput($this->getOutput(), $this->dispatcher, null, null);
        }

        return $this->progress;
    }

    /**
     * Get the fixer.
     *
     * @return \PhpCsFixer\Runner\Runner
     */
    protected function runner(): Runner
    {
        if ( ! $this->runner) {
            $this->runner = new Runner(
                $this->finder,
                $this->resolver()->getFixers(),
                $this->resolver()->getDiffer(),
                $this->dispatcher,
                $this->errors,
                $this->resolver()->getLinter(),
                $this->resolver()->isDryRun(),
                $this->resolver()->getCacheManager(),
                $this->resolver()->getDirectory(),
                $this->resolver()->shouldStopOnViolation()
            );
        }

        return $this->runner;
    }

    /**
     * Get the config.
     *
     * @param array  $paths
     * @param string $rules
     * @param string $cache
     *
     * @return \PhpCsFixer\Config
     */
    protected function config(array $paths, string $rules = null, string $cache = null): Config
    {
        $path = $this->basepath(is_null($cache) ? '.php_cs.cache' : $cache);

        $folder = dirname($path);
        if ( ! is_dir($folder)) {
            mkdir($folder);
        }

        if ( ! $this->config) {
            $this->config = (new Config())
                ->setRules($this->rules($rules))
                ->setFinder($this->finder($paths))
                ->setUsingCache( ! is_null($cache))
                ->setCacheFile($path);
        }

        return $this->config;
    }

    /**
     * Get the finder from the paths.
     *
     * @param array $paths
     *
     * @return \PhpCsFixer\Finder
     */
    protected function finder(array $paths): Finder
    {
        if ( ! $this->finder) {
            $finder = Finder::create()
                ->name('*.php')
                ->notName('*.blade.php')
                ->ignoreDotFiles(true)
                ->ignoreVCS(true);

            foreach ($paths as $path) {
                $finder->in($this->basepath($path));
            }

            $this->finder = $finder;
        }

        return $this->finder;
    }

    /**
     * Get the resolver.
     *
     * @param bool $pretend
     *
     * @return \PhpCsFixer\Console\ConfigurationResolver
     */
    protected function resolver(bool $pretend = false): ConfigurationResolver
    {
        if ( ! $this->resolver) {
            $this->resolver = new ConfigurationResolver($this->config, [
                'dry-run' => $pretend,
                'diff'    => $pretend,
            ], getcwd(), $this->info);
        }

        return $this->resolver;
    }

    /**
     * Get the rules.
     *
     * @param string $path
     *
     * @return array
     */
    protected function rules(string $path = null): array
    {
        if ( ! $this->rules) {
            if (is_string($path)) {
                $this->rules = require_once $this->basepath($path);
            }

            $this->rules = config(Configs::PACKAGE.'::rules', []);
        }

        return $this->rules;
    }
}
