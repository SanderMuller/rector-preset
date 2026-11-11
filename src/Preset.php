<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset;

use Rector\Configuration\RectorConfigBuilder;
use SanderMuller\RectorPreset\Internal\ComposerProject;
use SanderMuller\RectorPreset\Internal\CpuCores;
use SanderMuller\RectorPreset\Internal\PreparedSets;
use SanderMuller\RectorPreset\Internal\PresetFactory;
use SanderMuller\RectorPreset\Internal\Profile;

/**
 * Entry point: each factory method returns a configured RectorConfigBuilder.
 *
 * Call withPhpSets(), your configured rules and your project skips on the result. Do not call
 * withCache() again: it resets the container cache directory, use the containerCacheDirectory
 * parameter instead.
 */
final class Preset
{
    /**
     * The preset for Laravel applications.
     *
     * @param ?string $projectRoot defaults to the root of the Composer project that installed this package
     * @param ?string $containerCacheDirectory relative to the project root, or absolute; defaults to .cache/phpstan
     * @param ?bool $carbon null: on when nesbot/carbon is installed
     * @param list<string> $unskip rules to remove from the preset's skip list
     * @param bool $detect false turns off every detection: paths, composer-based and extension sets
     * @param ?bool $composerBased null: version-bound sets for the installed Laravel, Symfony, Doctrine and Twig,
     *                            plus PHPUnit when the tests are not Pest; false: none
     */
    public static function app(
        ?string $projectRoot = null,
        ?string $containerCacheDirectory = null,
        bool $deadCode = true,
        bool $codeQuality = true,
        bool $codingStyle = false,
        bool $typeDeclarations = true,
        bool $typeDeclarationDocblocks = false,
        bool $privatization = false,
        ?bool $carbon = null,
        bool $rectorPreset = true,
        bool $phpunitCodeQuality = true,
        array $unskip = [],
        bool $detect = true,
        ?bool $composerBased = null,
    ): RectorConfigBuilder {
        $project = ComposerProject::current();

        return PresetFactory::build(
            Profile::App,
            new PreparedSets(
                deadCode: $deadCode,
                codeQuality: $codeQuality,
                codingStyle: $codingStyle,
                typeDeclarations: $typeDeclarations,
                typeDeclarationDocblocks: $typeDeclarationDocblocks,
                privatization: $privatization,
                carbon: PresetFactory::resolveCarbon($carbon, $project),
                rectorPreset: $rectorPreset,
                phpunitCodeQuality: $phpunitCodeQuality,
            ),
            $project,
            CpuCores::fromEnvironment(),
            $projectRoot,
            $containerCacheDirectory,
            $unskip,
            $detect,
            $composerBased,
        );
    }

    /**
     * The preset for Composer packages.
     *
     * @param ?string $projectRoot defaults to the root of the Composer project that installed this package
     * @param ?string $containerCacheDirectory relative to the project root, or absolute; defaults to .cache/phpstan
     * @param ?bool $carbon null: on when nesbot/carbon is installed
     * @param list<string> $unskip rules to remove from the preset's skip list
     * @param bool $detect false turns off every detection: paths, composer-based and extension sets
     * @param ?bool $composerBased null: only the PHPUnit set, when the tests are not Pest. Rector binds
     *                            the framework rules to the installed version, not the supported
     *                            range, so a package that supports several Laravel majors would get
     *                            code for the newest one only. true: every installed framework.
     *                            false: none
     */
    public static function package(
        ?string $projectRoot = null,
        ?string $containerCacheDirectory = null,
        bool $deadCode = true,
        bool $codeQuality = true,
        bool $codingStyle = true,
        bool $typeDeclarations = true,
        bool $typeDeclarationDocblocks = true,
        bool $privatization = true,
        ?bool $carbon = null,
        bool $rectorPreset = true,
        bool $phpunitCodeQuality = true,
        array $unskip = [],
        bool $detect = true,
        ?bool $composerBased = null,
    ): RectorConfigBuilder {
        $project = ComposerProject::current();

        return PresetFactory::build(
            Profile::Package,
            new PreparedSets(
                deadCode: $deadCode,
                codeQuality: $codeQuality,
                codingStyle: $codingStyle,
                typeDeclarations: $typeDeclarations,
                typeDeclarationDocblocks: $typeDeclarationDocblocks,
                privatization: $privatization,
                carbon: PresetFactory::resolveCarbon($carbon, $project),
                rectorPreset: $rectorPreset,
                phpunitCodeQuality: $phpunitCodeQuality,
            ),
            $project,
            CpuCores::fromEnvironment(),
            $projectRoot,
            $containerCacheDirectory,
            $unskip,
            $detect,
            $composerBased,
        );
    }
}
