<?php

namespace Studeo\Check\Console\Formatters;

use Studeo\Check\Engine\CheckResult;
use Studeo\Check\Engine\Violation;

final readonly class JsonFormatter
{
    public function format(CheckResult $result): string
    {
        $items = [
            ...array_map(fn (Violation $violation): array => $violation->toArray(), $result->violations),
            ...array_map($this->formatStale(...), $result->staleBaseline),
        ];

        return (string) json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{rule: string, file: string, line: int, subject: string, severity: string, message: string}
     */
    private function formatStale(string $stale): array
    {
        $parts = explode(' ', $stale, 3);

        return [
            'rule' => 'baseline',
            'file' => $parts[1] ?? 'unknown',
            'line' => 1,
            'subject' => $parts[2] ?? '',
            'severity' => 'error',
            'message' => "Stale baseline entry for [{$parts[0]}]: no longer violated.",
        ];
    }
}
