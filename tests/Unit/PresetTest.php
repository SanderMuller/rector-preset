<?php declare(strict_types=1);

use Hihaho\RectorRules\Set\HihahoSetList;
use Rector\Set\ValueObject\SetList;
use SanderMuller\RectorPreset\Preset;
use SanderMuller\RectorPreset\Tests\Support\BuilderInspector;

it('autoloads the Preset entry point', function (): void {
    expect(class_exists(Preset::class))->toBeTrue();
});

it('requires the Rector minor this package version mirrors', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    assert(is_array($composer) && is_array($composer['require'] ?? null));

    expect($composer['require']['rector/rector'] ?? null)->toBe('~2.8.0');
});

it('builds both presets through the public entry point', function (): void {
    $app = new BuilderInspector(Preset::app(detect: false));
    $package = new BuilderInspector(Preset::package(detect: false));

    expect($app->get('containerCacheDirectory'))->toBe(realpath(dirname(__DIR__, 2)) . '/.cache/phpstan')
        ->and($app->sets())->not->toContain(SetList::CODING_STYLE)
        ->and($package->sets())->toContain(SetList::CODING_STYLE);
});

it('passes withoutSets through the public entry point', function (): void {
    $app = new BuilderInspector(Preset::app());
    $withoutHihaho = new BuilderInspector(Preset::package(withoutSets: [HihahoSetList::ALL]));

    expect($app->sets())->toContain(HihahoSetList::ALL)
        ->and($withoutHihaho->sets())->not->toContain(HihahoSetList::ALL);
})->group('extensions');
