<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

/**
 * @internal
 */
final class SkipList
{
    /**
     * Removes the unskipped rules, then drops every rule whose class does not exist.
     *
     * Rector throws when a skipped rule does not exist, and it deletes rules in patch releases. A
     * missing extension or a deleted rule therefore loses its skip instead of breaking the config.
     *
     * @param list<string> $rules
     * @param list<string> $unskip
     * @return list<string>
     */
    public static function resolve(array $rules, array $unskip = []): array
    {
        return array_values(array_filter(
            $rules,
            static fn (string $rule): bool => ! in_array($rule, $unskip, true) && class_exists($rule),
        ));
    }
}
