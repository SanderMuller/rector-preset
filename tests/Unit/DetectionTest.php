<?php declare(strict_types=1);

use Hihaho\RectorRules\Set\HihahoSetList;
use Pest\Rector\Set\PestSetList as OfficialPestSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\SymfonySetList;
use RectorLaravel\Set\LaravelSetList;
use RectorPest\Set\PestSetList as CommunityPestSetList;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;
use SanderMuller\RectorPreset\Internal\Detection;
use SanderMuller\RectorPreset\Internal\Profile;
use SanderMuller\RectorPreset\Tests\Support\BuilderInspector;
use SanderMuller\RectorPreset\Tests\Support\PartialSetList;
use SanderMuller\RectorPreset\Tests\Support\PresetHarness;

beforeEach(function (): void {
    $this->root = PresetHarness::temporaryRoot();
});

afterEach(function (): void {
    PresetHarness::remove($this->root);
});

function makeDirectories(string $root, string ...$directories): void
{
    foreach ($directories as $directory) {
        mkdir($root . '/' . $directory, recursive: true);
    }
}

function detected(Profile $profile, string $root, array $installed = [], mixed ...$options): BuilderInspector
{
    return new BuilderInspector(PresetHarness::build($profile, PresetHarness::project($root, $installed), ...$options));
}

it('scans the app folders that exist, but never database/migrations', function (): void {
    makeDirectories($this->root, 'app', 'config', 'routes', 'database/factories', 'database/seeders', 'database/migrations', 'tests', 'bootstrap/cache', 'lang');

    expect(detected(Profile::App, $this->root)->paths())->toBe([
        $this->root . '/app',
        $this->root . '/config',
        $this->root . '/routes',
        $this->root . '/database/factories',
        $this->root . '/database/seeders',
        $this->root . '/tests',
        $this->root . '/bootstrap',
    ]);
});

it('scans the package folders that exist', function (): void {
    makeDirectories($this->root, 'src', 'tests', 'workbench', 'config');

    expect(detected(Profile::Package, $this->root)->paths())->toBe([
        $this->root . '/src',
        $this->root . '/config',
        $this->root . '/tests',
        $this->root . '/workbench',
    ]);
});

it('skips bootstrap/cache and .cache when they exist', function (): void {
    makeDirectories($this->root, 'bootstrap/cache');

    $skip = detected(Profile::App, $this->root)->skip();

    // .cache always exists: the preset creates the container cache directory in it.
    expect($skip)->toContain($this->root . '/bootstrap/cache', $this->root . '/.cache');
});

it('leaves the path list empty when none of the folders exists', function (): void {
    expect(detected(Profile::Package, $this->root)->paths())
        ->toBeEmpty();
});

it('lets a repo replace the detected paths', function (): void {
    makeDirectories($this->root, 'src', 'tests');

    $builder = PresetHarness::build(Profile::Package, PresetHarness::project($this->root))
        ->withPaths([$this->root . '/src']);

    expect(new BuilderInspector($builder)->paths())->toBe([$this->root . '/src']);
});

it('turns on the composer-based sets of the installed frameworks in app()', function (): void {
    $sets = detected(Profile::App, $this->root, ['laravel/framework', 'phpunit/phpunit'])->sets();

    expect($sets)->toContain(LaravelSetList::COMPOSER_BASED, PHPUnitSetList::COMPOSER_BASED);
})->group('extensions');

it('does not treat a Symfony component as a Symfony application', function (): void {
    $sets = detected(Profile::App, $this->root, ['laravel/framework', 'symfony/console', 'symfony/http-kernel'])->sets();

    expect($sets)->not->toContain(SymfonySetList::COMPOSER_BASED);
});

it('turns on the Symfony composer-based set for a Symfony application', function (): void {
    expect(detected(Profile::App, $this->root, ['symfony/framework-bundle'])->sets())->toContain(SymfonySetList::COMPOSER_BASED);
});

it('turns on only the PHPUnit composer-based set in package(), because the framework sets follow the installed version', function (): void {
    $sets = detected(Profile::Package, $this->root, ['laravel/framework', 'phpunit/phpunit'])->sets();

    expect($sets)->toContain(PHPUnitSetList::COMPOSER_BASED)
        ->not->toContain(LaravelSetList::COMPOSER_BASED);
})->group('extensions');

