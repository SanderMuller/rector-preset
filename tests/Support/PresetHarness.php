<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Tests\Support;

use FilesystemIterator;
use Rector\Configuration\RectorConfigBuilder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SanderMuller\RectorPreset\Internal\ComposerProject;
use SanderMuller\RectorPreset\Internal\CpuCores;
use SanderMuller\RectorPreset\Internal\PreparedSets;
use SanderMuller\RectorPreset\Internal\PresetFactory;
use SanderMuller\RectorPreset\Internal\Profile;
use SplFileInfo;

/**
 * Builds presets against a fake Composer project and a temporary project root.
 */
final class PresetHarness
{
    public static function temporaryRoot(): string
    {
        $directory = sys_get_temp_dir() . '/rector-preset-' . bin2hex(random_bytes(6));
        mkdir($directory);

        return (string) realpath($directory);
    }

    public static function remove(string $directory): void
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

    /**
     * @param list<string> $installed package names installed in the fake project
     */
    public static function project(string $root, array $installed = [], string $name = 'acme/project'): ComposerProject
    {
        $versions = [$name => ['version' => '1.0.0.0', 'install_path' => $root]];

        foreach ($installed as $package) {
            $versions[$package] = ['version' => '1.0.0.0', 'install_path' => $root . '/vendor/' . $package];
        }

        return new ComposerProject($name, $root, $versions);
    }

    public static function cpu(int $cores = 8): CpuCores
    {
        return new CpuCores(static fn (): ?string => null, static fn (): string => (string) $cores, static fn (): bool => true);
    }

    /**
     * Builds the preset the same way Preset::app() or Preset::package() does, with the profile's
     * defaults unless overridden.
     *
     * @param array<string, bool> $sets prepared-set overrides
     * @param list<string> $unskip
     */
    public static function build(
        Profile $profile,
        ComposerProject $project,
        array $sets = [],
        ?bool $carbon = null,
        array $unskip = [],
        bool $detect = true,
        ?bool $composerBased = null,
        ?string $containerCacheDirectory = null,
    ): RectorConfigBuilder {
        $isApp = $profile === Profile::App;
        $defaults = [
            'deadCode' => true,
            'codeQuality' => true,
            'codingStyle' => ! $isApp,
            'typeDeclarations' => true,
            'typeDeclarationDocblocks' => ! $isApp,
            'privatization' => ! $isApp,
            'rectorPreset' => true,
            'phpunitCodeQuality' => true,
        ];
        $flags = array_merge($defaults, $sets);

        return PresetFactory::build(
            $profile,
            new PreparedSets(
                deadCode: $flags['deadCode'],
                codeQuality: $flags['codeQuality'],
                codingStyle: $flags['codingStyle'],
                typeDeclarations: $flags['typeDeclarations'],
                typeDeclarationDocblocks: $flags['typeDeclarationDocblocks'],
                privatization: $flags['privatization'],
                carbon: PresetFactory::resolveCarbon($carbon, $project),
                rectorPreset: $flags['rectorPreset'],
                phpunitCodeQuality: $flags['phpunitCodeQuality'],
            ),
            $project,
            self::cpu(),
            null,
            $containerCacheDirectory,
            $unskip,
            $detect,
            $composerBased,
        );
    }
}
