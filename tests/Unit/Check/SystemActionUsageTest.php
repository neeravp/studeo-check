<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\SystemActionUsage;

function systemAction(string $body = 'public function __invoke(SweepInput $input): void {}'): array
{
    return ['Actions/SweepAction.php' => phpFile("#[SystemAction]\nfinal class SweepAction\n{\n    {$body}\n}", uses: SYSTEM_USES)];
}

function referencer(string $directory, string $body = 'public function run(\\Studeo\\Blog\\Actions\\SweepAction $action): void {}'): array
{
    return ["{$directory}/Caller.php" => phpFile("final class Caller\n{\n    {$body}\n}", 'Studeo\\Blog\\'.str_replace('/', '\\', $directory))];
}

it('flags a reference from each forbidden directory', function (string $directory) {
    $found = runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...systemAction(), ...referencer($directory)]);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('system-action-usage')
        ->and($found[0]->file)->toBe("modules/Blog/{$directory}/Caller.php")
        ->and($found[0]->subject)->toBe('Studeo\\Blog\\Actions\\SweepAction')
        ->and($found[0]->message)->toContain('Actions, Listeners and Jobs');
})->with(['Controllers', 'Middleware', 'ApiResources', 'Livewire']);

it('flags a reference made only through a use import or a nested Livewire directory', function () {
    $use = ['Controllers/Caller.php' => phpFile('final class Caller {}', 'Studeo\\Blog\\Controllers', "use Studeo\\Blog\\Actions\\SweepAction;\n")];
    $nested = referencer('Livewire/Forms');

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...systemAction(), ...$use]))->toHaveCount(1)
        ->and(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...systemAction(), ...$nested]))->toHaveCount(1);
});

it('reports a file once per system Action however often it names it', function () {
    $body = 'public function a(\\Studeo\\Blog\\Actions\\SweepAction $a, \\Studeo\\Blog\\Actions\\SweepAction $b): void {}';

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...systemAction(), ...referencer('Controllers', $body)]))->toHaveCount(1);
});

it('lets Actions, Listeners and Jobs reference a system Action', function (string $directory) {
    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...systemAction(), ...referencer($directory)]))->toBe([]);
})->with(['Actions', 'Listeners', 'Jobs']);

it('matches by full name, never by short name', function () {
    $other = ['Controllers/Caller.php' => phpFile('final class Caller { public function run(SweepAction $action): void {} }', 'Studeo\\Blog\\Controllers', "use Other\\Place\\SweepAction;\n")];

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...systemAction(), ...$other]))->toBe([]);
});

it('ignores a class outside Actions that carries the attribute', function () {
    $stray = ['Services/SweepAction.php' => phpFile("#[SystemAction]\nfinal class SweepAction {}", 'Studeo\\Blog\\Services', SYSTEM_USES)];

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...$stray, ...referencer('Controllers', 'public function run(\\Studeo\\Blog\\Services\\SweepAction $a): void {}')]))->toBe([]);
});

it('no longer reports an actor or a Gate call in a system Action', function (string $body) {
    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), systemAction($body)))->toBe([]);
})->with([
    'actor' => ['public function __invoke(SweepInput $input, Authenticatable $actor): void {}'],
    'Gate' => ["public function __invoke(SweepInput \$input): void { Gate::allows('sweep'); }"],
]);

it('passes a normal Action with an actor and a Gate call', function () {
    $normal = ['Actions/CreatePostAction.php' => phpFile("final class CreatePostAction\n{\n    public function __invoke(CreatePostInput \$input, Authenticatable \$actor): void\n    {\n        Gate::forUser(\$actor)->authorize('create-post', \$input);\n    }\n}", uses: SYSTEM_USES)];

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), [...$normal, ...referencer('Controllers', 'public function run(\\Studeo\\Blog\\Actions\\CreatePostAction $a): void {}')]))->toBe([]);
});

it('passes a clean system Action', function () {
    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), systemAction()))->toBe([]);
});

it('takes forbiddenIn from options', function () {
    $files = [...systemAction(), ...referencer('Services')];

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE), $files))->toBe([])
        ->and(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE, forbiddenIn: ['Services']), $files))->toHaveCount(1)
        ->and(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE, forbiddenIn: ['Services']), [...systemAction(), ...referencer('Controllers')]))->toBe([]);
});

it('looks at the whole module by default and takes a scope', function () {
    $files = [...systemAction(), ...referencer('Controllers')];

    expect(runRule(SystemActionUsage::options(systemAttribute: SYSTEM_ATTRIBUTE, scope: ['Actions']), $files))->toBe([]);
});

it('rejects bad forbiddenIn and scope values naming the rule and the option', function (array $arguments, string $option) {
    expect(fn () => SystemActionUsage::options(...['systemAttribute' => SYSTEM_ATTRIBUTE, ...$arguments]))->toThrow(InvalidRuleConfigurationException::class, "[system-action-usage] option [{$option}]");
})->with([
    'empty forbiddenIn' => [['forbiddenIn' => []], 'forbiddenIn'],
    'path in forbiddenIn' => [['forbiddenIn' => ['Http/Controllers']], 'forbiddenIn'],
    'empty scope' => [['scope' => []], 'scope'],
]);