it('lets a package that supports one major version opt in to the framework composer-based sets', function (): void {
    $sets = detected(Profile::Package, $this->root, ['laravel/framework'], composerBased: true)->sets();

    expect($sets)->toContain(LaravelSetList::COMPOSER_BASED);
})->group('extensions');

it('leaves the PHPUnit composer-based set off for a Pest suite', function (Profile $profile): void {
    $sets = detected($profile, $this->root, ['phpunit/phpunit', 'pestphp/pest'])->sets();

    expect($sets)->not->toContain(PHPUnitSetList::COMPOSER_BASED);
})->with([Profile::App, Profile::Package]);

it('turns every composer-based set off with composerBased: false', function (): void {
    $sets = detected(Profile::App, $this->root, ['laravel/framework', 'phpunit/phpunit'], composerBased: false)->sets();

    expect($sets)->not->toContain(LaravelSetList::COMPOSER_BASED, PHPUnitSetList::COMPOSER_BASED);
})->group('extensions');

it('adds the Laravel sets when rector-laravel is installed, in both presets', function (Profile $profile): void {
    expect(detected($profile, $this->root, ['driftingly/rector-laravel'])->sets())->toContain(
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_COLLECTION,
    );
})->with([Profile::App, Profile::Package])->group('extensions');

it('adds no Laravel sets when rector-laravel is not a project dependency', function (): void {
    expect(detected(Profile::App, $this->root)->sets())->not->toContain(LaravelSetList::LARAVEL_CODE_QUALITY);
})->group('extensions');

it('adds the community Pest sets, and the Laravel one only with the Pest Laravel plugin', function (): void {
    $plain = detected(Profile::Package, $this->root, ['mrpunyapal/rector-pest'])->sets();
    $laravel = detected(Profile::Package, $this->root, ['mrpunyapal/rector-pest', 'pestphp/pest-plugin-laravel'])->sets();

    expect($plain)->toContain(CommunityPestSetList::PEST_CODE_QUALITY, CommunityPestSetList::PEST_CHAIN)
        ->not->toContain(CommunityPestSetList::PEST_LARAVEL)
        ->and($laravel)->toContain(CommunityPestSetList::PEST_LARAVEL);
})->group('extensions');

it('adds the official Pest coding-style set', function (): void {
    expect(detected(Profile::Package, $this->root, ['pestphp/pest-plugin-rector'])->sets())->toContain(OfficialPestSetList::CODING_STYLE);
})->group('extensions');

it('adds the hihaho set when hihaho/rector-rules is a dependency', function (): void {
    expect(detected(Profile::App, $this->root, ['hihaho/rector-rules'])->sets())->toContain(HihahoSetList::ALL);
})->group('extensions');

it('does not add the hihaho set to hihaho/rector-rules itself', function (): void {
    $project = PresetHarness::project($this->root, ['hihaho/rector-rules'], name: 'hihaho/rector-rules');

    expect(Detection::extensionSets($project))->not->toContain(HihahoSetList::ALL);
})->group('extensions');

it('adds the FluentValidation sets, without SCHEMA', function (): void {
    expect(detected(Profile::App, $this->root, ['sandermuller/laravel-fluent-validation-rector'])->sets())
        ->toContain(FluentValidationSetList::ALL, FluentValidationSetList::POLISH, FluentValidationSetList::SIMPLIFY)
        ->not->toContain(FluentValidationSetList::SCHEMA);
})->group('extensions');

it('turns off every detection with detect: false', function (): void {
    makeDirectories($this->root, 'src', 'app');
    $installed = ['laravel/framework', 'driftingly/rector-laravel', 'hihaho/rector-rules', 'mrpunyapal/rector-pest'];

    $inspector = detected(Profile::App, $this->root, $installed, detect: false);

    expect($inspector->paths())
        ->toBeEmpty()
        ->and($inspector->sets())->not->toContain(
            LaravelSetList::COMPOSER_BASED,
            LaravelSetList::LARAVEL_CODE_QUALITY,
            HihahoSetList::ALL,
            CommunityPestSetList::PEST_CODE_QUALITY,
        );
})->group('extensions');

it('leaves out a set constant that an older extension release does not have', function (): void {
    expect(Detection::constants(PartialSetList::class, ['PRESENT', 'MISSING']))->toBe(['/sets/present.php'])
        ->and(Detection::constants('Acme\\Missing\\SetList', ['ALL']))->toBe([]);
});
