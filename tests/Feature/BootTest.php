<?php declare(strict_types=1);

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use SanderMuller\RectorPreset\Tests\Support\PresetHarness;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Runs the real Rector binary with each preset on a copy of a fixture project. This proves what the
 * unit tests cannot: that every set file resolves, that no skipped rule is missing, and that Rector
 * accepts the config as a whole (for example the level-method check).
 *
 * The extensions this package detects are dev dependencies here. The CI job "without extensions"
 * removes them and runs this file again, which proves the class_exists() guards.
 */

function copyFixture(string $fixture, string $target): void
{
    $source = __DIR__ . '/../Fixtures/' . $fixture;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($items as $item) {
        assert($item instanceof SplFileInfo);
        $destination = $target . '/' . substr($item->getPathname(), strlen($source) + 1);
        $item->isDir() ? @mkdir($destination, recursive: true) : copy($item->getPathname(), $destination);
    }
}

/**
 * Runs Rector in dry-run mode and normalises its JSON output.
 *
 * Rector exits with 0 when nothing changes and 2 when a dry run finds changes. When laravel/pao
 * detects an AI agent, it replaces Rector's JSON with its own agent format, which reports config
 * errors under "fatal_errors". Both formats are accepted.
 *
 * @return array{exit: int, changed_files: int, changed: list<string>, errors: list<string>}
 */
