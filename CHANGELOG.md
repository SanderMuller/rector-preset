# Changelog

All notable changes to `sandermuller/rector-preset` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
