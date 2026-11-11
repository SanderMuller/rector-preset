<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\Ternary\TernaryToNullsafeCoalesceRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector;
use RectorLaravel\Rector\MethodCall\ReplaceServiceContainerCallArgRector;

/**
 * What separates the two presets: their skip lists and the rules the app preset adds.
 *
 * The lists come from a survey of 27 repos (specs/shared-rector-preset-package.md, §1.4 and §4.3).
 * Rules that do nothing in Rector 2.8 (deprecated, or in no set) are left out on purpose.
 *
 * @internal
 */
enum Profile
{
    case App;
    case Package;

    /**
     * Every rule the preset can skip, whatever sets are on. The skip-health check reads this list.
     *
     * @return list<string>
     */
    public function skips(): array
    {
        return array_keys($this->skipSources());
    }

    /**
     * The skips whose rule a turned-on set registers.
     *
     * Rector warns on every run about a skipped rule that no set registers, so a skip goes when its
     * set is off. The PHP version sets come from the repo's own withPhpSets() call, which the preset
     * cannot see; their skips always stay.
     *
     * @param bool $laravelSets true when the rector-laravel sets are on
     * @return list<string>
     */
    public function activeSkips(PreparedSets $sets, bool $laravelSets): array
    {
        $active = [];

        foreach ($this->skipSources() as $rule => $source) {
            $isOn = match ($source) {
                'php' => true,
                'laravel' => $laravelSets,
                'deadCode' => $sets->deadCode,
                'codeQuality' => $sets->codeQuality,
                'typeDeclarations' => $sets->typeDeclarations,
                'privatization' => $sets->privatization,
                'phpunitCodeQuality' => $sets->phpunitCodeQuality,
            };

            if ($isOn) {
                $active[] = $rule;
            }
        }

        return $active;
    }

    /**
     * Each skipped rule, with the set that registers it.
     *
     * @return array<string, 'php'|'laravel'|'deadCode'|'codeQuality'|'typeDeclarations'|'privatization'|'phpunitCodeQuality'>
     */
    private function skipSources(): array
    {
        $profileSkips = match ($this) {
            self::Package => [
                AddArrowFunctionReturnTypeRector::class => 'typeDeclarations',
                InlineArrayReturnAssignRector::class => 'codeQuality',
                PrivatizeFinalClassMethodRector::class => 'privatization',
                RemoveUselessParamTagRector::class => 'deadCode',
                RemoveUselessReturnTagRector::class => 'deadCode',
            ],
            self::App => [
                // driftingly/rector-laravel: only skipped when it is installed (SkipList checks class_exists).
                ApplyDefaultInsteadOfNullCoalesceRector::class => 'laravel',
                ReplaceServiceContainerCallArgRector::class => 'laravel',
                ArgumentAdderRector::class => 'php',
                ClosureToArrowFunctionRector::class => 'php',
                PreferPHPUnitThisCallRector::class => 'phpunitCodeQuality',
                RestoreDefaultNullToNullableTypePropertyRector::class => 'php',
            ],
        };

        return [
            ...$profileSkips,
            // Both presets, from the repo-init canonical setup (commit 76cac42).
            // Its `$x?->prop ?? $d` output fails PHPStan's nullsafe.neverNull.
            TernaryToNullsafeCoalesceRector::class => 'php',
            // On a nullable enum, its rewrite lets the deadCode set delete reachable code.
            ExplicitNullableObjectInstanceRector::class => 'codeQuality',
        ];
    }

    /**
     * @return list<string>
     */
    public function rules(bool $privatization): array
    {
        if ($this === self::Package) {
            return [];
        }

        // PrivatizeFinalClassPropertyRector is part of the privatization set. Adding it as well would
        // make Rector warn that the rule is registered in both a set and withRules().
        return $privatization
            ? [PreferPHPUnitSelfCallRector::class]
            : [PreferPHPUnitSelfCallRector::class, PrivatizeFinalClassPropertyRector::class];
    }
}
