<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Hihaho\RectorRules\Set\HihahoSetList;
use InvalidArgumentException;
use Pest\Rector\Set\PestSetList as OfficialPestSetList;
use Rector\Configuration\RectorConfigBuilder;
use RectorLaravel\Set\LaravelSetList;
use RectorPest\Set\PestSetList as CommunityPestSetList;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

/**
 * Turns on what the project's installed packages and folders call for.
 *
 * Every extension set is read only after a class_exists() check on its set list and a defined() check
 * on its constant, and only when the extension is a dependency of the project (never the root package).
 *
 * @internal
 */
final class Detection
{
    /**
     * Folders scanned when they exist. database/migrations is left out on purpose: migrations that
     * already ran must not be rewritten.
     */
    public const array PATHS = [
        'app',
        'src',
        'config',
        'routes',
        'database/factories',
        'database/seeders',
        'tests',
        'workbench',
        'bootstrap',
    ];

    /**
     * Skipped when they exist under a scanned folder or the root. Testbench keeps a package's
     * migrations in workbench/database/migrations, which the workbench path would otherwise scan.
     */
    public const array SKIPPED_PATHS = [
        'bootstrap/cache',
        '.cache',
        'workbench/database/migrations',
    ];

    /**
     * @param ?bool $composerBased null: the profile default. app() turns on every installed
     *                            framework. package() turns on PHPUnit only: Rector binds the
     *                            framework rules to the installed version, not the range a package
     *                            supports. true: every framework. false: none.
     * @param list<string> $withoutSets detected extension sets to leave out
     * @return list<string> the extension sets it turned on
     */
    public static function apply(
        RectorConfigBuilder $builder,
        ComposerProject $project,
        string $projectRoot,
        Profile $profile,
        ?bool $composerBased,
        array $withoutSets = [],
    ): array {
        $paths = self::paths($projectRoot);

        if ($paths !== []) {
            $builder->withPaths($paths);
        }

        $skippedPaths = self::skippedPaths($projectRoot);

        if ($skippedPaths !== []) {
            $builder->withSkip($skippedPaths);
        }

        $flags = self::composerBasedFlags($project, $profile, $composerBased);

        if (in_array(true, $flags, true)) {
            $builder->withComposerBased(...$flags);
        }

        $detected = self::extensionSets($project);
        $unknown = array_values(array_diff($withoutSets, $detected));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'withoutSets only takes extension sets the preset detected. Not detected: %s. Detected: %s.',
                implode(', ', $unknown),
                $detected === [] ? 'none' : implode(', ', $detected),
            ));
        }

        $sets = array_values(array_diff($detected, $withoutSets));

        if ($sets !== []) {
            $builder->withSets($sets);
        }

        return $sets;
    }

    /**
     * @return array{twig: bool, doctrine: bool, phpunit: bool, symfony: bool, laravel: bool}
     */
    public static function composerBasedFlags(ComposerProject $project, Profile $profile, ?bool $composerBased): array
    {
        $frameworks = $composerBased ?? $profile === Profile::App;

        // The PHPUnit upgrade set rewrites PHPUnit\Framework\TestCase subclasses. Pest installs
        // phpunit/phpunit too, but a Pest suite has no such classes, so the set is noise there.
        $phpunit = $composerBased !== false
            && $project->isInstalled('phpunit/phpunit')
            && ! $project->isInstalled('pestphp/pest');

        return [
            'twig' => $frameworks && $project->isInstalled('twig/twig'),
            'doctrine' => $frameworks && $project->isInstalled('doctrine/orm'),
            'phpunit' => $phpunit,
            'symfony' => $frameworks && $project->isInstalled('symfony/framework-bundle'),
            'laravel' => $frameworks && $project->isInstalled('laravel/framework'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function paths(string $projectRoot): array
    {
        $paths = [];

        foreach (self::PATHS as $path) {
            if (is_dir($projectRoot . '/' . $path)) {
                $paths[] = $projectRoot . '/' . $path;
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public static function skippedPaths(string $projectRoot): array
    {
        $paths = [];

        foreach (self::SKIPPED_PATHS as $path) {
            if (is_dir($projectRoot . '/' . $path)) {
                $paths[] = $projectRoot . '/' . $path;
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public static function extensionSets(ComposerProject $project): array
    {
        $sets = [];

        if ($project->isInstalled('driftingly/rector-laravel')) {
            $sets = [...$sets, ...self::constants(LaravelSetList::class, [
                'LARAVEL_CODE_QUALITY',
                'LARAVEL_ARRAYACCESS_TO_METHOD_CALL',
                'LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME',
                'LARAVEL_FACADE_ALIASES_TO_FULL_NAMES',
                'LARAVEL_COLLECTION',
            ])];
        }

        // mrpunyapal/rector-pest: abandoned in favour of pestphp/pest-plugin-rector, still used by many repos.
        if ($project->isInstalled('mrpunyapal/rector-pest')) {
            $sets = [...$sets, ...self::constants(CommunityPestSetList::class, ['PEST_CODE_QUALITY', 'PEST_CHAIN'])];

            if ($project->isInstalled('pestphp/pest-plugin-laravel')) {
                $sets = [...$sets, ...self::constants(CommunityPestSetList::class, ['PEST_LARAVEL'])];
            }
        }

        if ($project->isInstalled('pestphp/pest-plugin-rector')) {
            $sets = [...$sets, ...self::constants(OfficialPestSetList::class, ['CODING_STYLE'])];
        }

        if ($project->isInstalled('hihaho/rector-rules')) {
            $sets = [...$sets, ...self::constants(HihahoSetList::class, ['ALL'])];
        }

        if ($project->isInstalled('sandermuller/laravel-fluent-validation-rector')) {
            // SCHEMA stays opt-in: only 2 of the 4 surveyed users run it.
            $sets = [...$sets, ...self::constants(FluentValidationSetList::class, ['ALL', 'POLISH', 'SIMPLIFY'])];
        }

        return $sets;
    }

    /**
     * Reads set constants by name. The preset does not constrain the extension versions, so an older
     * release can have the set list class without one of its constants. That set is left out
     * instead of failing the config with an Error.
     *
     * @param string $setList a set list class; a class that does not exist gives no sets
     * @param list<string> $names
     * @return list<string>
     */
    public static function constants(string $setList, array $names): array
    {
        if (! class_exists($setList)) {
            return [];
        }

        $sets = [];

        foreach ($names as $name) {
            $constant = $setList . '::' . $name;

            if (defined($constant)) {
                $value = constant($constant);

                if (is_string($value)) {
                    $sets[] = $value;
                }
            }
        }

        return $sets;
    }
}
