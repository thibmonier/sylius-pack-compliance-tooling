#!/usr/bin/env php
<?php

// Tests of the Symfony Flex recipe of the pack, without dependency: php tests/recipe.php (exit code 1 on failure).
// recipe/<pack>/<version>/ mirrors the layout of symfony/recipes-contrib: manifest.json plus the files of config/.
// bin/check-recipe compares it with etc/gestures.json (the single source), bin/build-recipe-endpoint turns it into the
// JSON that Flex reads from an endpoint.

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
    $directory = sys_get_temp_dir() . '/recipe-' . bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);
    $temporaryDirectories[] = $directory;

    return $directory;
}

function copyTree(string $from, string $to): void
{
    mkdir($to, 0777, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ('.' === $entry || '..' === $entry) {
            continue;
        }
        is_dir($from . '/' . $entry) ? copyTree($from . '/' . $entry, $to . '/' . $entry) : copy($from . '/' . $entry, $to . '/' . $entry);
    }
}

function removeTree(string $directory): void
{
    foreach (scandir($directory) ?: [] as $entry) {
        if ('.' === $entry || '..' === $entry) {
            continue;
        }
        is_dir($directory . '/' . $entry) ? removeTree($directory . '/' . $entry) : unlink($directory . '/' . $entry);
    }
    rmdir($directory);
}

/** A copy of the files the recipe checks (gestures file, recipe directory); $mutate receives the root of the copy. */
function fixture(string $root, array &$temporaryDirectories, ?callable $mutate = null): string
{
    $directory = temporaryDirectory($temporaryDirectories);
    mkdir($directory . '/etc');
    copy($root . '/etc/gestures.json', $directory . '/etc/gestures.json');
    copyTree($root . '/recipe', $directory . '/recipe');
    if (null !== $mutate) {
        $mutate($directory);
    }

    return $directory;
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

$check = $root . '/bin/check-recipe';
$build = $root . '/bin/build-recipe-endpoint';
$gestures = json_decode((string) file_get_contents($root . '/etc/gestures.json'), true, 512, JSON_THROW_ON_ERROR);
$pack = $gestures['pack'];

/** The directory of the recipe in a copy: recipe/<vendor>/<package>/<version>. */
$recipeDirectory = static function (string $directory) use ($pack): string {
    $versions = glob($directory . '/recipe/' . $pack . '/*', GLOB_ONLYDIR) ?: [];
    if (1 !== count($versions)) {
        throw new RuntimeException('expected one recipe version under recipe/' . $pack);
    }

    return $versions[0];
};

$bundles = [];
foreach ($gestures['plugins'] as $plugin) {
    array_push($bundles, ...$plugin['bundles']);
}

// bin/check-recipe

$test('the recipe as committed matches the gestures file', static function () use ($check, $root): array {
    [$code, , $errors] = run($check, ['--root=' . $root]);

    return [0 === $code, $errors];
});

$test('the manifest registers exactly the bundles of the gestures file, for all environments', static function () use ($recipeDirectory, $root, $bundles): array {
    $manifest = json_decode((string) file_get_contents($recipeDirectory($root) . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $expected = array_fill_keys($bundles, ['all']);
    $found = $manifest['bundles'] ?? [];
    ksort($expected);
    ksort($found);

    return [[] !== $bundles && $expected === $found, json_encode($found)];
});

$test('the manifest copies the files of the recipe into the configuration directory', static function () use ($recipeDirectory, $root): array {
    $manifest = json_decode((string) file_get_contents($recipeDirectory($root) . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

    return [['config/' => '%CONFIG_DIR%/'] === ($manifest['copy-from-recipe'] ?? null), json_encode($manifest['copy-from-recipe'] ?? null)];
});

$test('a bundle missing from the manifest is refused and named', static function () use ($check, $root, $recipeDirectory, $bundles, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory, $bundles): void {
        $path = $recipeDirectory($directory) . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        unset($manifest['bundles'][$bundles[0]]);
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $bundles[0]), $errors];
});

$test('a bundle in the manifest that the gestures file does not have is refused and named', static function () use ($check, $root, $recipeDirectory, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory): void {
        $path = $recipeDirectory($directory) . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $manifest['bundles']['Acme\\ExtraBundle\\AcmeExtraBundle'] = ['all'];
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'AcmeExtraBundle'), $errors];
});

$test('a manifest without copy-from-recipe is refused', static function () use ($check, $root, $recipeDirectory, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory): void {
        $path = $recipeDirectory($directory) . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        unset($manifest['copy-from-recipe']);
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'copy-from-recipe'), $errors];
});

