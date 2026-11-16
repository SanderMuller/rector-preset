<?php declare(strict_types=1);

use Hihaho\RectorRules\Set\HihahoSetList;
use RectorLaravel\Set\LaravelSetList;
use RectorPest\Set\PestSetList;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;
use SanderMuller\RectorPreset\Preset;

// This repository runs its own preset from src/. The extensions are dev dependencies for the
// tests only, so their detected sets stay off. The Pest coding-style set stays on.
return Preset::package(withoutSets: [
    FluentValidationSetList::ALL,
    FluentValidationSetList::POLISH,
    FluentValidationSetList::SIMPLIFY,
    HihahoSetList::ALL,
    LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
    LaravelSetList::LARAVEL_CODE_QUALITY,
    LaravelSetList::LARAVEL_COLLECTION,
    LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
    LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
    PestSetList::PEST_CHAIN,
    PestSetList::PEST_CODE_QUALITY,
])
    ->withPhpSets(php84: true)
    ->withSkip([
        // Project trees that the boot tests run Rector on; not package code.
        __DIR__ . '/tests/Fixtures',
    ]);
