<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\ActionAuthorization;
use Studeo\Check\Rules\Laravel\SystemActionUsage;

it('prints #[Background] in both ActionAuthorization messages and matches by full name for a custom systemAttribute', function () {
    $rule = ActionAuthorization::options(systemAttribute: 'Acme\\Attr\\Background');

    // Message 1: normalProblem mentions #[Background]
    $normalAction = ['Actions/CreatePostAction.php' => phpFile('final class CreatePostAction { public function __invoke(): void {} }')];
    $normalViolations = runRule($rule, $normalAction);

    expect($normalViolations)->toHaveCount(1)
        ->and($normalViolations[0]->message)->toContain('#[Background]');

    // Message 2: systemProblem mentions #[Background] when full name matches
    $systemUses = "use Acme\\Attr\\Background;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\nuse Illuminate\\Support\\Facades\\Gate;\n";
    $systemAction = ['Actions/CreatePostAction.php' => phpFile("#[Background]\nfinal class CreatePostAction { public function __invoke(Authenticatable \$actor): void { Gate::forUser(\$actor)->authorize('x'); } }", uses: $systemUses)];
    $systemViolations = runRule($rule, $systemAction);

    expect($systemViolations)->toHaveCount(1)
        ->and($systemViolations[0]->message)->toContain('#[Background]')
        ->and($systemViolations[0]->message)->toContain('remove #[Background] to make it an authorizing Action');

    // Matches by full name: different attribute with same short name is not treated as system Action
    $otherUses = "use Other\\Attr\\Background;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\nuse Illuminate\\Support\\Facades\\Gate;\n";
    $otherAction = ['Actions/CreatePostAction.php' => phpFile("#[Background]\nfinal class CreatePostAction { public function __invoke(Authenticatable \$actor): void { Gate::forUser(\$actor)->authorize('x'); } }", uses: $otherUses)];
    $otherViolations = runRule($rule, $otherAction);

    expect($otherViolations)->toBe([]);
});

it('uses a custom attributionHint in the ActionAuthorization system message', function () {
    $rule = ActionAuthorization::options(
        systemAttribute: 'Acme\\Attr\\Background',
        attributionHint: 'pass credentials in Header',
    );
    $uses = "use Acme\\Attr\\Background;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\n";
    $action = ['Actions/CreatePostAction.php' => phpFile("#[Background]\nfinal class CreatePostAction { public function __invoke(Authenticatable \$actor): void {} }", uses: $uses)];

    $found = runRule($rule, $action);

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toContain('Remove the authorization and pass credentials in Header, or remove #[Background]');
});

it('uses the default hint text in the ActionAuthorization system message', function () {
    $rule = ActionAuthorization::options(systemAttribute: 'Acme\\Attr\\Background');
    $uses = "use Acme\\Attr\\Background;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\n";
    $action = ['Actions/CreatePostAction.php' => phpFile("#[Background]\nfinal class CreatePostAction { public function __invoke(Authenticatable \$actor): void {} }", uses: $uses)];

    $found = runRule($rule, $action);

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toContain('Remove the authorization and carry attribution in an Input field, or remove #[Background]');
});

it('rejects an invalid systemAttribute naming the rule and option for ActionAuthorization and SystemActionUsage', function (string $invalid) {
    expect(fn () => ActionAuthorization::options(systemAttribute: $invalid))
        ->toThrow(InvalidRuleConfigurationException::class, '[action-authorization] option [systemAttribute]')
        ->and(fn () => SystemActionUsage::options(systemAttribute: $invalid))
        ->toThrow(InvalidRuleConfigurationException::class, '[system-action-usage] option [systemAttribute]');
})->with([
    'empty' => [''],
    'leading backslash' => ['\\Acme\\Attr\\Background'],
    'trailing backslash' => ['Acme\\Attr\\Background\\'],
    'double backslash' => ['Acme\\\\Background'],
    'digit first' => ['Acme\\1Background'],
]);
