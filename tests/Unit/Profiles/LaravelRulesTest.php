<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Profiles\LaravelRules;

it('holds the three Laravel convention rules in the contract order', function () {
    $set = LaravelRules::make(DTO_INPUT_BASE, DTO_OUTPUT_BASE)->rules();

    $expected = [
        'dto-shape',
        'migration',
        'no-dto-in-livewire-state',
    ];

    expect(array_map(fn (Rule $rule): string => $rule->name(), $set->rules()))
        ->toBe($expected);
});

it('passes inputBase to dto-shape', function () {
    $rule = LaravelRules::make(DTO_INPUT_BASE, DTO_OUTPUT_BASE)->rules()->rules()[0];

    $files = [
        'Data/CreatePostInput.php' => phpFile('final readonly class CreatePostInput { protected function __construct() {} }', 'Acme\\Blog\\Data'),
    ];

    $found = runRule($rule, $files);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('dto-shape')
        ->and($found[0]->message)->toContain(DTO_INPUT_BASE);
});

it('passes outputBase to dto-shape', function () {
    $rule = LaravelRules::make(DTO_INPUT_BASE, DTO_OUTPUT_BASE)->rules()->rules()[0];

    $files = [
        'Data/PostData.php' => phpFile('final readonly class PostData {}', 'Acme\\Blog\\Data'),
    ];

    $found = runRule($rule, $files);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('dto-shape')
        ->and($found[0]->message)->toContain(DTO_OUTPUT_BASE);
});

it('passes inputBase to no-dto-in-livewire-state', function () {
    $rule = LaravelRules::make(DTO_INPUT_BASE, DTO_OUTPUT_BASE)->rules()->rules()[2];

    $files = [
        'Support/WeirdInput.php' => phpFile('final readonly class WeirdInput extends InputData {}', 'Acme\\Blog\\Support', "use Acme\\Dto\\InputData;\n"),
        'Livewire/PostEditor.php' => phpFile("class PostEditor extends Component\n{\n    public WeirdInput \$input;\n}", 'Acme\\Blog\\Livewire', "use Livewire\\Component;\nuse Acme\\Blog\\Support\\WeirdInput;\n"),
    ];

    $found = runRule($rule, $files, ['Acme\\Blog\\']);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('no-dto-in-livewire-state')
        ->and($found[0]->subject)->toBe('PostEditor::$input');
});

it('passes outputBase to no-dto-in-livewire-state', function () {
    $rule = LaravelRules::make(DTO_INPUT_BASE, DTO_OUTPUT_BASE)->rules()->rules()[2];

    $files = [
        'Support/WeirdOutput.php' => phpFile('final readonly class WeirdOutput extends OutputData {}', 'Acme\\Blog\\Support', "use Acme\\Dto\\OutputData;\n"),
        'Livewire/PostEditor.php' => phpFile("class PostEditor extends Component\n{\n    public WeirdOutput \$output;\n}", 'Acme\\Blog\\Livewire', "use Livewire\\Component;\nuse Acme\\Blog\\Support\\WeirdOutput;\n"),
    ];

    $found = runRule($rule, $files, ['Acme\\Blog\\']);

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('no-dto-in-livewire-state')
        ->and($found[0]->subject)->toBe('PostEditor::$output');
});

it('rejects an invalid inputBase or outputBase naming the rule and option', function (string $invalid) {
    expect(fn () => LaravelRules::make($invalid, DTO_OUTPUT_BASE)->rules())
        ->toThrow(InvalidRuleConfigurationException::class)
        ->and(fn () => LaravelRules::make(DTO_INPUT_BASE, $invalid)->rules())
        ->toThrow(InvalidRuleConfigurationException::class);
})->with([
    'empty' => [''],
    'leading backslash' => ['\\Acme\\Dto\\InputData'],
    'trailing backslash' => ['Acme\\Dto\\InputData\\'],
    'double backslash' => ['Acme\\\\InputData'],
    'digit first' => ['Acme\\1InputData'],
]);
