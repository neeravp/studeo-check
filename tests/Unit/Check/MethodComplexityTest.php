<?php

use Studeo\Check\Engine\Severity;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\MethodComplexity;
use Studeo\Check\Tests\Support\RuleFiles;

it('flags a method with three if or elseif', function () {
    $files = ['Actions/Run.php' => phpFile('final class Run { public function go($a, $b, $c) { '.RuleFiles::threeIfs().' return 0; } }')];

    $found = runRule(MethodComplexity::options(), $files);

    expect(summarise($found))->toBe(['modules/Blog/Actions/Run.php:6 Run::go'])
        ->and($found[0]->rule)->toBe('method-complexity')
        ->and($found[0]->severity)->toBe(Severity::Error)
        ->and($found[0]->message)->toContain('3')->toContain('max 2')->toContain('early return');
});

it('passes a method with two conditionals', function () {
    $files = ['Actions/Run.php' => phpFile('final class Run { public function go($a, $b) { if ($a) { return 1; } elseif ($b) { return 2; } return 0; } }')];

    expect(runRule(MethodComplexity::options(), $files))->toBe([]);
});

it('counts conditionals inside closures toward the enclosing method', function () {
    $files = ['Actions/Run.php' => phpFile('final class Run { public function go($a, $b) { if ($a) { return 1; } return array_map(function ($x) use ($b) { if ($b) { return 1; } if ($x) { return 2; } return 3; }, []); } }')];

    expect(summarise(runRule(MethodComplexity::options(), $files)))->toBe(['modules/Blog/Actions/Run.php:6 Run::go']);
});

it('treats the methods of an anonymous class as their own subjects', function () {
    $files = ['Actions/Run.php' => phpFile('final class Run { public function go($a) { if ($a) { return 1; } $x = new class { public function inner($a, $b, $c) { if ($a) { return 1; } if ($b) { return 2; } if ($c) { return 3; } return 0; } public function fine($a) { if ($a) { return 1; } return 0; } }; if ($a) { return 2; } return $x; } }')];

    expect(summarise(runRule(MethodComplexity::options(), $files)))->toBe(['modules/Blog/Actions/Run.php:6 class@anonymous::inner']);
});

it('checks the CAP 1 directories by default and ignores a file outside them', function (string $path, bool $flagged) {
    $files = [$path => phpFile('final class Run { public function go($a, $b, $c) { '.RuleFiles::threeIfs().' return 0; } }')];

    expect(runRule(MethodComplexity::options(), $files) !== [])->toBe($flagged);
})->with([
    'Actions' => ['Actions/Run.php', true],
    'nested Livewire' => ['Livewire/Pages/Run.php', true],
    'Models' => ['Models/Run.php', false],
    'migrations' => ['database/migrations/Run.php', false],
    'routes' => ['routes/Run.php', false],
    'tests' => ['tests/Run.php', false],
]);

it('takes its scope and maximum from options', function () {
    $files = ['Models/Run.php' => phpFile('final class Run { public function go($a, $b, $c) { '.RuleFiles::threeIfs().' return 0; } }')];

    expect(runRule(MethodComplexity::options(scope: ['Models']), $files))->toHaveCount(1)
        ->and(runRule(MethodComplexity::options(max: 3, scope: ['Models']), $files))->toBe([]);
});

it('does not parse blade files', function () {
    $files = ['Livewire/page.blade.php' => '<?php if ($a) {} if ($b) {} if ($c) {} ?><div></div>'];

    expect(runRule(MethodComplexity::options(), $files))->toBe([]);
});

it('rejects invalid options naming the rule and the option', function (array $options, string $option) {
    expect(fn () => MethodComplexity::options(...$options))
        ->toThrow(InvalidRuleConfigurationException::class, "[method-complexity] option [{$option}]");
})->with([
    'zero max' => [['max' => 0], 'max'],
    'negative max' => [['max' => -1], 'max'],
    'empty scope' => [['scope' => []], 'scope'],
    'blank scope entry' => [['scope' => ['']], 'scope'],
]);
