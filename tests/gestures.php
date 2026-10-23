#!/usr/bin/env php
<?php

// Tests of the installation gestures, without dependency: php tests/gestures.php (exit code 1 on failure).
// bin/apply-gestures applies etc/gestures.json to a project, bin/check-gestures compares it with the READMEs and
// bin/step names the plugin and the step of a command that fails.

declare(strict_types=1);

$root = dirname(__DIR__);
$temporaryDirectories = [];

/** @return array{int, string, string} exit code, stdout, stderr */
function run(string $script, array $arguments): array
{
    $process = proc_open([PHP_BINARY, $script, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);

    return [proc_close($process), $output, $errors];
}

function temporaryDirectory(array &$temporaryDirectories): string
{
    $directory = sys_get_temp_dir() . '/gestures-' . bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);
    $temporaryDirectories[] = $directory;

    return $directory;
}

/** A project directory shaped like Sylius-Standard: config/bundles.php with a few bundles, empty packages and routes. */
function project(array &$temporaryDirectories): string
{
    $directory = temporaryDirectory($temporaryDirectories);
    mkdir($directory . '/config/packages', 0777, true);
    mkdir($directory . '/config/routes', 0777, true);
    file_put_contents($directory . '/config/bundles.php', "<?php\n\nreturn [\n    Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle::class => ['all' => true],\n];\n");

    return $directory;
}

/** Copies the files of the pack that the checkers read; $mutate receives them as arrays and strings. */
function fixture(string $root, array &$temporaryDirectories, ?callable $mutate = null): string
{
    $directory = temporaryDirectory($temporaryDirectories);
    mkdir($directory . '/etc');
    $files = [
        'composer' => json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR),
        'composition' => json_decode((string) file_get_contents($root . '/etc/composition.json'), true, 512, JSON_THROW_ON_ERROR),
        'gestures' => json_decode((string) file_get_contents($root . '/etc/gestures.json'), true, 512, JSON_THROW_ON_ERROR),
        'readme' => (string) file_get_contents($root . '/README.md'),
        'readme_fr' => (string) file_get_contents($root . '/README.fr.md'),
    ];
    if (null !== $mutate) {
        $mutate($files);
    }
    file_put_contents($directory . '/composer.json', json_encode($files['composer'], JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/etc/composition.json', json_encode($files['composition'], JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/etc/gestures.json', json_encode($files['gestures'], JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/README.md', $files['readme']);
    file_put_contents($directory . '/README.fr.md', $files['readme_fr']);

    return $directory;
}

function removeTree(string $directory): void
{
    foreach (scandir($directory) ?: [] as $entry) {
        if ('.' === $entry || '..' === $entry) {
            continue;
        }
        $path = $directory . '/' . $entry;
        is_dir($path) ? removeTree($path) : unlink($path);
    }
    rmdir($directory);
}

$failures = 0;
$total = 0;
/** @param callable(): array{bool, string} $test returns [passed, detail] */
$test = static function (string $name, callable $test) use (&$failures, &$total): void {
    ++$total;
    try {
        [$passed, $detail] = $test();
    } catch (Throwable $exception) {
        [$passed, $detail] = [false, $exception->getMessage()];
    }
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . ($passed ? '' : ' -> ' . $detail) . "\n";
    $failures += $passed ? 0 : 1;
};

$apply = $root . '/bin/apply-gestures';
$check = $root . '/bin/check-gestures';
$step = $root . '/bin/step';
$gesturesFile = $root . '/etc/gestures.json';
$gestures = is_file($gesturesFile) ? json_decode((string) file_get_contents($gesturesFile), true, 512, JSON_THROW_ON_ERROR) : ['plugins' => []];
$first = $gestures['plugins'][0] ?? ['plugin' => 'none', 'bundles' => [], 'configuration' => [], 'routes' => []];

// bin/apply-gestures

$test('the gestures are applied to a project: bundles, configuration and routes', static function () use ($apply, $gesturesFile, $gestures, &$temporaryDirectories): array {
    $project = project($temporaryDirectories);
    [$code, , $errors] = run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);
    $bundles = (string) file_get_contents($project . '/config/bundles.php');
    foreach ($gestures['plugins'] as $plugin) {
        foreach ($plugin['bundles'] as $class) {
            if (!str_contains($bundles, $class . "::class => ['all' => true],")) {
                return [false, 'bundle not registered: ' . $class];
            }
        }
        foreach ($plugin['configuration'] as $configuration) {
            $file = $project . '/config/packages/' . $configuration['file'] . '.yaml';
            if (!is_file($file) || !str_contains((string) file_get_contents($file), $configuration['resource'])) {
                return [false, 'configuration missing: ' . $configuration['file']];
            }
        }
        foreach ($plugin['routes'] as $route) {
            $file = $project . '/config/routes/' . $route['file'] . '.yaml';
            if (!is_file($file) || !str_contains((string) file_get_contents($file), $route['resource'])) {
                return [false, 'routes missing: ' . $route['file']];
            }
        }
    }

    return [0 === $code && [] !== $gestures['plugins'], $errors];
});

$test('the bundles keep the order of the gestures file and the existing ones', static function () use ($apply, $gesturesFile, $gestures, &$temporaryDirectories): array {
    $project = project($temporaryDirectories);
    run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);
    $bundles = (string) file_get_contents($project . '/config/bundles.php');
    $expected = [];
    foreach ($gestures['plugins'] as $plugin) {
        array_push($expected, ...$plugin['bundles']);
    }
    $positions = array_map(static fn (string $class): int|false => strpos($bundles, $class . '::class'), $expected);
    $ordered = $positions === array_values(array_filter($positions, static fn ($position): bool => false !== $position)) && $positions === (static function (array $values): array {
        sort($values);

        return $values;
    })($positions);

    return [[] !== $expected && $ordered && str_contains($bundles, 'FrameworkBundle::class') && str_ends_with(trim($bundles), '];'), $bundles];
});

