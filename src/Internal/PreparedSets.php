<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Rector\Configuration\RectorConfigBuilder;

/**
 * The prepared sets one preset passes to a single withPreparedSets() call.
 *
 * withPreparedSets() only appends the sets that are true, so a later call cannot turn one off. The
 * preset therefore takes each set as a parameter and calls withPreparedSets() exactly once.
 *
 * @internal
 */
final readonly class PreparedSets
{
    public function __construct(
        public bool $deadCode,
        public bool $codeQuality,
        public bool $codingStyle,
        public bool $typeDeclarations,
        public bool $typeDeclarationDocblocks,
        public bool $privatization,
        public bool $carbon,
        public bool $rectorPreset,
        public bool $phpunitCodeQuality,
    ) {}

    public function applyTo(RectorConfigBuilder $builder): RectorConfigBuilder
    {
        return $builder->withPreparedSets(
            deadCode: $this->deadCode,
            codeQuality: $this->codeQuality,
            codingStyle: $this->codingStyle,
            typeDeclarations: $this->typeDeclarations,
            typeDeclarationDocblocks: $this->typeDeclarationDocblocks,
            privatization: $this->privatization,
            carbon: $this->carbon,
            rectorPreset: $this->rectorPreset,
            phpunitCodeQuality: $this->phpunitCodeQuality,
        );
    }
}
