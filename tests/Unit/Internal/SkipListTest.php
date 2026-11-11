<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use SanderMuller\RectorPreset\Internal\SkipList;

it('keeps rules whose class exists', function (): void {
    expect(SkipList::resolve([InlineArrayReturnAssignRector::class, PrivatizeFinalClassMethodRector::class]))
        ->toBe([InlineArrayReturnAssignRector::class, PrivatizeFinalClassMethodRector::class]);
});

it('removes only the unskipped rules', function (): void {
    expect(SkipList::resolve(
        [InlineArrayReturnAssignRector::class, PrivatizeFinalClassMethodRector::class],
        [PrivatizeFinalClassMethodRector::class],
    ))->toBe([InlineArrayReturnAssignRector::class]);
});

it('drops a rule whose class does not exist, as after a Rector release deleted it', function (): void {
    expect(SkipList::resolve(['Rector\\Deleted\\GoneRector', InlineArrayReturnAssignRector::class]))
        ->toBe([InlineArrayReturnAssignRector::class]);
});

it('ignores an unskip entry that the list does not contain', function (): void {
    expect(SkipList::resolve([InlineArrayReturnAssignRector::class], ['Rector\\Unknown\\OtherRector']))
        ->toBe([InlineArrayReturnAssignRector::class]);
});