$test('applying the gestures twice registers each bundle once', static function () use ($apply, $gesturesFile, $first, &$temporaryDirectories): array {
    $project = project($temporaryDirectories);
    run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);
    [$code] = run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);
    $bundles = (string) file_get_contents($project . '/config/bundles.php');

    return [0 === $code && 1 === substr_count($bundles, ($first['bundles'][0] ?? 'none') . '::class'), $bundles];
});

$test('a configuration file already in the project is kept', static function () use ($apply, $gesturesFile, $first, &$temporaryDirectories): array {
    $project = project($temporaryDirectories);
    $file = $project . '/config/packages/' . ($first['configuration'][0]['file'] ?? 'none') . '.yaml';
    file_put_contents($file, "# written by the application\n");
    [$code] = run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);

    return [0 === $code && "# written by the application\n" === file_get_contents($file), (string) file_get_contents($file)];
});

$test('a project without config/bundles.php fails and names the plugin and the step', static function () use ($apply, $gesturesFile, $first, &$temporaryDirectories): array {
    $project = temporaryDirectory($temporaryDirectories);
    [$code, , $errors] = run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);

    return [1 === $code && str_contains($errors, 'FAIL plugin=' . $first['plugin'] . ' step=bundles'), $errors];
});

$test('a project without config/packages fails and names the plugin and the step', static function () use ($apply, $gesturesFile, $first, &$temporaryDirectories): array {
    $project = project($temporaryDirectories);
    removeTree($project . '/config/packages');
    [$code, , $errors] = run($apply, ['--project=' . $project, '--gestures=' . $gesturesFile]);

    return [1 === $code && str_contains($errors, 'FAIL plugin=' . $first['plugin'] . ' step=configuration'), $errors];
});

// bin/check-gestures

$test('the gestures of the pack as committed match the READMEs and the composition', static function () use ($check, $root): array {
    [$code, , $errors] = run($check, ['--root=' . $root]);

    return [0 === $code, $errors];
});

foreach (['README.md' => 'readme', 'README.fr.md' => 'readme_fr'] as $readmeFile => $key) {
    $test($readmeFile . ': a gesture missing from the README is refused and named', static function () use ($check, $root, $key, $readmeFile, $first, &$temporaryDirectories): array {
        $resource = $first['configuration'][0]['resource'] ?? 'none';
        $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($key, $resource): void {
            $files[$key] = str_replace($resource, '@Removed/config/config.yaml', $files[$key]);
        });
        [$code, , $errors] = run($check, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $readmeFile) && str_contains($errors, $resource), $errors];
    });

    $test($readmeFile . ': a gesture in the README that the gestures file does not have is refused and named', static function () use ($check, $root, $key, $readmeFile, &$temporaryDirectories): array {
        $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($key): void {
            $files[$key] .= "\n`Acme\\ExtraPlugin\\AcmeExtraPlugin::class` and `@AcmeExtraPlugin/config/routes.yaml`.\n";
        });
        [$code, , $errors] = run($check, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $readmeFile) && str_contains($errors, 'AcmeExtraPlugin'), $errors];
    });

    $test($readmeFile . ': a README without the migrations command is refused', static function () use ($check, $root, $key, $readmeFile, &$temporaryDirectories): array {
        $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($key): void {
            $files[$key] = str_replace('doctrine:migrations:migrate', 'doctrine:migrations:status', $files[$key]);
        });
        [$code, , $errors] = run($check, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $readmeFile) && str_contains($errors, 'migrations'), $errors];
    });
}

