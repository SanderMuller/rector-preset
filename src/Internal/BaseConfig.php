<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Rector\Config\RectorConfig;
use Rector\Configuration\RectorConfigBuilder;

/**
 * The settings both presets share: cache, parallel workers, memory, imports, attributes and the
 * prepared sets.
 *
 * @internal
 */
final class BaseConfig
{
    public const int PARALLEL_TIMEOUT_SECONDS = 300;

    public const int PARALLEL_JOB_SIZE = 15;

    public const string MEMORY_LIMIT = '3G';

    public const string CACHE_DIRECTORY = '.cache/rector';

    /**
     * PHPStan's tmpDir in 26 of 27 surveyed repos. Rector boots a PHPStan container and writes the
     * same reflection cache entries, so sharing the directory keeps one copy instead of two.
     */
    public const string CONTAINER_CACHE_DIRECTORY = '.cache/phpstan';

    public static function configure(
        string $projectRoot,
        ?string $containerCacheDirectory,
        CpuCores $cpuCores,
        PreparedSets $preparedSets,
    ): RectorConfigBuilder {
        $builder = RectorConfig::configure()
            ->withCache(
                cacheDirectory: $projectRoot . '/' . self::CACHE_DIRECTORY,
                containerCacheDirectory: CacheDirectory::ensure(
                    self::resolveContainerCacheDirectory($projectRoot, $containerCacheDirectory),
                ),
            )
            ->withParallel(self::PARALLEL_TIMEOUT_SECONDS, $cpuCores->workers(), self::PARALLEL_JOB_SIZE)
            ->withMemoryLimit(self::MEMORY_LIMIT)
            ->withImportNames()
            ->withAttributesSets()
            ->withFluentCallNewLine();

        return $preparedSets->applyTo($builder);
    }

    public static function resolveContainerCacheDirectory(string $projectRoot, ?string $directory): string
    {
        $directory ??= self::CONTAINER_CACHE_DIRECTORY;

        if (str_starts_with($directory, '/')) {
            return rtrim($directory, '/');
        }

        // Strip a leading "./" only: ltrim() with "./" would also eat the dot of ".cache".
        $relative = (string) preg_replace('#^(\./)+#', '', $directory);

        return $projectRoot . '/' . trim($relative, '/');
    }
}
