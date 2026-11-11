<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use RuntimeException;

/**
 * @internal
 */
final class CacheDirectory
{
    /**
     * Creates the directory when it is missing and returns it.
     *
     * Rector asserts that containerCacheDirectory already exists and never creates it, so a fresh
     * clone or a CI runner would fail before parsing a file. Every parallel worker loads the config
     * again, so a second process can create the directory between the checks: that is not an error.
     */
    public static function ensure(string $directory): string
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the Rector cache directory "%s".', $directory));
        }

        return $directory;
    }
}
