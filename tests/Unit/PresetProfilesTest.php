<?php declare(strict_types=1);

use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\Configuration\Deprecation\Contract\DeprecatedInterface;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\Ternary\TernaryToNullsafeCoalesceRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector;
use RectorLaravel\Rector\MethodCall\ReplaceServiceContainerCallArgRector;
use SanderMuller\RectorPreset\Internal\Profile;
use SanderMuller\RectorPreset\Tests\Support\BuilderInspector;
use SanderMuller\RectorPreset\Tests\Support\PresetHarness;

beforeEach(function (): void {
    $this->root = PresetHarness::temporaryRoot();
});

afterEach(function (): void {
    PresetHarness::remove($this->root);
});

/**
 * @param list<string> $installed
 * @param array<string, bool> $sets
 * @param list<string> $unskip
 */
function presetOf(
    Profile $profile,
    string $root,
    array $installed = [],
    array $sets = [],
    ?bool $carbon = null,
    array $unskip = [],
    bool $detect = true,
    ?bool $composerBased = null,
    ?string $containerCacheDirectory = null,
): BuilderInspector {
    return new BuilderInspector(PresetHarness::build(
        $profile,
        PresetHarness::project($root, $installed),
        $sets,
        $carbon,
        $unskip,
        $detect,
        $composerBased,
        $containerCacheDirectory,
    ));
}

it('turns on the package prepared sets', function (): void {
    $sets = presetOf(Profile::Package, $this->root, detect: false)->sets();

    expect($sets)->toContain(
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::TYPE_DECLARATION_DOCBLOCKS,
        SetList::PRIVATIZATION,
        SetList::RECTOR_PRESET,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        // @phpstan-ignore classConstant.deprecated, classConstant.deprecated (the test checks that the deprecated sets stay off)
    )->not->toContain(SetList::INSTANCEOF, SetList::EARLY_RETURN, SetList::CARBON);
});

it('turns on the app prepared sets and leaves the package-only sets off', function (): void {
    $sets = presetOf(Profile::App, $this->root, detect: false)->sets();

    expect($sets)->toContain(
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        SetList::RECTOR_PRESET,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    )->not->toContain(SetList::CODING_STYLE, SetList::PRIVATIZATION, SetList::TYPE_DECLARATION_DOCBLOCKS);
});

it('lets an app use withTypeCoverageDocblockLevel(), because typeDeclarationDocblocks is off', function (): void {
    $builder = PresetHarness::build(Profile::App, PresetHarness::project($this->root), detect: false)
        ->withTypeCoverageDocblockLevel(2);

    expect(new BuilderInspector($builder)->sets())->not->toContain(SetList::TYPE_DECLARATION_DOCBLOCKS);
});

it('lets a parameter override a prepared-set default', function (): void {
    $sets = presetOf(Profile::App, $this->root, sets: ['codingStyle' => true, 'deadCode' => false], detect: false)->sets();

    expect($sets)->toContain(SetList::CODING_STYLE)->not->toContain(SetList::DEAD_CODE);
});

it('turns the carbon set on only when nesbot/carbon is installed', function (): void {
    expect(presetOf(Profile::Package, $this->root, detect: false)->sets())->not->toContain(SetList::CARBON)
        ->and(presetOf(Profile::Package, $this->root, ['nesbot/carbon'], detect: false)->sets())->toContain(SetList::CARBON)
        ->and(presetOf(Profile::Package, $this->root, carbon: true, detect: false)->sets())->toContain(SetList::CARBON)
        ->and(presetOf(Profile::App, $this->root, ['nesbot/carbon'], carbon: false, detect: false)->sets())->not->toContain(SetList::CARBON);
});

it('skips the package rules', function (): void {
    expect(presetOf(Profile::Package, $this->root, detect: false)->skip())->toBe([
        AddArrowFunctionReturnTypeRector::class,
        InlineArrayReturnAssignRector::class,
        PrivatizeFinalClassMethodRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        TernaryToNullsafeCoalesceRector::class,
        ExplicitNullableObjectInstanceRector::class,
    ]);
});

