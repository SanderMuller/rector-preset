# Fixtures

Small project trees that the boot tests run Rector on. They are data, not part of the package's own code: PHPStan, Pint and Rector skip this folder.

- `package-project/`: the shape of a Composer package (`src/`, `tests/`).
- `laravel-app/`: the shape of a Laravel application, including `database/migrations/` (the preset must never scan it) and `bootstrap/cache/` (the preset must skip it).
