<?php declare(strict_types=1);

namespace Tests;

use App\Models\Example;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testName(): void
    {
        self::assertSame('a', new Example('a')->name);
    }
}
