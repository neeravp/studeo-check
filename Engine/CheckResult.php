<?php

namespace Studeo\Check\Engine;

final readonly class CheckResult
{
    /**
     * @param  list<Violation>  $violations  ordered by file, line and rule
     * @param  list<string>  $staleBaseline  baseline entries that are no longer violated
     */
    public function __construct(
        public array $violations,
        public array $staleBaseline = [],
        public bool $hasBaseline = false,
    ) {}

    public function errorCount(): int
    {
        return $this->count(Severity::Error) + count($this->staleBaseline);
    }

    public function warningCount(): int
    {
        return $this->count(Severity::Warning);
    }

    public function failed(): bool
    {
        if ($this->hasBaseline) {
            return $this->hasAnyViolationsOrStale();
        }

        return $this->count(Severity::Error) > 0;
    }

    private function hasAnyViolationsOrStale(): bool
    {
        return $this->violations !== [] || $this->staleBaseline !== [];
    }

    private function count(Severity $severity): int
    {
        return count(array_filter($this->violations, fn (Violation $violation): bool => $violation->severity === $severity));
    }
}
