<?php

use Studeo\Check\Engine\Severity;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\ConditionExtraction;

function conditionFile(string $body, string $path = 'Actions/Run.php'): array
{
    return [$path => phpFile("final class Run { public function go(\$a, \$b) { {$body} } }")];
}

it('flags a boolean operator in a multi-statement method and says how to fix it', function () {
    $found = runRule(ConditionExtraction::options(), conditionFile('if ($a && $b) { return 1; } return 0;'));

    expect(summarise($found))->toBe(['modules/Blog/Actions/Run.php:6 Run::go'])
        ->and($found[0]->rule)->toBe('condition-extraction')
        ->and($found[0]->severity)->toBe(Severity::Error)
        ->and($found[0]->message)->toContain('private method')->toContain('hasActiveSubscription()')->toContain('return …;');
});

it('flags the operator wherever it appears in a multi-statement method', function (string $body) {
    expect(summarise(runRule(ConditionExtraction::options(), conditionFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 Run::go']);
})->with([
    '&& in if' => ['if ($a && $b) { return 1; } return 0;'],
    '|| in if' => ['if ($a || $b) { return 1; } return 0;'],
    'and' => ['if ($a and $b) { return 1; } return 0;'],
    'or' => ['if ($a or $b) { return 1; } return 0;'],
    'ternary' => ['$x = ($a && $b) ? 1 : 2; return $x;'],
    'match arm' => ['$x = match (true) { $a && $b => 1, default => 2 }; return $x;'],
    'assignment' => ['$x = $a || $b; return $x;'],
    'statement as control flow' => ['$a || throw new Exception; return 1;'],
    'short circuit append' => ['$list = []; $a && $list[] = 1; return $list;'],
    'argument' => ['strlen($a && $b); return 1;'],
    'while' => ['while ($a && $b) { break; } return 1;'],
    'arrow function' => ['$c = 1; return array_filter($a, fn ($x) => $x && $b);'],
    'closure' => ['$c = 1; return array_filter($a, function ($x) use ($b) { return $x || $b; });'],
    'in a nested block' => ['foreach ($a as $c) { if ($c && $b) { echo 1; } } return 1;'],
]);

it('reports a method once however many operators it holds', function () {
    $found = runRule(ConditionExtraction::options(), conditionFile('if ($a && $b || $a) { return 1; } if ($a and $b) { return 2; } return 0;'));

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toContain('3 boolean operators');
});

it('allows a one-line helper whose whole body is a single return', function (string $body) {
    expect(runRule(ConditionExtraction::options(), conditionFile($body)))->toBe([]);
})->with([
    '&&' => ['return $a && $b;'],
    '||' => ['return $a || $b;'],
    'and' => ['return $a and $b;'],
    'or' => ['return $a or $b;'],
    'mixed' => ['return ($a && $b) || ($a && ! $b);'],
    'operator inside an arrow function of the return' => ['return array_filter($a, fn ($x) => $x && $b);'],
]);

it('does not allow an operator in a method that has a return and another statement', function () {
    expect(runRule(ConditionExtraction::options(), conditionFile('$x = 1; return $a && $b;')))->toHaveCount(1);
});

it('does not allow an operator in a method whose single statement is not a return', function () {
    expect(runRule(ConditionExtraction::options(), conditionFile('if ($a && $b) { echo 1; }')))->toHaveCount(1);
});

it('passes code without a boolean operator', function () {
    $body = 'if (! $a) { return 1; } $x = $a ?? $b; $y = $a ?: $b; return match ($x) { 1 => $y, default => 0 } | 2 ^ 3 & 4;';

    expect(runRule(ConditionExtraction::options(), conditionFile($body)))->toBe([]);
});

it('treats the methods of an anonymous class as their own subjects', function () {
    $body = '$x = new class { public function inner($a, $b) { return $a && $b; } public function bad($a, $b) { $c = 1; return $a && $b; } }; return $x;';

    expect(summarise(runRule(ConditionExtraction::options(), conditionFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 class@anonymous::bad']);
});

it('does not charge an anonymous class operator to the enclosing method', function () {
    $body = '$x = new class { public function inner($a, $b) { return $a && $b; } }; return $x;';

    expect(runRule(ConditionExtraction::options(), conditionFile($body)))->toBe([]);
});

it('checks the CAP 1 directories by default and ignores a file outside them', function (string $path, bool $flagged) {
    expect(runRule(ConditionExtraction::options(), conditionFile('if ($a && $b) { return 1; } return 0;', $path)) !== [])->toBe($flagged);
})->with([
    'Listeners' => ['Listeners/Run.php', true],
    'Models' => ['Models/Run.php', false],
    'database' => ['database/migrations/Run.php', false],
]);

it('takes its scope from options', function () {
    expect(runRule(ConditionExtraction::options(scope: ['Models']), conditionFile('if ($a && $b) { return 1; } return 0;', 'Models/Run.php')))->toHaveCount(1);
});

it('has the id condition-extraction and runs by default', function () {
    expect(ConditionExtraction::options()->name())->toBe('condition-extraction');
});

it('rejects invalid scope options naming the rule and the option', function (array $scope) {
    expect(fn () => ConditionExtraction::options(scope: $scope))
        ->toThrow(InvalidRuleConfigurationException::class, '[condition-extraction] option [scope]');
})->with([
    'empty' => [[]],
    'blank entry' => [['']],
]);
