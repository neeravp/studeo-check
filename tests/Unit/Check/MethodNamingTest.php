<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\MethodNaming;

it('flags every banned method name', function (string $name) {
    $files = ['Services/Thing.php' => phpFile("final class Thing { public function {$name}() {} }", 'Studeo\\Blog\\Services')];

    $found = runRule(MethodNaming::options(), $files);

    expect(summarise($found))->toBe(["modules/Blog/Services/Thing.php:6 Thing::{$name}"])
        ->and($found[0]->rule)->toBe('method-naming')
        ->and($found[0]->message)->toContain($name)->toContain('intent');
})->with(['process', 'processData', 'handleData', 'doStuff', 'doWork']);

it('passes intent-revealing names', function () {
    $files = ['Services/Thing.php' => phpFile('final class Thing { public function verifyActiveSubscriptionStatus() {} }', 'Studeo\\Blog\\Services')];

    expect(runRule(MethodNaming::options(), $files))->toBe([]);
});

it('checks the whole module by default', function (string $path) {
    $files = [$path => phpFile('final class Thing { public function doWork() {} }')];

    expect(runRule(MethodNaming::options(), $files))->toHaveCount(1);
})->with(['Models/Thing.php', 'database/seeders/Thing.php', 'tests/Thing.php', 'Actions/Thing.php']);

it('flags a banned top-level function and an anonymous class method', function () {
    $files = ['Actions/Run.php' => phpFile('function process() {} $x = new class { public function doStuff() {} };')];

    expect(summarise(runRule(MethodNaming::options(), $files)))->toBe([
        'modules/Blog/Actions/Run.php:6 process()',
        'modules/Blog/Actions/Run.php:6 class@anonymous::doStuff',
    ]);
});

it('takes its banned list and scope from options', function () {
    $files = ['Models/Thing.php' => phpFile('final class Thing { public function run() {} public function doWork() {} }', 'Studeo\\Blog\\Models')];

    expect(summarise(runRule(MethodNaming::options(banned: ['run']), $files)))->toBe(['modules/Blog/Models/Thing.php:6 Thing::run'])
        ->and(runRule(MethodNaming::options(scope: ['Actions']), $files))->toBe([]);
});

it('rejects invalid options naming the rule and the option', function (array $options, string $option) {
    expect(fn () => MethodNaming::options(...$options))
        ->toThrow(InvalidRuleConfigurationException::class, "[method-naming] option [{$option}]");
})->with([
    'empty banned list' => [['banned' => []], 'banned'],
    'blank banned name' => [['banned' => ['']], 'banned'],
    'empty scope' => [['scope' => []], 'scope'],
]);
