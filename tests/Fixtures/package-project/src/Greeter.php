<?php declare(strict_types=1);

namespace Fixture\Package;

final readonly class Greeter
{
    public function __construct(private string $name) {}

    public function greet(): string
    {
        return 'Hello ' . $this->name;
    }
}
