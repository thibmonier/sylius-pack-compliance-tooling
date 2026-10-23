#!/usr/bin/env php
<?php

// Tests of bin/check-composition, without dependency: php tests/run.php (exit code 1 on failure).
// Each test copies the files of the pack into a temporary directory, breaks one rule and runs the checker.

declare(strict_types=1);

const PLUS_PHRASE = 'Sylius ' . 'Plus';

$root = dirname(__DIR__);
$script = $root . '/bin/check-composition';
$composition = json_decode((string) file_get_contents($root . '/etc/composition.json'), true, 512, JSON_THROW_ON_ERROR);
$temporaryDirectories = [];

/** @return array{int, string, string} exit code, stdout, stderr */
function runChecker(string $script, array $arguments): array
{
    $process = proc_open([PHP_BINARY, $script, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);

    return [proc_close($process), $output, $errors];
}

/** Copies the pack files to a fresh directory; $mutate receives them as arrays and strings and may change them. */
function fixture(string $root, array &$temporaryDirectories, ?callable $mutate = null): string
{
    $directory = sys_get_temp_dir() . '/check-composition-' . bin2hex(random_bytes(6));
    mkdir($directory . '/etc', 0777, true);
    $temporaryDirectories[] = $directory;

    $files = [
        'composer' => json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR),
        'composition' => json_decode((string) file_get_contents($root . '/etc/composition.json'), true, 512, JSON_THROW_ON_ERROR),
        'readme' => (string) file_get_contents($root . '/README.md'),
        'readme_fr' => (string) file_get_contents($root . '/README.fr.md'),
    ];
    if (null !== $mutate) {
        $mutate($files);
    }
    file_put_contents($directory . '/composer.json', json_encode($files['composer'], JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/etc/composition.json', json_encode($files['composition'], JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/README.md', $files['readme']);
    file_put_contents($directory . '/README.fr.md', $files['readme_fr']);

    return $directory;
}

/** A composer.lock as written after a resolution: the pack, then its members (each with its own requirements). */
function lock(array $composition, array $packages = [], array $memberRequires = []): string
{
    $entries = [['name' => $composition['pack'], 'type' => 'metapackage', 'require' => array_fill_keys($composition['members'], '*')]];
    foreach ($composition['members'] as $member) {
        $entries[] = ['name' => $member, 'require' => $memberRequires[$member] ?? ['php' => '^8.2']];
    }
    foreach ($packages as $package) {
        $entries[] = ['name' => $package, 'require' => ['php' => '^8.2']];
    }
    $path = sys_get_temp_dir() . '/check-composition-lock-' . bin2hex(random_bytes(6)) . '.lock';
    file_put_contents($path, json_encode(['packages' => $entries], JSON_THROW_ON_ERROR));

    return $path;
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

$member = $composition['members'][0];
$excluded = $composition['excluded'][0];

$test('the pack as committed is valid', static function () use ($script, $root, $temporaryDirectories): array {
    [$code, , $errors] = runChecker($script, ['--root=' . $root]);

    return [0 === $code, $errors];
});

$test('a lock with exactly the members passes', static function () use ($script, $root, $composition): array {
    [$code, , $errors] = runChecker($script, ['--root=' . $root, '--lock=' . lock($composition)]);

    return [0 === $code, $errors];
});

$test('an unexpected plugin in require is refused and named', static function () use ($script, $root, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files): void {
        $files['composer']['require']['tmonier/sylius-order-hold-plugin'] = '^1.0';
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'tmonier/sylius-order-hold-plugin'), $errors];
});

$test('a missing member is refused and named', static function () use ($script, $root, &$temporaryDirectories, $member): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($member): void {
        unset($files['composer']['require'][$member]);
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $member), $errors];
});

$test('an excluded plugin in require is refused and named', static function () use ($script, $root, &$temporaryDirectories, $excluded): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($excluded): void {
        $files['composer']['require'][$excluded] = '^1.0';
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $excluded) && str_contains($errors, 'excluded'), $errors];
});

