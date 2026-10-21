<?php

use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\Laravel\ActionShape;
use Studeo\Check\Tests\Support\RuleFiles;

it('reports every problem of one class in the fixed order, on one line', function () {
    $uses = "use Lorisleiva\\Actions\\Concerns\\AsInvokable;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;";
    $body = 'class Wrong implements ShouldQueue { use AsInvokable; public function run(): void {} }';

    $found = runRule(ActionShape::options(), ['Actions/Wrong.php' => phpFile($body, uses: $uses)]);

    expect(array_map(fn (Violation $violation): int => $violation->line, $found))->toBe([7, 7, 7, 7, 7])
        ->and(array_map(fn (Violation $violation): string => $violation->subject, $found))->toBe(['Wrong', 'Wrong', 'Wrong', 'Wrong', 'Wrong'])
        ->and(array_map(fn (Violation $violation): string => $violation->rule, $found))->toBe(array_fill(0, 5, 'action-shape'))
        ->and(array_map(fn (Violation $violation): string => substr($violation->message, 0, 22), $found))->toBe([
            'Actions must be final ',
            'class name must end wi',
            'Actions expose exactly',
            'must not use AsInvokab',
            'Actions must not imple',
        ]);
});

it('lists the classes of one file in order, each with its own line and problems', function () {
    $body = "class First { public function a() {} }\n\nfinal class SecondAction { public function __invoke() {} public function b() {} }";

    $found = runRule(ActionShape::options(), ['Actions/Two.php' => phpFile($body)]);

    expect(summarise($found))->toBe([
        'modules/Blog/Actions/Two.php:6 First',
        'modules/Blog/Actions/Two.php:6 First',
        'modules/Blog/Actions/Two.php:6 First',
        'modules/Blog/Actions/Two.php:8 SecondAction',
    ]);
});

it('keeps the order of classes with a valid class between two invalid ones', function () {
    $body = "final class AAction { public function a() {} }\nfinal class BAction { public function __invoke() {} }\nfinal class CAction { public function c() {} }";

    expect(summarise(runRule(ActionShape::options(), ['Actions/Three.php' => phpFile($body)])))->toBe([
        'modules/Blog/Actions/Three.php:6 AAction',
        'modules/Blog/Actions/Three.php:8 CAction',
    ]);
});

it('skips abstract and anonymous classes', function () {
    $body = "abstract class BaseAction { public function a() {} public function b() {} }\nfinal class RunAction { public function __invoke() { return new class { public function x() {} }; } }";

    expect(runRule(ActionShape::options(), ['Actions/Skip.php' => phpFile($body)]))->toBe([]);
});

it('reports a class with a doc comment and attributes at its first attribute line', function () {
    $body = "/** The action. */\n#[First]\n#[Second]\nclass Plain {}";

    expect(summarise(runRule(ActionShape::options(), ['Actions/Plain.php' => phpFile($body)])))->toBe([
        'modules/Blog/Actions/Plain.php:7 Plain',
        'modules/Blog/Actions/Plain.php:7 Plain',
        'modules/Blog/Actions/Plain.php:7 Plain',
    ]);
});

it('names the class by its short name in a nested folder', function () {
    $found = runRule(ActionShape::options(), ['Actions/Posts/Run.php' => phpFile('class Run { public function __invoke() {} }', 'Studeo\\Blog\\Actions\\Posts')]);

    expect(summarise($found))->toBe(['modules/Blog/Actions/Posts/Run.php:6 Run', 'modules/Blog/Actions/Posts/Run.php:6 Run']);
});

it('returns nothing for a file without a class', function () {
    expect(runRule(ActionShape::options(), ['Actions/functions.php' => phpFile('function helper() {}')]))->toBe([]);
});

it('counts public static methods but not the constructor', function () {
    $body = 'final class RunAction { public function __construct() {} public function __invoke() {} public static function make() {} }';
    $only = 'final class OnlyAction { public function __construct() {} }';

    $found = runRule(ActionShape::options(), ['Actions/Run.php' => phpFile($body), 'Actions/Only.php' => phpFile($only)]);

    expect($found)->toHaveCount(2)
        ->and($found[0]->message)->toContain('found: none')
        ->and($found[1]->message)->toContain('found: __invoke, make');
});

it('lists the files of a scope in file name order', function () {
    $files = ['Actions/B.php' => phpFile('final class BAction {}'), 'Actions/A.php' => phpFile('final class AAction {}')];

    expect(summarise(runRule(ActionShape::options(), $files)))->toBe(['modules/Blog/Actions/A.php:6 AAction', 'modules/Blog/Actions/B.php:6 BAction']);
});

it('matches AsInvokable and ShouldQueue by their exact name, not by a name suffix', function (string $declaration, string $uses) {
    expect(runRule(ActionShape::options(), RuleFiles::action($declaration, $uses)))->toBe([]);
})->with([
    'NotShouldQueue' => ['final class RunAction implements NotShouldQueue { public function __invoke(): void {} }', 'use Acme\\NotShouldQueue;'],
    'NotAsInvokable' => ['final class RunAction { use NotAsInvokable; public function __invoke(): void {} }', 'use Acme\\NotAsInvokable;'],
]);

it('matches ShouldQueue and AsInvokable by their short name from any namespace', function (string $declaration, string $uses) {
    expect(runRule(ActionShape::options(), RuleFiles::action($declaration, $uses)))->toHaveCount(1);
})->with([
    'qualified name' => ['final class RunAction implements \\Illuminate\\Contracts\\Queue\\ShouldQueue { public function __invoke(): void {} }', ''],
    'aliased import' => ['final class RunAction implements Queued { public function __invoke(): void {} }', 'use Illuminate\\Contracts\\Queue\\ShouldQueue as Queued;'],
    'qualified trait' => ['final class RunAction { use \\Lorisleiva\\Actions\\Concerns\\AsInvokable; public function __invoke(): void {} }', ''],
]);