$test('a gesture for a plugin that is not a member of the pack is refused', static function () use ($check, $root, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files): void {
        $files['gestures']['plugins'][] = ['plugin' => 'tmonier/sylius-order-hold-plugin', 'bundles' => [], 'configuration' => [], 'routes' => []];
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'tmonier/sylius-order-hold-plugin'), $errors];
});

$test('a member without gestures is refused and named', static function () use ($check, $root, $first, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files): void {
        array_shift($files['gestures']['plugins']);
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $first['plugin']), $errors];
});

// Composer packages that a plugin needs besides the pack ("composer" in the gestures file)

$test('bin/apply-gestures --packages lists the extra Composer packages of the gestures file', static function () use ($apply, $first, &$temporaryDirectories): array {
    $directory = temporaryDirectory($temporaryDirectories);
    $custom = ['pack' => 'tmonier/sylius-pack-test', 'migrations' => 'doctrine:migrations:migrate', 'plugins' => [
        ['plugin' => $first['plugin'], 'composer' => ['symfony/lock', 'acme/bridge'], 'bundles' => [], 'configuration' => [], 'routes' => []],
        ['plugin' => 'tmonier/sylius-other-plugin', 'bundles' => [], 'configuration' => [], 'routes' => []],
    ]];
    file_put_contents($directory . '/gestures.json', json_encode($custom, JSON_THROW_ON_ERROR));
    [$code, $output] = run($apply, ['--packages', '--gestures=' . $directory . '/gestures.json']);

    return [0 === $code && "symfony/lock\nacme/bridge\n" === $output, $output];
});

foreach (['README.md' => 'readme', 'README.fr.md' => 'readme_fr'] as $readmeFile => $key) {
    $test($readmeFile . ': an extra Composer package missing from the README is refused and named', static function () use ($check, $root, $readmeFile, &$temporaryDirectories): array {
        $directory = fixture($root, $temporaryDirectories, static function (array &$files): void {
            $files['gestures']['plugins'][0]['composer'] = ['acme/extra-package'];
        });
        [$code, , $errors] = run($check, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $readmeFile) && str_contains($errors, 'acme/extra-package'), $errors];
    });

    $test($readmeFile . ': a composer require in the README that the gestures file does not have is refused and named', static function () use ($check, $root, $key, $readmeFile, &$temporaryDirectories): array {
        $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($key): void {
            $files[$key] .= "\n```bash\ncomposer require acme/other-package\n```\n";
        });
        [$code, , $errors] = run($check, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $readmeFile) && str_contains($errors, 'acme/other-package'), $errors];
    });
}

// bin/step

$test('a command that succeeds passes through with its output and exit code 0', static function () use ($step): array {
    [$code, $output] = run($step, ['--plugin=tmonier/sylius-x-plugin', '--step=demo', '--', PHP_BINARY, '-r', 'echo "hello\n";']);

    return [0 === $code && str_contains($output, 'hello'), $output];
});

$test('a command that fails keeps its exit code and the failure names the plugin and the step', static function () use ($step): array {
    [$code, , $errors] = run($step, ['--plugin=tmonier/sylius-x-plugin', '--step=migrations', '--', PHP_BINARY, '-r', 'exit(3);']);

    return [3 === $code && str_contains($errors, 'FAIL plugin=tmonier/sylius-x-plugin step=migrations'), $errors];
});

$test('with --plugin=auto the failure names the plugin found in the output of the command', static function () use ($step, $first): array {
    $command = 'fwrite(STDERR, "Root composer.json requires ' . $first['plugin'] . ' ^99.0 -> no matching package\n"); exit(2);';
    [$code, , $errors] = run($step, ['--plugin=auto', '--step=composer-require', '--', PHP_BINARY, '-r', $command]);

    return [2 === $code && str_contains($errors, 'FAIL plugin=' . $first['plugin'] . ' step=composer-require'), $errors];
});

$test('with --plugin=auto and no plugin in the output the failure names the pack', static function () use ($step, $root): array {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    [$code, , $errors] = run($step, ['--plugin=auto', '--step=lint-container', '--root=' . $root, '--', PHP_BINARY, '-r', 'fwrite(STDERR, "boom\n"); exit(1);']);

    return [1 === $code && str_contains($errors, 'FAIL plugin=' . $composer['name'] . ' step=lint-container'), $errors];
});

