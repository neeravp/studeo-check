<?php

namespace Studeo\Check\Console\Formatters;

use Studeo\Check\Engine\CheckResult;
use Studeo\Check\Engine\Violation;

final readonly class TextFormatter
{
    public function format(CheckResult $result): string
    {
        $lines = [
            ...array_map($this->formatViolation(...), $result->violations),
            ...array_map($this->formatStale(...), $result->staleBaseline),
            $this->summary($result),
        ];

        return implode("\n", $lines)."\n";
    }

    private function formatViolation(Violation $violation): string
    {
        return "{$violation->file}:{$violation->line} [{$violation->rule}] {$violation->message}";
    }

    private function formatStale(string $stale): string
    {
        $parts = explode(' ', $stale, 3);
        $rule = $parts[0] ?? 'unknown';
        $file = $parts[1] ?? 'unknown';
        $subject = $parts[2] ?? '';

        return "{$file}:1 [baseline] Stale baseline entry for [{$rule}] {$subject}: no longer violated.";
    }

    private function summary(CheckResult $result): string
    {
        $errors = $result->errorCount();
        $warnings = $result->warningCount();

        $errorWord = $errors === 1 ? 'error' : 'errors';
        $warningWord = $warnings === 1 ? 'warning' : 'warnings';

        return "{$errors} {$errorWord}, {$warnings} {$warningWord}.";
    }
}
