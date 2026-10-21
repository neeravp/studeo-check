<?php

use Studeo\Check\Console\Formatters\JsonFormatter;
use Studeo\Check\Console\Formatters\TextFormatter;
use Studeo\Check\Engine\CheckResult;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\Violation;

it('formats text output with path:line [rule] message and summary', function () {
    $v1 = new Violation('file-length', 'app/A.php', 12, 'file', Severity::Error, '250 lines (max 230). Shorten the file.');
    $v2 = new Violation('step-count', 'app/B.php', 30, 'B::go', Severity::Warning, '9 statements (max 8).');
    $result = new CheckResult([$v1, $v2], ['method-complexity app/C.php C::old']);

    $formatter = new TextFormatter;
    $output = $formatter->format($result);

    expect($output)->toContain('app/A.php:12 [file-length] 250 lines (max 230). Shorten the file.')
        ->and($output)->toContain('app/B.php:30 [step-count] 9 statements (max 8).')
        ->and($output)->toContain('app/C.php:1 [baseline] Stale baseline entry for [method-complexity] C::old: no longer violated.')
        ->and($output)->toContain('2 errors, 1 warning.');
});

it('formats json output with exact keys rule, file, line, subject, severity, message', function () {
    $v1 = new Violation('file-length', 'app/A.php', 12, 'file', Severity::Error, 'Too long.');
    $result = new CheckResult([$v1], ['method-complexity app/C.php C::old']);

    $formatter = new JsonFormatter;
    $output = $formatter->format($result);
    $decoded = json_decode($output, true);

    expect($decoded)->toBe([
        [
            'rule' => 'file-length',
            'file' => 'app/A.php',
            'line' => 12,
            'subject' => 'file',
            'severity' => 'error',
            'message' => 'Too long.',
        ],
        [
            'rule' => 'baseline',
            'file' => 'app/C.php',
            'line' => 1,
            'subject' => 'C::old',
            'severity' => 'error',
            'message' => 'Stale baseline entry for [method-complexity]: no longer violated.',
        ],
    ]);
});
