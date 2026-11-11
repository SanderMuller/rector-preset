# Changelog

All notable changes to `sandermuller/rector-preset` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v2.8.1 - 2026-11-11

<!-- verified-sha: 5990c7e5042ee7bb1b4ba2a1d5c4f44c61825d31 -->
#### Added

- **`withoutSets`** on `Preset::app()` and `Preset::package()`. It leaves out one detected extension set and keeps the others. Use it when a project has an extension installed but runs only some of its rules:
  
  ```php
  return Preset::app(withoutSets: [HihahoSetList::ALL])
      ->withPhpSets()
      ->withRules([/* the hihaho rules you use */]);
  
  ```
  A set the preset did not detect throws an `InvalidArgumentException`, so a typo or a prepared set such as `SetList::DEAD_CODE` does not pass silently. Turn a prepared set off with its own parameter.
  

#### Changed

- Each rector-laravel skip in `app()` now depends on the set that registers its rule. `ApplyDefaultInsteadOfNullCoalesceRector` stays while `LARAVEL_CODE_QUALITY` is on, and `ReplaceServiceContainerCallArgRector` stays while `LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME` is on. With every Laravel set on, nothing changes. Before, both skips depended only on the installed package, so leaving out one of those sets made Rector warn about a skip it never used.

#### Compatibility

- Conflicts with `hihaho/rector-rules` below 1.2.1. In 1.2.0, `AliasImportRector` uses a class that Rector 2.8 does not have, so Rector fails once the preset turns on `HihahoSetList::ALL`.

### What's Changed

* Bump zizmorcore/zizmor-action from 0.6.0 to 0.8.1 by @dependabot[bot] in https://github.com/SanderMuller/rector-preset/pull/1

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/SanderMuller/rector-preset/pull/1

**Full Changelog**: https://github.com/SanderMuller/rector-preset/compare/v2.8.0...v2.8.1

## v2.8.0 - 2026-11-11

<!-- verified-sha: c64ac64836f2ef2457e60b8da1b4fdc2821fdefd -->
### 2.8.0

The first release. It requires `rector/rector: ~2.8.0`. From now on the major and minor version follow Rector, and the patch number belongs to this package.

##### Added

- **`Preset::app()` and `Preset::package()`.** Each returns Rector's `RectorConfigBuilder` with the cache, the parallel workers, the memory limit, import names, attribute sets, fluent new lines, the prepared sets and the shared skips set up. Your `rector.php` adds `withPhpSets()`, configured rules and project skips.
- **Detection.** The presets scan the folders that exist (never `database/migrations`). They add the Laravel, Pest, hihaho and FluentValidation sets when those extensions are installed. `app()` adds `withComposerBased()` for the installed frameworks. Both presets add the PHPUnit composer-based set when the tests are PHPUnit, not Pest.
- **CPU-aware parallel workers.** 2 cores stay free locally and none on CI, clamped to 2–15 workers.
- **Container cache in `.cache/phpstan`**, created when it is missing.
- **Shared skips** for `TernaryToNullsafeCoalesceRector` and `ExplicitNullableObjectInstanceRector`, plus a skip list for each preset.
- **Guarded skips.** A skip is added only when its rule class exists, so a Rector release that deletes a rule does not break your config. A skip also goes when you turn off the set that registers its rule, so Rector does not warn that it is never registered.

##### Compatibility

- Conflicts with `driftingly/rector-laravel` below 2.7.2. Versions 2.7.0 and 2.7.1 allow Rector 2.8, but their array-access set uses a class that Rector 2.8 removed.
- Requires PHP 8.4.

**Full Changelog**: https://github.com/SanderMuller/rector-preset/commits/v2.8.0

## [Unreleased]
