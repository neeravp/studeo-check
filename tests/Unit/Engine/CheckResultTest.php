<?php

use Studeo\Check\Engine\CheckResult;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\Violation;

function hasAnyViolationsOrStale(CheckResult $result): bool
{
    $method = new ReflectionMethod(CheckResult::class, 'hasAnyViolationsOrStale');

    return (bool) $method->invoke($result);
}

function makeWarningViolation(): Violation
{
    return new Violation('rule-w', 'file.php', 10, 'subject', Severity::Warning, 'msg');
}

function makeErrorViolation(): Violation
{
    return new Violation('rule-e', 'file.php', 20, 'subject', Severity::Error, 'msg');
}

it('evaluates exit-relevant methods for (a) no findings', function () {
    $withoutBaseline = new CheckResult([]);
    expect($withoutBaseline->failed())->toBeFalse()
        ->and(hasAnyViolationsOrStale($withoutBaseline))->toBeFalse()
        ->and($withoutBaseline->errorCount())->toBe(0)
        ->and($withoutBaseline->warningCount())->toBe(0);

    $withBaseline = new CheckResult([], [], hasBaseline: true);
    expect($withBaseline->failed())->toBeFalse()
        ->and(hasAnyViolationsOrStale($withBaseline))->toBeFalse()
        ->and($withBaseline->errorCount())->toBe(0)
        ->and($withBaseline->warningCount())->toBe(0);
});

it('evaluates exit-relevant methods for (b) a warning only', function () {
    $warning = makeWarningViolation();

    $withoutBaseline = new CheckResult([$warning]);
    expect($withoutBaseline->failed())->toBeFalse()
        ->and(hasAnyViolationsOrStale($withoutBaseline))->toBeTrue()
        ->and($withoutBaseline->errorCount())->toBe(0)
        ->and($withoutBaseline->warningCount())->toBe(1);

    $withBaseline = new CheckResult([$warning], [], hasBaseline: true);
    expect($withBaseline->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBaseline))->toBeTrue()
        ->and($withBaseline->errorCount())->toBe(0)
        ->and($withBaseline->warningCount())->toBe(1);
});

it('evaluates exit-relevant methods for (c) an error', function () {
    $error = makeErrorViolation();

    $withoutBaseline = new CheckResult([$error]);
    expect($withoutBaseline->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withoutBaseline))->toBeTrue()
        ->and($withoutBaseline->errorCount())->toBe(1)
        ->and($withoutBaseline->warningCount())->toBe(0);

    $withBaseline = new CheckResult([$error], [], hasBaseline: true);
    expect($withBaseline->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBaseline))->toBeTrue()
        ->and($withBaseline->errorCount())->toBe(1)
        ->and($withBaseline->warningCount())->toBe(0);
});

it('evaluates exit-relevant methods for (d) a stale baseline entry only', function () {
    $staleSingle = ['file-length app/Services/Short.php file'];

    $withBaseline = new CheckResult([], $staleSingle, hasBaseline: true);
    expect($withBaseline->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBaseline))->toBeTrue()
        ->and($withBaseline->errorCount())->toBe(1)
        ->and($withBaseline->warningCount())->toBe(0);

    $staleMultiple = [
        'file-length app/Services/Short.php file',
        'method-complexity app/Services/Other.php go',
    ];
    $withBaselineMultiple = new CheckResult([], $staleMultiple, hasBaseline: true);
    expect($withBaselineMultiple->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBaselineMultiple))->toBeTrue()
        ->and($withBaselineMultiple->errorCount())->toBe(2)
        ->and($withBaselineMultiple->warningCount())->toBe(0);

    $withoutBaseline = new CheckResult([], $staleSingle, hasBaseline: false);
    expect($withoutBaseline->failed())->toBeFalse()
        ->and(hasAnyViolationsOrStale($withoutBaseline))->toBeTrue()
        ->and($withoutBaseline->errorCount())->toBe(1)
        ->and($withoutBaseline->warningCount())->toBe(0);
});

it('evaluates exit-relevant methods for (e) a new violation key with a baseline present', function () {
    $warning = makeWarningViolation();
    $withBaselineWarning = new CheckResult([$warning], hasBaseline: true);
    expect($withBaselineWarning->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBaselineWarning))->toBeTrue()
        ->and($withBaselineWarning->errorCount())->toBe(0)
        ->and($withBaselineWarning->warningCount())->toBe(1);

    $error = makeErrorViolation();
    $withBaselineError = new CheckResult([$error], hasBaseline: true);
    expect($withBaselineError->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBaselineError))->toBeTrue()
        ->and($withBaselineError->errorCount())->toBe(1)
        ->and($withBaselineError->warningCount())->toBe(0);

    $withBoth = new CheckResult([$error], ['rule-stale file.php subject'], hasBaseline: true);
    expect($withBoth->failed())->toBeTrue()
        ->and(hasAnyViolationsOrStale($withBoth))->toBeTrue()
        ->and($withBoth->errorCount())->toBe(2)
        ->and($withBoth->warningCount())->toBe(0);
});

it('evaluates exit-relevant methods for (f) no findings with hasBaseline true', function () {
    $result = new CheckResult([], [], hasBaseline: true);

    expect($result->failed())->toBeFalse()
        ->and($result->errorCount())->toBe(0)
        ->and($result->warningCount())->toBe(0);
});

it('evaluates exit-relevant methods for (g) one error with hasBaseline true', function () {
    $error = makeErrorViolation();
    $result = new CheckResult([$error], [], hasBaseline: true);

    expect($result->failed())->toBeTrue()
        ->and($result->errorCount())->toBe(1);
});

it('evaluates exit-relevant methods for (h) two stale entries plus one error with hasBaseline true', function () {
    $error = makeErrorViolation();
    $stale = ['rule1 file1 subject1', 'rule2 file2 subject2'];
    $result = new CheckResult([$error], $stale, hasBaseline: true);

    expect($result->errorCount())->toBe(3)
        ->and($result->failed())->toBeTrue();
});
