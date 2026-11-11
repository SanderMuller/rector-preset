# Public API

This file lists the public surface of `sandermuller/rector-preset`. Anything outside it is internal and can change in any release.

## Versioning

The major and minor version follow Rector. Package `2.8.x` requires `rector/rector: ~2.8.0`, and package `2.9.x` will require `~2.9.0`. The patch number belongs to this package.

A patch release can change what the presets do: a new skip, a new set or a changed default. Rector itself changes rule behaviour inside a minor version, so the presets follow the same rhythm. Each release note lists every change in rule behaviour. Require the package with `~2.8.0` and run `rector --dry-run` in CI to see the effect of an update.

The parameters of `Preset::app()` and `Preset::package()` do not change in a way that breaks existing calls inside one Rector minor version.

## Stable surface

### Classes

- `SanderMuller\RectorPreset\Preset`: the entry point. Each factory method returns a configured `Rector\Configuration\RectorConfigBuilder`.

### Methods

- `Preset::app(...)`: the preset for Laravel applications.
- `Preset::package(...)`: the preset for Composer packages.

## Internal (not covered by semver)

Everything under `SanderMuller\RectorPreset\Internal`, and anything marked `@internal`.
