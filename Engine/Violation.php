<?php

namespace Studeo\Check\Engine;

final readonly class Violation
{
    public function __construct(
        public string $rule,
        public string $file,
        public int $line,
        public string $subject,
        public Severity $severity,
        public string $message,
    ) {}

    /**
     * @return array{rule: string, file: string, line: int, subject: string, severity: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'file' => $this->file,
            'line' => $this->line,
            'subject' => $this->subject,
            'severity' => $this->severity->value,
            'message' => $this->message,
        ];
    }
}
