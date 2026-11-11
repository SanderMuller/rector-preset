<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use RuntimeException;

/**
 * @internal
 */
final class ProjectRoot
{
    /**
     * The root of the project that runs Rector: the override when given, else the root of the
     * Composer installation that contains this package (see ComposerProject).
     */
    public static function resolve(?string $override = null, ?ComposerProject $project = null): string
    {
        $path = $override ?? ($project ?? ComposerProject::current())->rootPath;
        $resolved = realpath($path);

        if ($resolved === false || ! is_dir($resolved)) {
            throw new RuntimeException(sprintf('The project root "%s" is not a directory.', $path));
        }

        return $resolved;
    }
}
