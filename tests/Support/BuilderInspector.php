<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Tests\Support;

use Rector\Configuration\RectorConfigBuilder;
use ReflectionProperty;

/**
 * Reads the state a RectorConfigBuilder collected, so tests can assert what a preset configured
 * without booting Rector. The property names are the ones in rector/rector 2.8.
 */
final readonly class BuilderInspector
{
    public function __construct(private RectorConfigBuilder $builder) {}

    public function get(string $property): mixed
    {
        return new ReflectionProperty(RectorConfigBuilder::class, $property)->getValue($this->builder);
    }

    /**
     * @return list<string>
     */
    public function sets(): array
    {
        /** @var list<string> $sets */
        $sets = $this->get('sets');

        return $sets;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function skip(): array
    {
        /** @var array<int|string, mixed> $skip */
        $skip = $this->get('skip');

        return $skip;
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        /** @var list<string> $rules */
        $rules = $this->get('rules');

        return $rules;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        /** @var list<string> $paths */
        $paths = $this->get('paths');

        return $paths;
    }
}