it('skips the app rules, including the rector-laravel ones when they exist', function (): void {
    $ruleSkips = array_values(array_filter(
        presetOf(Profile::App, $this->root, ['driftingly/rector-laravel'])->skip(),
        static fn (mixed $skip): bool => is_string($skip) && ! str_starts_with($skip, '/'),
    ));

    expect($ruleSkips)->toBe([
        ApplyDefaultInsteadOfNullCoalesceRector::class,
        ReplaceServiceContainerCallArgRector::class,
        ArgumentAdderRector::class,
        ClosureToArrowFunctionRector::class,
        PreferPHPUnitThisCallRector::class,
        RestoreDefaultNullToNullableTypePropertyRector::class,
        TernaryToNullsafeCoalesceRector::class,
        ExplicitNullableObjectInstanceRector::class,
    ]);
})->group('extensions');

it('removes the unskipped rules from the skip list', function (): void {
    expect(presetOf(Profile::App, $this->root, unskip: [ClosureToArrowFunctionRector::class], detect: false)->skip())
        ->not->toContain(ClosureToArrowFunctionRector::class)
        ->toContain(ArgumentAdderRector::class);
});

it('adds the app rules and no package rules', function (): void {
    expect(presetOf(Profile::App, $this->root, detect: false)->rules())
        ->toBe([PreferPHPUnitSelfCallRector::class, PrivatizeFinalClassPropertyRector::class])
        ->and(presetOf(Profile::Package, $this->root, detect: false)->rules())
        ->toBeEmpty();
});

it('does not add PrivatizeFinalClassPropertyRector to an app that turns privatization on', function (): void {
    $inspector = presetOf(Profile::App, $this->root, sets: ['privatization' => true], detect: false);

    expect($inspector->rules())->toBe([PreferPHPUnitSelfCallRector::class])
        ->and($inspector->sets())->toContain(SetList::PRIVATIZATION);
});

it('turns fluent new lines on in both presets, and a repo can turn them off', function (): void {
    $builder = PresetHarness::build(Profile::App, PresetHarness::project($this->root), detect: false)
        ->withFluentCallNewLine(false);

    expect(presetOf(Profile::App, $this->root, detect: false)->get('isFluentNewLine'))->toBeTrue()
        ->and(presetOf(Profile::Package, $this->root, detect: false)->get('isFluentNewLine'))->toBeTrue()
        ->and(new BuilderInspector($builder)->get('isFluentNewLine'))->toBeFalse();
});

it('lets a repo call withPhpSets() on the preset', function (): void {
    $builder = PresetHarness::build(Profile::Package, PresetHarness::project($this->root), detect: false)
        ->withPhpSets(php84: true);

    expect(new BuilderInspector($builder)->get('isWithPhpSetsUsed'))->toBeTrue();
});

it('keeps every skipped and added rule existing and not deprecated', function (Profile $profile): void {
    $rules = [...$profile->skips(), ...$profile->rules(privatization: false)];

    foreach ($rules as $rule) {
        expect(class_exists($rule))->toBeTrue($rule . ' no longer exists')
            ->and(is_a($rule, DeprecatedInterface::class, true))
            ->toBeFalse($rule . ' is deprecated');
    }
})->with([Profile::App, Profile::Package])->group('extensions', 'skip-health');

it('skips only the core app rules when the rector-laravel sets are off', function (): void {
    expect(presetOf(Profile::App, $this->root, detect: false)->skip())->toBe([
        ArgumentAdderRector::class,
        ClosureToArrowFunctionRector::class,
        PreferPHPUnitThisCallRector::class,
        RestoreDefaultNullToNullableTypePropertyRector::class,
        TernaryToNullsafeCoalesceRector::class,
        ExplicitNullableObjectInstanceRector::class,
    ]);
});

it('drops the skips of a turned-off set, so Rector does not warn that they are never registered', function (): void {
    $skips = presetOf(Profile::Package, $this->root, sets: ['privatization' => false, 'codeQuality' => false, 'deadCode' => false], detect: false)->skip();

    expect($skips)->toBe([
        AddArrowFunctionReturnTypeRector::class,
        TernaryToNullsafeCoalesceRector::class,
    ]);
});

it('drops PreferPHPUnitThisCallRector when phpunitCodeQuality is off', function (): void {
    expect(presetOf(Profile::App, $this->root, sets: ['phpunitCodeQuality' => false], detect: false)->skip())
        ->not->toContain(PreferPHPUnitThisCallRector::class);
});
