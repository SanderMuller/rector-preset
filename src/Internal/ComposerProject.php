<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Composer\InstalledVersions;
use RuntimeException;

/**
 * The Composer installation of the project that runs Rector.
 *
 * One process can hold several Composer installations: during a Rector run, rector/rector registers
 * its own bundled vendor first, and tools such as Herd add more. InstalledVersions::getRootPackage()
 * then returns rector/rector-src, and InstalledVersions::isInstalled() also sees Rector's bundled
 * packages. This class picks the one installation that contains this package.
 *
 * @internal
 */
final readonly class ComposerProject
{
    public const string PACKAGE_NAME = 'sandermuller/rector-preset';

    /**
     * @param array<string, mixed> $versions package name => version data, root package included
     */
    public function __construct(
        public string $rootName,
        public string $rootPath,
        private array $versions,
    ) {}

    public static function current(): self
    {
        foreach (InstalledVersions::getAllRawData() as $installation) {
            $root = $installation['root'];

            if ($root['name'] === self::PACKAGE_NAME || isset($installation['versions'][self::PACKAGE_NAME])) {
                return new self($root['name'], $root['install_path'], $installation['versions']);
            }
        }

        throw new RuntimeException(sprintf('No Composer installation contains "%s".', self::PACKAGE_NAME));
    }

    /**
     * Whether a dependency is installed. The root package never counts: an extension that runs the
     * preset on its own source must not enable its own set.
     */
    public function isInstalled(string $package): bool
    {
        if ($package === $this->rootName) {
            return false;
        }

        if (! isset($this->versions[$package]) || ! is_array($this->versions[$package])) {
            return false;
        }

        // A package that is only replaced or provided by another one has no install of its own.
        return isset($this->versions[$package]['version']) || isset($this->versions[$package]['install_path']);
    }
}
