<?php declare(strict_types=1);

use Rector\Set\ValueObject\SetList;
use SanderMuller\RectorPreset\Preset;
use SanderMuller\RectorPreset\Tests\Support\BuilderInspector;

it('autoloads the Preset entry point', function (): void {
    expect(class_exists(Preset::class))->toBeTrue();
});

it('requires the Rector minor this package version mirrors', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer)->toBeArray()
        ->and($composer['require']['rector/rector'] ?? null)->toBe('~2.8.0');
});

it('builds both presets through the public entry point', function (): void {
    $app = new BuilderInspector(Preset::app(detect: false));
    $package = new BuilderInspector(Preset::package(detect: false));

    expect($app->get('containerCacheDirectory'))->toBe(realpath(dirname(__DIR__, 2)) . '/.cache/phpstan')
        ->and($app->sets())->not->toContain(SetList::CODING_STYLE)
        ->and($package->sets())->toContain(SetList::CODING_STYLE);
});
