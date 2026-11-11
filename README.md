# Rector Preset

Shared [Rector](https://getrector.com) presets for Laravel applications and Composer packages. A preset sets up the cache, the parallel workers, the prepared sets, the shared skips and the extension sets for the packages you have installed. Your `rector.php` keeps only what is specific to your project.

## Installation

```bash
composer require --dev sandermuller/rector-preset:~2.8.0
```

Requires PHP 8.4 and `rector/rector` 2.8.

## Usage

For a Laravel application:

```php
<?php declare(strict_types=1);

use SanderMuller\RectorPreset\Preset;

return Preset::app()
    ->withPhpSets(php85: true);
```

For a Composer package:

```php
<?php declare(strict_types=1);

use SanderMuller\RectorPreset\Preset;

return Preset::package()
    ->withPhpSets(php84: true);
```

Each method returns Rector's own `RectorConfigBuilder`. Add your PHP set, configured rules and project skips to it:

```php
return Preset::app(unskip: [Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector::class])
    ->withPhpSets(php84: true)
    ->withSkip([__DIR__ . '/app/Models/Example.php'])
    ->withFluentCallNewLine(false);
```

## What the presets set up

Both presets:

- Cache in `.cache/rector`, and Rector's container cache in `.cache/phpstan`, the directory PHPStan uses as `tmpDir`. The preset creates the directory when it is missing.
- Parallel workers from the CPU count: 2 cores stay free locally, none on CI, at least 2 and at most 15 workers.
- `withMemoryLimit('3G')`, `withImportNames()`, `withAttributesSets()` and `withFluentCallNewLine()`.
- The `carbon` set when `nesbot/carbon` is installed.

| Prepared set | `app()` | `package()` |
|---|---|---|
| `deadCode`, `codeQuality`, `typeDeclarations`, `rectorPreset`, `phpunitCodeQuality` | on | on |
| `codingStyle`, `privatization`, `typeDeclarationDocblocks` | off | on |

Each prepared set is a parameter, for example `Preset::app(codingStyle: true)`. `instanceOf` and `earlyReturn` are not: Rector 2.8 deprecated both sets and moved their rules into `codeQuality`.

Both presets skip `TernaryToNullsafeCoalesceRector` (its output fails PHPStan's `nullsafe.neverNull`) and `ExplicitNullableObjectInstanceRector` (on a nullable enum it lets `deadCode` delete reachable code).

`package()` also skips `AddArrowFunctionReturnTypeRector`, `InlineArrayReturnAssignRector`, `PrivatizeFinalClassMethodRector`, `RemoveUselessParamTagRector` and `RemoveUselessReturnTagRector`.

`app()` also skips `ArgumentAdderRector`, `ClosureToArrowFunctionRector`, `PreferPHPUnitThisCallRector` and `RestoreDefaultNullToNullableTypePropertyRector`, plus rector-laravel's `ApplyDefaultInsteadOfNullCoalesceRector` and `ReplaceServiceContainerCallArgRector`. It adds `PreferPHPUnitSelfCallRector`, and `PrivatizeFinalClassPropertyRector` while `privatization` is off.

A skip is added only when its rule class exists, so a Rector release that deletes a rule does not break your config. A skip is also left out when you turn off the set that registers its rule, because Rector warns on every run about a skipped rule that is never registered. The skips for PHP-version rules (`TernaryToNullsafeCoalesceRector`, `ArgumentAdderRector`, `ClosureToArrowFunctionRector`, `RestoreDefaultNullToNullableTypePropertyRector`) always stay, so call `withPhpSets()` in your `rector.php`.

## Detection

With `detect: true` (the default), the preset looks at your folders and at the packages your project installs:

| When | The preset adds |
|---|---|
| The folder exists: `app`, `src`, `config`, `routes`, `database/factories`, `database/seeders`, `tests`, `workbench`, `bootstrap` | The folder to `withPaths()`. `database/migrations` is never scanned. `bootstrap/cache`, `.cache` and `workbench/database/migrations` are skipped. |
| `app()`, or `package(composerBased: true)` | `withComposerBased()` for the installed `laravel/framework`, `symfony/framework-bundle`, `doctrine/orm` and `twig/twig` |
| `phpunit/phpunit` without `pestphp/pest`, in both presets | `withComposerBased(phpunit: true)` |
| `driftingly/rector-laravel` | `LARAVEL_CODE_QUALITY`, `LARAVEL_ARRAYACCESS_TO_METHOD_CALL`, `LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME`, `LARAVEL_FACADE_ALIASES_TO_FULL_NAMES`, `LARAVEL_COLLECTION` |
| `pestphp/pest-plugin-rector` | `CODING_STYLE` |
| `mrpunyapal/rector-pest` | `PEST_CODE_QUALITY`, `PEST_CHAIN`, and `PEST_LARAVEL` with `pestphp/pest-plugin-laravel` |
| `hihaho/rector-rules` | `HihahoSetList::ALL` |
| `sandermuller/laravel-fluent-validation-rector` | `FluentValidationSetList::ALL`, `POLISH` and `SIMPLIFY` |

`package()` leaves the framework sets off. Rector picks those rules from the installed version, not from the range your package supports, so a package that supports Laravel 11 to 13 would get code that only works on Laravel 13. Pass `composerBased: true` when your package supports one major version. `composerBased: false` turns every composer-based set off, PHPUnit included.

An extension set is added only when its constant exists in the installed release, so an older extension loses that set instead of breaking your config.

Your own `withPaths()` call replaces the detected paths. `detect: false` turns all detection off.

## Parameters

Both methods take the same named parameters:

| Parameter | Default | Meaning |
|---|---|---|
| `projectRoot` | the Composer project root | The folder the paths and the cache resolve against |
| `containerCacheDirectory` | `.cache/phpstan` | Relative to the project root, or absolute |
| `deadCode` … `phpunitCodeQuality` | see the table above | The prepared sets |
| `carbon` | `null` | `null` turns it on when `nesbot/carbon` is installed |
| `unskip` | `[]` | Rules to remove from the preset's skip list |
| `detect` | `true` | Folder and package detection |
| `composerBased` | `null` | `null`: frameworks in `app()`, PHPUnit only in `package()`; `true`: all; `false`: none |

## Things to know

- Do not call `withCache()` on the preset. Rector resets every cache argument you leave out, so you lose the container cache directory. Use the `containerCacheDirectory` parameter.
- `withPreparedSets()` only adds sets, and `withSkip()` only adds skips. To turn a preset set off, use its parameter. To remove a preset skip, use `unskip`.
- `withPhpSets()` is yours to call. Rector allows it only once.
- `withTypeCoverageDocblockLevel()` cannot be combined with the `typeDeclarationDocblocks` set. With `package()`, pass `typeDeclarationDocblocks: false`.

## Versioning

The major and minor version follow Rector: `2.8.x` requires `rector/rector: ~2.8.0`. The patch number belongs to this package. A patch release can change what the presets do, and its release notes list each change. Run `rector --dry-run` in CI to see the effect of an update. See [PUBLIC_API.md](PUBLIC_API.md).

## Testing

```bash
composer test
```

## License

MIT. See [LICENSE](LICENSE).