function runRector(string $projectRoot, string $presetCall): array
{
    $packageRoot = dirname(__DIR__, 2);
    writePresetConfig($projectRoot, $presetCall);

    $process = new Process(
        [(string) new PhpExecutableFinder()->find(), '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED), $packageRoot . '/vendor/bin/rector', 'process', '--dry-run', '--output-format=json', '--no-progress-bar', '--config=' . $projectRoot . '/rector.php'],
        $packageRoot,
        timeout: 300,
    );
    $process->run();

    $result = json_decode($process->getOutput(), true);

    if (! is_array($result)) {
        return [
            'exit' => (int) $process->getExitCode(),
            'changed_files' => -1,
            'changed' => [],
            'errors' => [trim($process->getErrorOutput() . "\n" . $process->getOutput())],
        ];
    }

    $errors = [];

    foreach ([...(array) ($result['errors'] ?? []), ...(array) ($result['fatal_errors'] ?? [])] as $error) {
        $message = is_string($error) ? $error : (string) json_encode($error);

        if (! str_starts_with($message, 'Deprecated: ') && ! str_starts_with($message, 'PHP Deprecated: ')) {
            $errors[] = $message;
        }
    }

    // prefer-lowest installs old transitive packages that print PHP deprecations in Rector's worker
    // processes. Those are not preset errors.
    $stderr = trim((string) preg_replace('/^(PHP )?Deprecated: .*$\\R?/m', '', $process->getErrorOutput()));

    if ($stderr !== '') {
        $errors[] = $stderr;
    }

    /** @var list<string> $changed */
    $changed = $result['changed_files'] ?? [];

    return [
        'exit' => (int) $process->getExitCode(),
        'changed_files' => count($changed),
        'changed' => $changed,
        'errors' => $errors,
    ];
}

/**
 * Runs Rector in dry-run mode and returns the first line of each warning it prints.
 *
 * Rector prints its warnings, for example about a skipped rule that no set registers, only in the
 * console format. laravel/pao forces the JSON format when it detects an AI agent, so PAO_DISABLE
 * turns it off.
 *
 * @return list<string>
 */
function rectorWarnings(string $projectRoot, string $presetCall): array
{
    $packageRoot = dirname(__DIR__, 2);
    writePresetConfig($projectRoot, $presetCall);

    $process = new Process(
        [(string) new PhpExecutableFinder()->find(), '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED), $packageRoot . '/vendor/bin/rector', 'process', '--dry-run', '--output-format=console', '--no-progress-bar', '--config=' . $projectRoot . '/rector.php'],
        $packageRoot,
        ['PAO_DISABLE' => '1'],
        timeout: 300,
    );
    $process->run();

    preg_match_all('/^\s*\[WARNING\].*$/m', $process->getOutput() . "\n" . $process->getErrorOutput(), $matches);

    return array_map(trim(...), $matches[0]);
}

function writePresetConfig(string $projectRoot, string $presetCall): void
{
    $config = <<<PHP
        <?php declare(strict_types=1);

        use SanderMuller\\RectorPreset\\Preset;

        return {$presetCall};
        PHP;
    file_put_contents($projectRoot . '/rector.php', $config);
}

beforeEach(function (): void {
    $this->root = PresetHarness::temporaryRoot();
});

afterEach(function (): void {
    PresetHarness::remove($this->root);
});

it('boots the package preset on a package project', function (): void {
    copyFixture('package-project', $this->root);

    $result = runRector($this->root, 'Preset::package(projectRoot: __DIR__)->withPhpSets(php84: true)');

    expect($result['errors'])
        ->toBeEmpty()
        ->and($result['exit'])->toBeIn([0, 2]);
});

it('boots the app preset on a Laravel app and never touches migrations or bootstrap/cache', function (): void {
    copyFixture('laravel-app', $this->root);

    $result = runRector($this->root, 'Preset::app(projectRoot: __DIR__)->withPhpSets(php84: true)');

    $touched = array_filter(
        $result['changed'],
        static fn (string $file): bool => str_contains($file, 'database/migrations') || str_contains($file, 'bootstrap/cache'),
    );

    expect($result['errors'])
        ->toBeEmpty()
        ->and($result['exit'])->toBeIn([0, 2])
        ->and($result['changed_files'])->toBeGreaterThan(0)
        ->and($touched)
        ->toBeEmpty();
});

it('proves the fixture migration would change if it were scanned', function (): void {
    copyFixture('laravel-app', $this->root);

    $result = runRector($this->root, "Preset::app(projectRoot: __DIR__)->withPaths([__DIR__ . '/database/migrations'])");

    expect($result['errors'])
        ->toBeEmpty()
        ->and($result['changed'])->toHaveCount(1)
        ->and($result['changed'][0])->toContain('database/migrations');
});

it('accepts withTypeCoverageDocblockLevel() on the app preset', function (): void {
    copyFixture('laravel-app', $this->root);

    $result = runRector($this->root, 'Preset::app(projectRoot: __DIR__)->withTypeCoverageDocblockLevel(2)');

    expect($result['errors'])
        ->toBeEmpty();
});

it('rejects withTypeCoverageDocblockLevel() on the package preset unless typeDeclarationDocblocks is off', function (): void {
    copyFixture('package-project', $this->root);

    $rejected = runRector($this->root, 'Preset::package(projectRoot: __DIR__)->withTypeCoverageDocblockLevel(2)');
    $accepted = runRector($this->root, 'Preset::package(projectRoot: __DIR__, typeDeclarationDocblocks: false)->withTypeCoverageDocblockLevel(2)');

    expect(implode("\n", $rejected['errors']))->toContain('type declaration docblocks')
        ->and($accepted['errors'])
        ->toBeEmpty();
});

it('runs the preset without a Rector warning', function (string $fixture, string $presetCall): void {
    copyFixture($fixture, $this->root);

    expect(rectorWarnings($this->root, $presetCall))->toBeEmpty();
})->with([
    'package' => ['package-project', 'Preset::package(projectRoot: __DIR__)->withPhpSets(php84: true)'],
    'app' => ['laravel-app', 'Preset::app(projectRoot: __DIR__)->withPhpSets(php84: true)'],
]);

it('proves the warning check sees a skipped rule that no set registers', function (): void {
    copyFixture('laravel-app', $this->root);

    // The app preset turns codingStyle off, and only that set registers this rule.
    $warnings = rectorWarnings(
        $this->root,
        'Preset::app(projectRoot: __DIR__)->withPhpSets(php84: true)->withSkip([' . CatchExceptionNameMatchingTypeRector::class . '::class])',
    );

    expect(implode("\n", $warnings))->toContain('never registered');
});

it('boots the README example', function (): void {
    copyFixture('laravel-app', $this->root);

    $readmeExample = <<<'PHP'
        Preset::app(projectRoot: __DIR__, unskip: [Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector::class])
                ->withPhpSets(php84: true)
                ->withSkip([__DIR__ . '/app/Models/Example.php'])
                ->withFluentCallNewLine(false)
        PHP;

    $result = runRector($this->root, $readmeExample);

    expect($result['errors'])
        ->toBeEmpty();
});
