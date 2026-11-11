<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Rector\Configuration\RectorConfigBuilder;

/**
 * Builds a preset. Preset is the public wrapper; this class takes the Composer project and the CPU
 * reader as arguments, so tests can control detection.
 *
 * @internal
 */
final class PresetFactory
{
    /**
     * @param list<string> $unskip
     * @param list<string> $withoutSets
     */
    public static function build(
        Profile $profile,
        PreparedSets $preparedSets,
        ComposerProject $project,
        CpuCores $cpuCores,
        ?string $projectRoot,
        ?string $containerCacheDirectory,
        array $unskip,
        bool $detect = true,
        ?bool $composerBased = null,
        array $withoutSets = [],
    ): RectorConfigBuilder {
        $root = ProjectRoot::resolve($projectRoot, $project);

        $builder = BaseConfig::configure($root, $containerCacheDirectory, $cpuCores, $preparedSets);

        $extensionSets = $detect
            ? Detection::apply($builder, $project, $root, $profile, $composerBased, $withoutSets)
            : [];

        $skips = SkipList::resolve($profile->activeSkips($preparedSets, $extensionSets), $unskip);

        if ($skips !== []) {
            $builder->withSkip($skips);
        }

        $rules = array_values(array_filter($profile->rules($preparedSets->privatization), class_exists(...)));

        if ($rules !== []) {
            $builder->withRules($rules);
        }

        return $builder;
    }

    /**
     * A null carbon flag means: on when nesbot/carbon is installed. The carbon set adds rules such
     * as DateFuncCallToCarbonRector, which would bring Carbon into a project that does not use it.
     */
    public static function resolveCarbon(?bool $carbon, ComposerProject $project): bool
    {
        return $carbon ?? $project->isInstalled('nesbot/carbon');
    }
}
