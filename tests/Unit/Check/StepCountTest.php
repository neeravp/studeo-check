<?php

use Studeo\Check\Engine\Severity;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\StepCount;

function stepFile(string $body, string $path = 'Actions/Run.php'): array
{
    return [$path => phpFile("final class Run { public function go(\$a) { {$body} } }")];
}

function steps(int $count): string
{
    return implode(' ', array_map(fn (int $number): string => "\$s{$number} = {$number};", range(1, $count)));
}

it('flags a method with more than eight statements and says how to fix it', function () {
    $found = runRule(StepCount::options(), stepFile(steps(9)));

    expect(summarise($found))->toBe(['modules/Blog/Actions/Run.php:6 Run::go'])
        ->and($found[0]->rule)->toBe('step-count')
        ->and($found[0]->severity)->toBe(Severity::Error)
        ->and($found[0]->message)->toContain('9 statements')->toContain('max 8')->toContain('named private helpers')->toContain('short pipeline');
});

it('passes a method with exactly eight statements', function () {
    expect(runRule(StepCount::options(), stepFile(steps(8))))->toBe([]);
});

it('does not count what is inside a nested block', function () {
    $body = 'if ($a) { '.steps(5).' } foreach ($a as $b) { '.steps(5).' } '.steps(6);

    expect(runRule(StepCount::options(), stepFile($body)))->toBe([]);
});

it('counts a method that is only one block with many statements as one step', function () {
    expect(runRule(StepCount::options(), stepFile('if ($a) { '.steps(20).' }')))->toBe([]);
});

it('treats a closure body as its own subject, counted the same way', function () {
    $body = 'return array_map(function ($x) { '.steps(9).' return $x; }, $a);';

    $found = runRule(StepCount::options(), stepFile($body));

    expect(summarise($found))->toBe(['modules/Blog/Actions/Run.php:6 Run::go{closure}'])
        ->and($found[0]->message)->toContain('10 statements');
});

it('does not add a closure body to the statements of its method', function () {
    $body = steps(7).' $f = function ($x) { '.steps(7).' return $x; };';

    expect(runRule(StepCount::options(), stepFile($body)))->toBe([]);
});

it('never counts an arrow function', function () {
    $body = steps(7).' return array_map(fn ($x) => [$x, $x, $x, $x, $x, $x, $x, $x, $x, $x], $a);';

    expect(runRule(StepCount::options(), stepFile($body)))->toBe([]);
});

it('names a closure inside a closure by both levels', function () {
    $body = 'return function () { return function () { '.steps(9).' }; };';

    expect(summarise(runRule(StepCount::options(), stepFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 Run::go{closure}{closure}']);
});

it('treats the methods of an anonymous class as their own subjects', function () {
    $body = '$x = new class { public function inner() { '.steps(9).' } public function fine() { '.steps(2).' } }; return $x;';

    expect(summarise(runRule(StepCount::options(), stepFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 class@anonymous::inner']);
});

it('checks abstract and empty methods without failing', function () {
    $files = ['Actions/Run.php' => phpFile('abstract class Run { abstract public function go(); public function __construct() {} }')];

    expect(runRule(StepCount::options(), $files))->toBe([]);
});

it('checks the CAP 1 directories by default and ignores a file outside them', function (string $path, bool $flagged) {
    expect(runRule(StepCount::options(), stepFile(steps(9), $path)) !== [])->toBe($flagged);
})->with([
    'Actions' => ['Actions/Run.php', true],
    'nested Livewire' => ['Livewire/Pages/Run.php', true],
    'Models' => ['Models/Run.php', false],
    'migrations' => ['database/migrations/Run.php', false],
]);

it('takes its maximum and scope from options', function () {
    expect(runRule(StepCount::options(max: 9), stepFile(steps(9))))->toBe([])
        ->and(runRule(StepCount::options(max: 2), stepFile(steps(3))))->toHaveCount(1)
        ->and(runRule(StepCount::options(scope: ['Models']), stepFile(steps(9), 'Models/Run.php')))->toHaveCount(1);
});

it('has the id step-count and a default maximum of eight', function () {
    expect(StepCount::options()->name())->toBe('step-count');
});

it('rejects invalid options naming the rule and the option', function (array $options, string $option) {
    expect(fn () => StepCount::options(...$options))
        ->toThrow(InvalidRuleConfigurationException::class, "[step-count] option [{$option}]");
})->with([
    'zero max' => [['max' => 0], 'max'],
    'negative max' => [['max' => -1], 'max'],
    'empty scope' => [['scope' => []], 'scope'],
    'blank scope entry' => [['scope' => ['']], 'scope'],
]);
