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
        if (! is_dir($directory) && ! self::make($directory) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the Rector cache directory "%s".', $directory));
        }

        return $directory;
    }

    /**
     * mkdir() without its warning: the caller reports the failure with the directory name. An error
     * handler is used instead of @, because a test runner or error reporter can still see an
     * @-suppressed warning.
     */
    private static function make(string $directory): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return mkdir($directory, 0o777, true);
        } finally {
            restore_error_handler();
        }
    }
}
