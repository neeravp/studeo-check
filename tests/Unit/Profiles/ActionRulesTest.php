<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Profiles\ActionRules;

it('holds the three action rules in the contract order', function () {
    $set = ActionRules::make(SYSTEM_ATTRIBUTE)->rules();

    $expected = [
        'action-shape',
        'system-action-usage',
        'action-authorization',
    ];

    expect(array_map(fn (Rule $rule): string => $rule->name(), $set->rules()))
        ->toBe($expected);
});

it('passes systemAttribute to system-action-usage', function () {
    $rule = ActionRules::make(SYSTEM_ATTRIBUTE)->rules()->rules()[1];

    $action = [
        'Actions/SweepAction.php' => phpFile(
            "#[SystemAction]\nfinal class SweepAction\n{\n    public function __invoke(): void {}\n}",
            uses: SYSTEM_USES,
        ),
    ];
    $caller = [
        'Controllers/Caller.php' => phpFile(
            "final class Caller\n{\n    public function run(\\Studeo\\Blog\\Actions\\SweepAction \$action): void {}\n}",
            'Studeo\\Blog\\Controllers',
        ),
    ];

    $found = runRule($rule, [...$action, ...$caller]);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('system-action-usage')
        ->and($found[0]->subject)->toBe('Studeo\\Blog\\Actions\\SweepAction');
});

it('passes systemAttribute to action-authorization', function () {
    $rule = ActionRules::make(SYSTEM_ATTRIBUTE)->rules()->rules()[2];

    $action = [
        'Actions/CreatePostAction.php' => phpFile(
            "final class CreatePostAction\n{\n    public function __invoke(): void {}\n}",
        ),
    ];

    $found = runRule($rule, $action);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('action-authorization')
        ->and($found[0]->message)->toContain('#[SystemAction]');
});

it('passes attributionHint to action-authorization', function () {
    $rule = ActionRules::make(SYSTEM_ATTRIBUTE, SYSTEM_ATTRIBUTION_HINT)->rules()->rules()[2];

    $action = [
        'Actions/SweepAction.php' => phpFile(
            "#[SystemAction]\nfinal class SweepAction\n{\n    public function __invoke(Authenticatable \$actor): void {}\n}",
            uses: SYSTEM_USES,
        ),
    ];

    $found = runRule($rule, $action);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('action-authorization')
        ->and($found[0]->message)->toContain(SYSTEM_ATTRIBUTION_HINT);
});

it('uses the rule default attribution hint when attributionHint is omitted', function () {
    $rule = ActionRules::make('Acme\\Other')->rules()->rules()[2];

    $action = [
        'Actions/SweepAction.php' => phpFile(
            "#[Other]\nfinal class SweepAction\n{\n    public function __invoke(Authenticatable \$actor): void {}\n}",
            uses: "use Acme\\Other;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\n",
        ),
    ];

    $found = runRule($rule, $action);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('action-authorization')
        ->and($found[0]->message)->toContain('carry attribution in an Input field');
});

it('rejects an invalid systemAttribute naming the rule and option', function (string $invalid) {
    expect(fn () => ActionRules::make($invalid)->rules())
        ->toThrow(InvalidRuleConfigurationException::class);
})->with([
    'empty' => [''],
    'leading backslash' => ['\\Acme\\Attributes\\SystemAction'],
    'trailing backslash' => ['Acme\\Attributes\\SystemAction\\'],
    'double backslash' => ['Acme\\\\SystemAction'],
    'digit first' => ['Acme\\1SystemAction'],
]);
