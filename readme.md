# Bench

A set of testing and package development tools for the developer work bench.

## Table of Contents

- [Installation](#installation)
- [Usage Guide](#usage-guide)
- [Running the Tests](#running-the-tests)
- [Licensing](#licensing)

# Installation

The tool chain installs into a PHP application like any other PHP package:

```bash
composer require artisansdk/bench
```

# Usage Guide

```
php artisan bench:fix [path] [--rules=/file/path] [--cache=/file/path] [--pretend]
php artisan bench:test [path] [--filter=] [--suite=] [--processes=] [--no-coverage]
php artisan bench:watch [path]
php artisan bench:report [--min-line-coverage=80] [--max-line-duplication=3] [--max-token-duplication=35]
```

# Running the Tests

The package is unit tested with 100% line coverage and path coverage. You can
run the tests by simply cloning the source, installing the dependencies, and then
running `./vendor/bin/phpunit`. Additionally included in the developer dependencies
are some Composer scripts which can assist with Code Styling and coverage reporting:

```bash
composer test
composer watch
composer fix
composer report
```

See the `composer.json` for more details on their execution and reporting output.
Note that `composer watch` relies upon [`watchman-make`](https://facebook.github.io/watchman/docs/install.html).
Additionally `composer report` assumes a Unix system to run line coverage reporting.
Configure the command setting the value for `min = 80` to set your minimum line
coverage requirements.

# Licensing

Copyright (c) 2018-2019 [Artisans Collaborative](https://artisanscollaborative.com)

This package is released under the MIT license. Please see the LICENSE file
distributed with every copy of the code for commercial licensing terms.