$test('with --plugin=auto a bundle namespace in the output is mapped to its plugin', static function () use ($step, $root, $first): array {
    $bundles = array_values(array_filter($first['bundles'], static fn (string $class): bool => str_starts_with($class, 'Tmonier\\')));
    if ([] === $bundles) {
        return [false, 'no bundle'];
    }
    $namespace = substr($bundles[0], 0, (int) strrpos($bundles[0], '\\'));
    $command = 'fwrite(STDERR, ' . var_export('In ' . $namespace . '\\Foo.php line 4: boom', true) . '); exit(1);';
    [$code, , $errors] = run($step, ['--plugin=auto', '--step=lint-container', '--root=' . $root, '--', PHP_BINARY, '-r', $command]);

    return [1 === $code && str_contains($errors, 'FAIL plugin=' . $first['plugin'] . ' step=lint-container'), $errors];
});

$test('with --plugin=auto a service or configuration alias of a plugin in the output is mapped to its plugin', static function () use ($step, $root, $first): array {
    $slug = str_replace('-', '_', substr($first['plugin'], strlen('tmonier/sylius-'), -strlen('-plugin')));
    $command = 'fwrite(STDERR, ' . var_export('Rate limiter "tmonier_' . $slug . '_requests" requires the Lock component', true) . '); exit(1);';
    [$code, , $errors] = run($step, ['--plugin=auto', '--step=database-create', '--root=' . $root, '--', PHP_BINARY, '-r', $command]);

    return [1 === $code && str_contains($errors, 'FAIL plugin=' . $first['plugin'] . ' step=database-create'), $errors];
});

// bin/check-routes (debug:router --format=json of a project with both packs)

$routes = static function (array &$temporaryDirectories, array $table): string {
    $path = temporaryDirectory($temporaryDirectories) . '/routes.json';
    $json = [];
    foreach ($table as $name => [$route, $method]) {
        $json[$name] = ['path' => $route, 'host' => 'ANY', 'method' => $method];
    }
    file_put_contents($path, json_encode($json, JSON_THROW_ON_ERROR));

    return $path;
};
$checkRoutes = $root . '/bin/check-routes';
$prefixes = '--prefixes=tmonier_,sylius_invoicing_';

$test('routes without conflict pass', static function () use ($checkRoutes, $routes, $prefixes, &$temporaryDirectories): array {
    $file = $routes($temporaryDirectories, ['tmonier_a_index' => ['/admin/a', 'GET'], 'tmonier_b_index' => ['/admin/b', 'GET'], 'sylius_home' => ['/', 'GET']]);
    [$code, , $errors] = run($checkRoutes, ['--file=' . $file, $prefixes]);

    return [0 === $code, $errors];
});

$test('two routes with the same path and method, one of a plugin, are refused and named', static function () use ($checkRoutes, $routes, $prefixes, &$temporaryDirectories): array {
    $file = $routes($temporaryDirectories, ['tmonier_a_index' => ['/admin/x', 'GET'], 'tmonier_b_index' => ['/admin/x', 'GET']]);
    [$code, , $errors] = run($checkRoutes, ['--file=' . $file, $prefixes]);

    return [1 === $code && str_contains($errors, 'tmonier_a_index') && str_contains($errors, 'tmonier_b_index') && str_contains($errors, '/admin/x'), $errors];
});

$test('a route for any method conflicts with a route for GET on the same path', static function () use ($checkRoutes, $routes, $prefixes, &$temporaryDirectories): array {
    $file = $routes($temporaryDirectories, ['tmonier_a_index' => ['/admin/x', 'ANY'], 'sylius_core_x' => ['/admin/x', 'GET']]);
    [$code, , $errors] = run($checkRoutes, ['--file=' . $file, $prefixes]);

    return [1 === $code && str_contains($errors, 'sylius_core_x'), $errors];
});

$test('the same path with different methods is not a conflict', static function () use ($checkRoutes, $routes, $prefixes, &$temporaryDirectories): array {
    $file = $routes($temporaryDirectories, ['tmonier_a_show' => ['/admin/x', 'GET'], 'tmonier_a_update' => ['/admin/x', 'POST|PUT']]);
    [$code, , $errors] = run($checkRoutes, ['--file=' . $file, $prefixes]);

    return [0 === $code, $errors];
});

$test('two routes of the application itself on the same path are not reported', static function () use ($checkRoutes, $routes, $prefixes, &$temporaryDirectories): array {
    $file = $routes($temporaryDirectories, ['sylius_a' => ['/x', 'GET'], 'sylius_b' => ['/x', 'GET']]);
    [$code, , $errors] = run($checkRoutes, ['--file=' . $file, $prefixes]);

    return [0 === $code, $errors];
});

foreach ($temporaryDirectories as $directory) {
    is_dir($directory) && removeTree($directory);
}

echo sprintf("%d tests, %d failed\n", $total, $failures);
exit(0 === $failures ? 0 : 1);