$test('an excluded plugin in suggest is refused', static function () use ($script, $root, &$temporaryDirectories, $excluded): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($excluded): void {
        $files['composer']['suggest'][$excluded] = 'not part of a pack';
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, $excluded), $errors];
});

$test('an excluded plugin named in a README is refused', static function () use ($script, $root, &$temporaryDirectories, $excluded): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($excluded): void {
        $files['readme_fr'] .= "\nVoir aussi `" . $excluded . "`.\n";
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'README.fr.md') && str_contains($errors, $excluded), $errors];
});

$test('a README that cites a commercial offer is refused', static function () use ($script, $root, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files): void {
        $files['readme'] .= "\nCompared with " . PLUS_PHRASE . ".\n";
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'README.md'), $errors];
});

$test('a package that is not a metapackage is refused', static function () use ($script, $root, &$temporaryDirectories): array {
    $directory = fixture($root, $temporaryDirectories, static function (array &$files): void {
        $files['composer']['type'] = 'library';
    });
    [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

    return [1 === $code && str_contains($errors, 'metapackage'), $errors];
});

foreach ($composition['suggest'] as $suggested) {
    $test('suggested ' . $suggested . ' is in suggest and not in require', static function () use ($script, $root, $suggested): array {
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        return [isset($composer['suggest'][$suggested]) && !isset($composer['require'][$suggested]), 'composer.json'];
    });

    $test('suggested ' . $suggested . ' moved to require is refused', static function () use ($script, $root, &$temporaryDirectories, $suggested): array {
        $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($suggested): void {
            $files['composer']['require'][$suggested] = '^1.0';
        });
        [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $suggested), $errors];
    });

    $test('suggested ' . $suggested . ' dropped from suggest is refused', static function () use ($script, $root, &$temporaryDirectories, $suggested): array {
        $directory = fixture($root, $temporaryDirectories, static function (array &$files) use ($suggested): void {
            unset($files['composer']['suggest'][$suggested]);
        });
        [$code, , $errors] = runChecker($script, ['--root=' . $directory]);

        return [1 === $code && str_contains($errors, $suggested), $errors];
    });
}

$test('a locked plugin outside the pack is refused and named', static function () use ($script, $root, $composition): array {
    $lock = lock($composition, ['tmonier/sylius-order-hold-plugin']);
    [$code, , $errors] = runChecker($script, ['--root=' . $root, '--lock=' . $lock]);

    return [1 === $code && str_contains($errors, 'tmonier/sylius-order-hold-plugin'), $errors];
});

$test('a member that requires a plugin outside the pack fails and names the dependency', static function () use ($script, $root, $composition, $member): array {
    $dependency = 'tmonier/sylius-company-accounts-plugin';
    $lock = lock($composition, [$dependency], [$member => ['php' => '^8.2', $dependency => '^1.1']]);
    [$code, , $errors] = runChecker($script, ['--root=' . $root, '--lock=' . $lock]);

    return [1 === $code && str_contains($errors, $member . ' requires ' . $dependency), $errors];
});

$test('a member missing from the lock is refused and named', static function () use ($script, $root, $composition, $member): array {
    $lock = lock(['pack' => $composition['pack'], 'members' => array_values(array_diff($composition['members'], [$member]))]);
    [$code, , $errors] = runChecker($script, ['--root=' . $root, '--lock=' . $lock]);

    return [1 === $code && str_contains($errors, $member), $errors];
});

foreach ($temporaryDirectories as $directory) {
    foreach (glob($directory . '/{,etc/}*', GLOB_BRACE) ?: [] as $file) {
        is_file($file) && unlink($file);
    }
    @rmdir($directory . '/etc');
    @rmdir($directory);
}
foreach (glob(sys_get_temp_dir() . '/check-composition-lock-*.lock') ?: [] as $file) {
    unlink($file);
}

echo sprintf("%d tests, %d failed\n", $total, $failures);
exit(0 === $failures ? 0 : 1);
