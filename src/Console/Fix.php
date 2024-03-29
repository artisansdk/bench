<?php

declare(strict_types=1);

namespace ArtisanSdk\Bench\Console;

use ArtisanSdk\Bench\Providers\Configs;
use PhpCsFixer\Config;
use PhpCsFixer\Console\Command\FixCommandExitStatusCalculator as Calculator;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Output\ErrorOutput;
use PhpCsFixer\Console\Output\ProcessOutput;
use PhpCsFixer\Console\Report\FixReport\ReportSummary;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Runner;
use PhpCsFixer\ToolInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;
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
     * Setup the environment to run the fixer.
     *
     * @param string $rules
     * @param string $cache
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
            \count($this->fixed),
            \count($this->errors->getInvalidErrors()),
            \count($this->errors->getExceptionErrors()),
            \count($this->errors->getLintErrors())
        );
    }

    /**
     * Render the error report.
     */
    protected function report(): void
    {
        $errors = new ErrorOutput($this->getOutput());

        $invalid = $this->errors->getInvalidErrors();
        if (\count($invalid) > 0) {
            $errors->listErrors('linting before fixing', $invalid);
        }

        $exception = $this->errors->getExceptionErrors();
        if (\count($exception) > 0) {
            $errors->listErrors('fixing', $exception);
        }

        $lint = $this->errors->getLintErrors();
        if (\count($lint) > 0) {
            $errors->listErrors('linting after fixing', $lint);
        }
    }

    /**
     * Render the summary report of files fixed.
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
    protected function start(): void
    {
        $this->stopwatch->start(__CLASS__);
    }

    /**
     * Stop the timer.
     */
    protected function stop(): void
    {
        $this->stopwatch->stop(__CLASS__);
    }

    /**
     * Get the timer.
     */
    protected function timer()
    {
        if (! $this->timer) {
            $this->timer = $this->stopwatch->getEvent(__CLASS__);
        }

        return $this->timer;
    }

    /**
     * Calculate the exit code.
     */
    protected function calculate(int $fixed, int $invalid, int $error, int $lint): int
    {
        return (new Calculator())
            ->calculate(
                $this->resolver()->isDryRun(),
                $fixed > 0,
                $invalid > 0,
                $error > 0,
                $lint > 0
            );
    }

    /**
     * Get the progressive output.
     */
    protected function progress(): ProcessOutput
    {
        if (! $this->progress) {
            $this->progress = new ProcessOutput(
                $this->getOutput(),
                $this->dispatcher,
                (new Terminal())->getWidth(),
                $this->finder->count()
            );
        }

        return $this->progress;
    }

    /**
     * Get the fixer.
     */
    protected function runner(): Runner
    {
        if (! $this->runner) {
            $resolver = $this->resolver();
            $this->runner = new Runner(
                $this->finder,
                $resolver->getFixers(),
                $resolver->getDiffer(),
                $this->dispatcher,
                $this->errors,
                $resolver->getLinter(),
                $resolver->isDryRun(),
                $resolver->getCacheManager(),
                $resolver->getDirectory(),
                $resolver->shouldStopOnViolation()
            );
        }

        return $this->runner;
    }

    /**
     * Get the config.
     *
     * @param string $rules
     * @param string $cache
     */
    protected function config(array $paths, string $rules = null, string $cache = null): Config
    {
        $path = $this->basepath(null === $cache ? '.php_cs.cache' : $cache);

        $folder = \dirname($path);
        if (! is_dir($folder)) {
            mkdir($folder);
        }

        if (! $this->config) {
            $this->config = (new Config())
                ->setRiskyAllowed(true)
                ->setRules($this->rules($rules))
                ->setFinder($this->finder($paths))
                ->setUsingCache(null !== $cache)
                ->setCacheFile($path);
        }

        return $this->config;
    }

    /**
     * Get the finder from the paths.
     *
     * @return \PhpCsFixer\Finder
     */
    protected function finder(array $paths): Finder
    {
        if (! $this->finder) {
            $finder = Finder::create()
                ->name('*.php')
                ->notName('*.blade.php')
                ->ignoreDotFiles(true)
                ->ignoreVCS(true);

            foreach ($paths as $path) {
                $path = $this->basepath($path);
                is_dir($path) ? $finder->in($path) : $finder->append([$path]);
            }

            $this->finder = $finder;
        }

        return $this->finder;
    }

    /**
     * Get the resolver.
     */
    protected function resolver(bool $pretend = false): ConfigurationResolver
    {
        if (! $this->resolver) {
            $this->resolver = new ConfigurationResolver($this->config, [
                'diff' => $pretend,
                'dry-run' => $pretend,
                'stop-on-violation' => false,
            ], getcwd(), $this->info);
        }

        return $this->resolver;
    }

    /**
     * Get the rules.
     *
     * @param string $path
     */
    protected function rules(string $path = null): array
    {
        if (! $this->rules) {
            if (\is_string($path)) {
                $this->rules = require_once $this->basepath($path);
            }

            $this->rules = config(Configs::PACKAGE.'::rules', []);
        }

        return $this->rules;
    }
}