$test('a file of the recipe whose content differs from the gestures is refused and named', static function () use ($check, $root, $recipeDirectory, &$temporaryDirectories): array {
    $name = '';
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory, &$name): void {
        $files = glob($recipeDirectory($directory) . '/config/packages/*.yaml') ?: [];
        $name = basename($files[0]);
        file_put_contents($files[0], "imports:\n    - { resource: \"@Elsewhere/config/config.yaml\" }\n");
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $name), $errors];
});

$test('a file that the gestures write and the recipe lacks is refused and named', static function () use ($check, $root, $recipeDirectory, &$temporaryDirectories): array {
    $name = '';
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory, &$name): void {
        $files = glob($recipeDirectory($directory) . '/config/routes/*.yaml') ?: [];
        $name = basename($files[0]);
        unlink($files[0]);
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $name), $errors];
});

$test('a file in the recipe that the gestures do not write is refused and named', static function () use ($check, $root, $recipeDirectory, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory): void {
        file_put_contents($recipeDirectory($directory) . '/config/packages/acme_extra.yaml', "imports: []\n");
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'acme_extra.yaml'), $errors];
});

$test('a recipe that registers a bundle provided by the recipe of another package is refused', static function () use ($check, $root, $recipeDirectory, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (string $directory) use ($recipeDirectory): void {
        $path = $recipeDirectory($directory) . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $manifest['bundles']['Symfony\\AI\\AiBundle\\AiBundle'] = ['all'];
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
    });
    [$code, , $errors] = run($check, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'AiBundle'), $errors];
});

// bin/build-recipe-endpoint

$test('the endpoint has an index naming the pack and one recipe file with the manifest and the files', static function () use ($build, $root, $recipeDirectory, $pack, &$temporaryDirectories): array {
    $out = temporaryDirectory($temporaryDirectories);
    [$code, , $errors] = run($build, ['--root=' . $root, '--out=' . $out, '--url=http://127.0.0.1:8765']);
    $version = basename($recipeDirectory($root));
    $index = json_decode((string) @file_get_contents($out . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    $recipeFile = $out . '/' . str_replace('/', '.', $pack) . '.' . $version . '.json';
    $recipe = json_decode((string) @file_get_contents($recipeFile), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($recipeDirectory($root) . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $entry = $recipe['manifests'][$pack] ?? [];

    $ok = 0 === $code
        && [$version] === ($index['recipes'][$pack] ?? null)
        && true === $index['is_contrib']
        && str_starts_with($index['_links']['recipe_template'], 'http://127.0.0.1:8765/')
        && $manifest === ($entry['manifest'] ?? null)
        && [] !== ($entry['files'] ?? [])
        && 1 === preg_match('/^[0-9a-f]{40}$/', (string) ($entry['ref'] ?? ''));

    return [$ok, $errors . json_encode($index)];
});

$test('each file of the endpoint carries the lines of the file of the recipe', static function () use ($build, $root, $recipeDirectory, $pack, &$temporaryDirectories): array {
    $out = temporaryDirectory($temporaryDirectories);
    run($build, ['--root=' . $root, '--out=' . $out, '--url=http://127.0.0.1:8765']);
    $recipe = json_decode((string) file_get_contents($out . '/' . str_replace('/', '.', $pack) . '.' . basename($recipeDirectory($root)) . '.json'), true, 512, JSON_THROW_ON_ERROR);
    $files = $recipe['manifests'][$pack]['files'] ?? [];
    foreach ($files as $path => $file) {
        $original = (string) file_get_contents($recipeDirectory($root) . '/' . $path);
        if (implode("\n", $file['contents']) !== $original || false !== $file['executable']) {
            return [false, $path];
        }
    }

    return [[] !== $files && array_keys($files) === array_values(array_filter(array_keys($files), static fn (string $path): bool => str_starts_with($path, 'config/'))), json_encode(array_keys($files))];
});

foreach ($temporaryDirectories as $directory) {
    is_dir($directory) && removeTree($directory);
}

echo sprintf("%d tests, %d failed\n", $total, $failures);
exit(0 === $failures ? 0 : 1);
