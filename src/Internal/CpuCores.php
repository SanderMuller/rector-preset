<?php declare(strict_types=1);

namespace SanderMuller\RectorPreset\Internal;

use Closure;

/**
 * Picks the number of parallel Rector workers from the host's cores.
 *
 * A fixed count oversubscribes a 2-4 vCPU CI runner, because each worker boots the full container.
 * The result keeps 2 cores free locally and none on CI, and is clamped to 2..15. The ceiling is the
 * old fixed value, so a container that sees the host's cores instead of its quota spawns no more
 * workers than before.
 *
 * @internal
 */
final readonly class CpuCores
{
    public const int MIN_WORKERS = 2;

    public const int MAX_WORKERS = 15;

    public const int FALLBACK_CORES = 4;

    public const int LOCAL_RESERVE = 2;

    /**
     * @param Closure(): ?string $cpuInfo contents of /proc/cpuinfo, or null when it is not readable
     * @param Closure(): ?string $shellCores output of the sysctl/nproc command, or null when shell_exec is unavailable
     * @param Closure(): bool $isCi whether the process runs on CI
     */
    public function __construct(
        private Closure $cpuInfo,
        private Closure $shellCores,
        private Closure $isCi,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            static function (): ?string {
                if (! is_readable('/proc/cpuinfo')) {
                    return null;
                }

                $contents = file_get_contents('/proc/cpuinfo');

                return $contents === false ? null : $contents;
            },
            static function (): ?string {
                if (! function_exists('shell_exec')) {
                    return null;
                }

                // A fixed literal: there is no injection surface.
                $output = shell_exec('sysctl -n hw.ncpu 2>/dev/null || nproc 2>/dev/null');

                return is_string($output) ? $output : null;
            },
            static fn (): bool => getenv('CI') !== false,
        );
    }

    public function workers(): int
    {
        $reserve = ($this->isCi)() ? 0 : self::LOCAL_RESERVE;

        return max(self::MIN_WORKERS, min(self::MAX_WORKERS, $this->cores() - $reserve));
    }

    public function cores(): int
    {
        $cpuInfo = ($this->cpuInfo)();

        if ($cpuInfo !== null) {
            // Anchored per line, so the first entry also counts.
            $processors = preg_match_all('/^processor\s*:/m', $cpuInfo);

            if (is_int($processors) && $processors > 0) {
                return $processors;
            }
        }

        $shellCores = ($this->shellCores)();

        if ($shellCores !== null) {
            $trimmed = trim($shellCores);

            if (ctype_digit($trimmed) && (int) $trimmed > 0) {
                return (int) $trimmed;
            }
        }

        return self::FALLBACK_CORES;
    }
}
