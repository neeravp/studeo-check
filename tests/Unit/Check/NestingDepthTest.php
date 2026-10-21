<?php

use Studeo\Check\Engine\Severity;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\NestingDepth;

function nestFile(string $body, string $path = 'Actions/Run.php'): array
{
    return [$path => phpFile("final class Run { public function go(\$a, \$b) { {$body} } }")];
}

it('flags a control block inside another and says how to fix it', function () {
    $found = runRule(NestingDepth::options(), nestFile('if ($a) { foreach ($b as $c) { echo $c; } }'));

    expect(summarise($found))->toBe(['modules/Blog/Actions/Run.php:6 Run::go'])
        ->and($found[0]->rule)->toBe('nesting-depth')
        ->and($found[0]->severity)->toBe(Severity::Error)
        ->and($found[0]->message)->toContain('depth 2')->toContain('max 1')->toContain('named helper');
});

it('passes control blocks that sit side by side at depth one', function () {
    $body = 'if ($a) { echo 1; } foreach ($b as $c) { echo $c; } while ($a) { break; } for (;;) { break; } do { echo 1; } while ($a); try { echo 1; } catch (Exception $e) { echo 2; } switch ($a) { case 1: echo 1; }';

    expect(runRule(NestingDepth::options(), nestFile($body)))->toBe([]);
});

it('flags every kind of block nested in an if', function (string $inner) {
    expect(summarise(runRule(NestingDepth::options(), nestFile("if (\$a) { {$inner} }"))))->toBe(['modules/Blog/Actions/Run.php:6 Run::go']);
})->with([
    'if' => ['if ($b) { echo 1; }'],
    'foreach' => ['foreach ($b as $c) { echo $c; }'],
    'for' => ['for (;;) { break; }'],
    'while' => ['while ($b) { break; }'],
    'do-while' => ['do { echo 1; } while ($b);'],
    'try' => ['try { echo 1; } catch (Exception $e) { echo 2; }'],
    'switch' => ['switch ($b) { case 1: echo 1; }'],
]);

it('does not count else, elseif, catch or finally as deeper levels', function () {
    $body = 'if ($a) { echo 1; } elseif ($b) { echo 2; } else { echo 3; } try { echo 1; } catch (Exception $e) { echo 2; } finally { echo 3; }';

    expect(runRule(NestingDepth::options(), nestFile($body)))->toBe([]);
});

it('reads else if written as two words as the same level', function () {
    expect(runRule(NestingDepth::options(), nestFile('if ($a) { echo 1; } else if ($b) { echo 2; } else { echo 3; }')))->toBe([]);
});

it('flags a block inside a branch of another block', function (string $body) {
    expect(summarise(runRule(NestingDepth::options(), nestFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 Run::go']);
})->with([
    'else' => ['if ($a) { echo 1; } else { echo 0; foreach ($b as $c) { echo $c; } }'],
    'elseif' => ['if ($a) { echo 1; } elseif ($b) { foreach ($b as $c) { echo $c; } }'],
    'catch' => ['try { echo 1; } catch (Exception $e) { if ($a) { echo 2; } }'],
    'finally' => ['try { echo 1; } finally { if ($a) { echo 2; } }'],
    'switch case' => ['switch ($a) { case 1: if ($b) { echo 1; } }'],
]);

it('does not treat match as a block', function () {
    $body = 'if ($a) { return match ($b) { 1 => 2, default => 3 }; } return 0;';

    expect(runRule(NestingDepth::options(), nestFile($body)))->toBe([]);
});

it('lets a closure start again at depth zero for its own body', function () {
    $body = 'if ($a) { return array_map(function ($x) { foreach ($x as $y) { echo $y; } return $x; }, $b); } return [];';

    expect(runRule(NestingDepth::options(), nestFile($body)))->toBe([]);
});

it('lets an arrow function start again at depth zero', function () {
    $body = 'if ($a) { return array_map(fn ($x) => array_map(function ($y) { if ($y) { return 1; } return 0; }, $x), $b); } return [];';

    expect(runRule(NestingDepth::options(), nestFile($body)))->toBe([]);
});

it('flags a nested block inside a closure as the closure', function () {
    $body = 'return array_map(function ($x) { if ($x) { foreach ($x as $y) { echo $y; } } return $x; }, $b);';

    expect(summarise(runRule(NestingDepth::options(), nestFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 Run::go{closure}']);
});

it('reports a method once however many blocks are nested too deep', function () {
    $body = 'if ($a) { if ($b) { echo 1; } if ($a) { echo 2; } } foreach ($a as $c) { while ($b) { break; } }';

    expect(runRule(NestingDepth::options(), nestFile($body)))->toHaveCount(1);
});

it('treats the methods of an anonymous class as their own subjects', function () {
    $body = '$x = new class { public function inner($a) { if ($a) { if ($a) { return 1; } } return 0; } }; return $x;';

    expect(summarise(runRule(NestingDepth::options(), nestFile($body))))->toBe(['modules/Blog/Actions/Run.php:6 class@anonymous::inner']);
});

it('takes its maximum and scope from options', function () {
    $body = 'if ($a) { if ($b) { echo 1; } }';

    expect(runRule(NestingDepth::options(max: 2), nestFile($body)))->toBe([])
        ->and(runRule(NestingDepth::options(max: 2), nestFile('if ($a) { if ($b) { if ($a) { echo 1; } } }')))->toHaveCount(1)
        ->and(runRule(NestingDepth::options(scope: ['Models']), nestFile($body, 'Models/Run.php')))->toHaveCount(1);
});

it('checks the CAP 1 directories by default and ignores a file outside them', function (string $path, bool $flagged) {
    expect(runRule(NestingDepth::options(), nestFile('if ($a) { if ($b) { echo 1; } }', $path)) !== [])->toBe($flagged);
})->with([
    'Services' => ['Services/Run.php', true],
    'Models' => ['Models/Run.php', false],
    'routes' => ['routes/Run.php', false],
]);

it('has the id nesting-depth and runs by default', function () {
    expect(NestingDepth::options()->name())->toBe('nesting-depth');
});

it('rejects invalid options naming the rule and the option', function (array $options, string $option) {
    expect(fn () => NestingDepth::options(...$options))
        ->toThrow(InvalidRuleConfigurationException::class, "[nesting-depth] option [{$option}]");
})->with([
    'zero max' => [['max' => 0], 'max'],
    'negative max' => [['max' => -1], 'max'],
    'empty scope' => [['scope' => []], 'scope'],
    'blank scope entry' => [['scope' => ['']], 'scope'],
]);
