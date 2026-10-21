<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\ActionShape;
use Studeo\Check\Tests\Support\RuleFiles;

it('passes both Section 6 signatures', function (string $invoke) {
    $declaration = "final class CreatePostAction { public function __construct(private readonly Dep \$dep) {} {$invoke} }";

    expect(runRule(ActionShape::options(), RuleFiles::action($declaration)))->toBe([]);
})->with([
    'with an actor' => ['public function __invoke(CreatePostInput $input, Authenticatable $actor): void {}'],
    'system' => ['public function __invoke(SweepInput $input): void {}'],
]);

it('flags each broken part of the shape', function (string $declaration, string $uses, string $expected) {
    $found = runRule(ActionShape::options(), RuleFiles::action($declaration, $uses));

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('action-shape')
        ->and($found[0]->subject)->toStartWith('CreatePost')
        ->and($found[0]->message)->toContain($expected);
})->with([
    'not final' => ['class CreatePostAction { public function __invoke(): void {} }', '', 'final'],
    'name' => ['final class CreatePost { public function __invoke(): void {} }', '', "must end with 'Action'"],
    'extra public method' => ['final class CreatePostAction { public function __invoke(): void {} public function helper(): void {} }', '', 'found: __invoke, helper'],
    'no __invoke' => ['final class CreatePostAction { public function run(): void {} }', '', 'found: run'],
    'AsInvokable' => ['final class CreatePostAction { use AsInvokable; public function __invoke(): void {} }', 'use Lorisleiva\\Actions\\Concerns\\AsInvokable;', 'AsInvokable'],
    'ShouldQueue' => ['final class CreatePostAction implements ShouldQueue { public function __invoke(): void {} }', 'use Illuminate\\Contracts\\Queue\\ShouldQueue;', 'ShouldQueue'],
]);

it('allows private and protected helpers and ignores abstract classes', function () {
    $declaration = 'final class CreatePostAction { public function __invoke(): void {} private function a() {} protected function b() {} } abstract class BaseAction { public function x() {} }';

    expect(runRule(ActionShape::options(), RuleFiles::action($declaration)))->toBe([]);
});

it('only looks at Actions by default, including nested folders', function () {
    $bad = phpFile('class Helper { public function a() {} }');

    expect(runRule(ActionShape::options(), ['Services/Helper.php' => $bad]))->toBe([])
        ->and(runRule(ActionShape::options(), ['Actions/Posts/Helper.php' => $bad]))->not->toBe([]);
});

it('takes its scope from options', function () {
    expect(runRule(ActionShape::options(scope: ['Services']), ['Services/Helper.php' => phpFile('class Helper {}')]))->not->toBe([]);
});

it('rejects an empty scope naming the rule and the option', function () {
    expect(fn () => ActionShape::options(scope: []))->toThrow(InvalidRuleConfigurationException::class, '[action-shape] option [scope]');
});
