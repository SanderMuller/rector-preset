<?php declare(strict_types=1);

// The preset must never scan migrations. This file holds code that Rector's
// dead-code set would change, so a boot test can prove it stays untouched.
return new class {
    public function up(): void
    {
        $unused = 1;
    }
};
