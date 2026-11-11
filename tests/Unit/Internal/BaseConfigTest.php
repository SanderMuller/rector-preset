<?php declare(strict_types=1);

use SanderMuller\RectorPreset\Internal\BaseConfig;
use SanderMuller\RectorPreset\Internal\CacheDirectory;
use SanderMuller\RectorPreset\Internal\CpuCores;
use SanderMuller\RectorPreset\Internal\PreparedSets;
use SanderMuller\RectorPreset\Internal\ProjectRoot;
use SanderMuller\RectorPreset\Tests\Support\BuilderInspector;

function temporaryProject(): string
{
    $directory = sys_get_temp_dir() . '/rector-preset-' . bin2hex(random_bytes(6));
    mkdir($directory);

    return (string) realpath($directory);
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        assert($item instanceof SplFileInfo);
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

function allPreparedSets(bool $enabled): PreparedSets
{
    return new PreparedSets(
        deadCode: $enabled,
        codeQuality: $enabled,
        codingStyle: $enabled,
        typeDeclarations: $enabled,
        typeDeclarationDocblocks: $enabled,
        privatization: $enabled,
        carbon: $enabled,
        rectorPreset: $enabled,
        phpunitCodeQuality: $enabled,
    );
}

function fixedCores(int $cores, bool $ci = true): CpuCores
{
    return new CpuCores(static fn (): ?string => null, static fn (): string => (string) $cores, static fn (): bool => $ci);
}

beforeEach(function (): void {
    $this->root = temporaryProject();
});

afterEach(function (): void {
    removeDirectory($this->root);
});

it('creates the default container cache directory under the project root', function (): void {
    $builder = BaseConfig::configure($this->root, null, fixedCores(4), allPreparedSets(false));
    $inspector = new BuilderInspector($builder);

    expect($inspector->get('containerCacheDirectory'))->toBe($this->root . '/.cache/phpstan')
        ->and($this->root . '/.cache/phpstan')
        ->toBeDirectory()
        ->and($inspector->get('cacheDirectory'))->toBe($this->root . '/.cache/rector');
});

it('resolves a relative container cache directory against the project root', function (string $given): void {
    expect(BaseConfig::resolveContainerCacheDirectory('/project', $given))->toBe('/project/.cache/rectorContainer');
})->with(['.cache/rectorContainer', './.cache/rectorContainer', '.cache/rectorContainer/']);

it('keeps an absolute container cache directory', function (): void {
    expect(BaseConfig::resolveContainerCacheDirectory('/project', '/tmp/cache/'))->toBe('/tmp/cache');
});

it('accepts a container cache directory that already exists', function (): void {
    mkdir($this->root . '/.cache/phpstan', recursive: true);

    expect(CacheDirectory::ensure($this->root . '/.cache/phpstan'))->toBe($this->root . '/.cache/phpstan');
});

it('throws when the container cache directory cannot be created', function (): void {
    file_put_contents($this->root . '/blocker', 'a file, not a directory');

    CacheDirectory::ensure($this->root . '/blocker/cache');
})->throws(RuntimeException::class, 'Unable to create the Rector cache directory');

it('sets the shared parallel, memory, import and formatting options', function (): void {
    $inspector = new BuilderInspector(BaseConfig::configure($this->root, null, fixedCores(6), allPreparedSets(false)));

    expect($inspector->get('parallel'))->toBeTrue()
        ->and($inspector->get('parallelTimeoutSeconds'))->toBe(300)
        ->and($inspector->get('parallelMaxNumberOfProcess'))->toBe(6)
        ->and($inspector->get('parallelJobSize'))->toBe(15)
        ->and($inspector->get('memoryLimit'))->toBe('3G')
        ->and($inspector->get('importNames'))->toBeTrue()
        ->and($inspector->get('removeUnusedImports'))->toBeTrue()
        ->and($inspector->get('isFluentNewLine'))->toBeTrue();
});

it('lets a repo override the overwriting options after the preset', function (): void {
    $builder = BaseConfig::configure($this->root, null, fixedCores(6), allPreparedSets(false))
        ->withParallel(maxNumberOfProcess: 3)
        ->withMemoryLimit('1G')
        ->withFluentCallNewLine(false);
    $inspector = new BuilderInspector($builder);

    expect($inspector->get('parallelMaxNumberOfProcess'))->toBe(3)
        ->and($inspector->get('memoryLimit'))->toBe('1G')
        ->and($inspector->get('isFluentNewLine'))->toBeFalse();
});

it('maps the prepared-set flags to sets', function (): void {
    $none = new BuilderInspector(BaseConfig::configure($this->root, null, fixedCores(4), allPreparedSets(false)));
    $all = new BuilderInspector(BaseConfig::configure($this->root, null, fixedCores(4), allPreparedSets(true)));

    $preparedFiles = array_values(array_diff($all->sets(), $none->sets()));

    expect($preparedFiles)->toHaveCount(9)
        ->and(array_filter($preparedFiles, is_file(...)))->toHaveCount(9);
});

it('resolves the project root from an override', function (): void {
    expect(ProjectRoot::resolve($this->root . '/.'))->toBe($this->root);
});

it('resolves the project root from Composer by default', function (): void {
    expect(ProjectRoot::resolve())->toBe((string) realpath(dirname(__DIR__, 3)));
});

it('rejects a project root that does not exist', function (): void {
    ProjectRoot::resolve($this->root . '/missing');
})->throws(RuntimeException::class, 'is not a directory');
