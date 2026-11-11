<?php declare(strict_types=1);

use SanderMuller\RectorPreset\Internal\CpuCores;

function cpuCores(?string $cpuInfo = null, ?string $shell = null, bool $ci = false): CpuCores
{
    return new CpuCores(
        static fn (): ?string => $cpuInfo,
        static fn (): ?string => $shell,
        static fn (): bool => $ci,
    );
}

function cpuInfo(int $processors): string
{
    $entries = [];

    for ($index = 0; $index < $processors; ++$index) {
        $entries[] = "processor\t: {$index}\nmodel name\t: Fixture CPU\n";
    }

    return implode("\n", $entries);
}

it('counts processor entries in /proc/cpuinfo, including the first one', function (): void {
    expect(cpuCores(cpuInfo: cpuInfo(8))->cores())->toBe(8);
});

it('falls back to the shell command when /proc/cpuinfo is missing', function (): void {
    expect(cpuCores(shell: "12\n")->cores())->toBe(12);
});

it('falls back to the shell command when /proc/cpuinfo has no processor entries', function (): void {
    expect(cpuCores(cpuInfo: 'garbage', shell: '6')->cores())->toBe(6);
});

it('uses 4 cores when neither source is available', function (): void {
    expect(cpuCores()->cores())->toBe(CpuCores::FALLBACK_CORES);
});

it('ignores shell output that is not a positive number', function (string $output): void {
    expect(cpuCores(shell: $output)->cores())->toBe(CpuCores::FALLBACK_CORES);
})->with(['', 'sysctl: unknown oid', '0', '-3', '4.5']);

it('keeps 2 cores free locally', function (): void {
    expect(cpuCores(shell: '14')->workers())->toBe(12);
});

it('keeps no cores free on CI', function (): void {
    expect(cpuCores(shell: '4', ci: true)->workers())->toBe(4);
});

it('never goes below 2 workers', function (): void {
    expect(cpuCores(shell: '2')->workers())->toBe(CpuCores::MIN_WORKERS)
        ->and(cpuCores(shell: '1', ci: true)->workers())->toBe(CpuCores::MIN_WORKERS);
});

it('never goes above 15 workers, even when a container sees the host cores', function (): void {
    expect(cpuCores(cpuInfo: cpuInfo(64), ci: true)->workers())->toBe(CpuCores::MAX_WORKERS);
});

it('uses the fallback before the reserve and the clamp', function (): void {
    expect(cpuCores()->workers())->toBe(2)
        ->and(cpuCores(ci: true)->workers())->toBe(4);
});

it('reads the real environment without failing', function (): void {
    expect(CpuCores::fromEnvironment()->workers())->toBeGreaterThanOrEqual(CpuCores::MIN_WORKERS)
        ->toBeLessThanOrEqual(CpuCores::MAX_WORKERS);
});
