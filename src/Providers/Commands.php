<?php

namespace ArtisanSdk\Bench\Providers;

use ArtisanSdk\Bench\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class Commands extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->load(__DIR__.'/../Console', Command::class));
        }
    }

    /**
     * Register bindings in the container.
     */
    public function register()
    {
    }

    /**
     * Register all of the commands in the given directory.
     *
     * @param array|string $paths
     * @param string       $class filter
     *
     * @return string[]
     */
    protected function load($paths, $class): array
    {
        $paths = $this->normalizePaths($paths);

        $namespace = (new ReflectionClass($class))->getNamespaceName();
        $parts = explode('\\', $namespace);
        $parts = array_slice($parts, 0, count($parts) - 1);
        $namespace = implode('\\', $parts);

        $base_path = realpath(__DIR__.'/../');

        $classes = [];

        foreach ((new Finder())->in($paths)->files() as $file) {
            $subclass = $namespace.str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($file->getPathname(), $base_path)
            );

            if (is_subclass_of($subclass, $class) &&
                ! (new ReflectionClass($subclass))->isAbstract()) {
                $classes[] = $subclass;
            }
        }

        return $classes;
    }

    protected function normalizePaths($paths): array
    {
        $paths = array_unique(is_array($paths) ? $paths : (array) $paths);

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return [];
        }

        foreach ($paths as &$path) {
            $path = realpath($path);
        }

        return $paths;
    }
}
