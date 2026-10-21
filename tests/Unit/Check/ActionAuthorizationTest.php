<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\ActionAuthorization;

function authAction(string $body, bool $system = false, string $name = 'CreatePostAction'): array
{
    $attribute = $system ? "#[SystemAction]\n" : '';

    return ["Actions/{$name}.php" => phpFile("{$attribute}final class {$name}\n{\n    {$body}\n}", uses: SYSTEM_USES)];
}

const NORMAL_INVOKE = "public function __invoke(CreatePostInput \$input, Authenticatable \$actor): void\n    {\n        Gate::forUser(\$actor)->authorize('create', Post::class);\n    }";

it('passes a normal Action with an actor and Gate::forUser()->authorize()', function (string $body) {
    expect(runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE), authAction($body)))->toBe([]);
})->with([
    'inline' => [NORMAL_INVOKE],
    'nullable actor' => ["public function __invoke(CreatePostInput \$input, ?Authenticatable \$actor): void { Gate::forUser(\$actor)->authorize('x'); }"],
    'private helper' => ["public function __invoke(CreatePostInput \$input, Authenticatable \$actor): void { \$this->authorizeCreation(\$actor); }\n    private function authorizeCreation(Authenticatable \$actor): void { Gate::forUser(\$actor)->authorize('create', Post::class); }"],
    'facade alias' => ["public function __invoke(CreatePostInput \$input, Authenticatable \$actor): void { \\Gate::forUser(\$actor)->authorize('x'); }"],
]);

it('fails a normal Action missing the actor, the Gate call or both, naming what is missing', function (string $body, array $contains) {
    $found = runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE), authAction($body));

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('action-authorization')
        ->and($found[0]->subject)->toBe('Studeo\\Blog\\Actions\\CreatePostAction')
        ->and($found[0]->file)->toBe('modules/Blog/Actions/CreatePostAction.php');

    foreach ($contains as $text) {
        expect($found[0]->message)->toContain($text);
    }
})->with([
    'no actor' => ["public function __invoke(CreatePostInput \$input): void { Gate::forUser(\$x)->authorize('x'); }", ['Authenticatable']],
    'no Gate' => ['public function __invoke(CreatePostInput $input, Authenticatable $actor): void {}', ['Gate::forUser']],
    'Gate without forUser' => ["public function __invoke(CreatePostInput \$input, Authenticatable \$actor): void { Gate::allows('x'); }", ['Gate::forUser']],
    'authorize without Gate' => ["public function __invoke(CreatePostInput \$input, Authenticatable \$actor): void { \$this->gate->authorize('x'); }", ['Gate::forUser']],
    'actor on a helper only' => ["public function __invoke(CreatePostInput \$input): void { \$this->go(); }\n    private function go(Authenticatable \$actor): void { Gate::forUser(\$actor)->authorize('x'); }", ['Authenticatable']],
    'neither' => ['public function __invoke(CreatePostInput $input): void {}', ['Authenticatable', 'Gate::forUser']],
]);

it('passes a system Action with neither', function () {
    expect(runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE), authAction('public function __invoke(SweepInput $input): void {}', true, 'SweepAction')))->toBe([]);
});

it('fails a system Action that takes an actor or authorizes, with one finding naming what is present', function (string $body, array $contains) {
    $found = runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE, attributionHint: SYSTEM_ATTRIBUTION_HINT), authAction($body, true, 'SweepAction'));

    expect($found)->toHaveCount(1)
        ->and($found[0]->subject)->toBe('Studeo\\Blog\\Actions\\SweepAction');

    foreach ($contains as $text) {
        expect($found[0]->message)->toContain($text);
    }
})->with([
    'actor' => ['public function __invoke(SweepInput $input, Authenticatable $actor): void {}', ['Authenticatable', '#[Context]']],
    'nullable actor' => ['public function __invoke(SweepInput $input, ?Authenticatable $actor): void {}', ['Authenticatable']],
    'Gate' => ["public function __invoke(SweepInput \$input): void { Gate::allows('sweep'); }", ['Gate']],
    'authorize' => ["public function __invoke(SweepInput \$input): void { \$this->gate->authorize('sweep'); }", ['authorize']],
    'helper' => ["public function __invoke(SweepInput \$input): void {}\n    private function check(): void { \$this->gate?->authorize('x'); }", ['authorize']],
    'both' => ["public function __invoke(SweepInput \$input, Authenticatable \$actor): void { Gate::forUser(\$actor)->authorize('x'); }", ['Authenticatable', 'Gate']],
]);

it('matches the attribute and the Gate by full name', function () {
    $other = ['Actions/SweepAction.php' => phpFile("#[SystemAction]\nfinal class SweepAction\n{\n    public function __invoke(): void { Gate::forUser(1)->authorize('x'); }\n}", uses: "use Other\\SystemAction;\nuse Other\\Gate;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\n")];

    expect(array_column(runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE), $other), 'message'))->toHaveCount(1);
});

it('ignores abstract classes, other directories and anonymous classes', function () {
    $abstract = ['Actions/BaseAction.php' => phpFile('abstract class BaseAction {}', uses: SYSTEM_USES)];
    $elsewhere = ['Services/CreatePostAction.php' => phpFile('final class CreatePostAction {}', 'Studeo\\Blog\\Services', SYSTEM_USES)];

    expect(runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE), [...$abstract, ...$elsewhere]))->toBe([])
        ->and(runRule(ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE, scope: ['Services']), $elsewhere))->toHaveCount(1);
});

it('rejects an empty scope naming the rule and the option', function () {
    expect(fn () => ActionAuthorization::options(systemAttribute: SYSTEM_ATTRIBUTE, scope: []))->toThrow(InvalidRuleConfigurationException::class, '[action-authorization] option [scope]');
});
