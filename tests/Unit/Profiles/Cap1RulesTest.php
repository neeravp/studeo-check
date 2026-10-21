<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Profiles\Cap1Rules;

it('holds the six CAP 1 rules in the contract order', function () {
    $set = Cap1Rules::make()->rules();

    $expected = [
        'method-complexity',
        'file-length',
        'method-naming',
        'step-count',
        'nesting-depth',
        'condition-extraction',
    ];

    expect(array_map(fn (Rule $rule): string => $rule->name(), $set->rules()))
        ->toBe($expected);
});
