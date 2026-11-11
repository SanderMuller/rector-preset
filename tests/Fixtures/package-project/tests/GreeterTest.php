<?php declare(strict_types=1);

namespace Fixture\Package\Tests;

use Fixture\Package\Greeter;
use PHPUnit\Framework\TestCase;

final class GreeterTest extends TestCase
{
    public function testGreets(): void
    {
        self::assertSame('Hello Ada', new Greeter('Ada')->greet());
    }
}
