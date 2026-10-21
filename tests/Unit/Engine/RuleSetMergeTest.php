<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\FileLength;
use Studeo\Check\Rules\MethodNaming;
use Studeo\Check\Rules\StepCount;

it('orders rules as this set rules followed by each argument rules in order', function () {
    $set1 = RuleSet::of(FileLength::options());
    $set2 = RuleSet::of(MethodNaming::options());
    $merged = $set1->merge($set2);

    expect(array_map(fn (Rule $rule): string => $rule->name(), $merged->rules()))
        ->toBe(['file-length', 'method-naming']);
});

it('returns a set with the same rules and a different object when merging nothing', function () {
    $set = RuleSet::of(FileLength::options());
    $merged = $set->merge();

    expect($merged->rules())->toBe($set->rules())
        ->and($merged)->not->toBe($set);
});

it('leaves the original sets unchanged after a merge', function () {
    $set1 = RuleSet::of(FileLength::options());
    $set2 = RuleSet::of(MethodNaming::options());
    $set1->merge($set2);

    expect(array_map(fn (Rule $rule): string => $rule->name(), $set1->rules()))
        ->toBe(['file-length'])
        ->and(array_map(fn (Rule $rule): string => $rule->name(), $set2->rules()))
        ->toBe(['method-naming']);
});

it('throws the duplicate exception of of() when a class is present in two sets', function () {
    $set1 = RuleSet::of(FileLength::options());
    $set2 = RuleSet::of(FileLength::options(target: 100));

    expect(fn () => $set1->merge($set2))
        ->toThrow(InvalidRuleConfigurationException::class, FileLength::class);
});

it('keeps the argument order when merging three sets', function () {
    $set1 = RuleSet::of(FileLength::options());
    $set2 = RuleSet::of(MethodNaming::options());
    $set3 = RuleSet::of(StepCount::options());
    $merged = $set1->merge($set2, $set3);

    expect(array_map(fn (Rule $rule): string => $rule->name(), $merged->rules()))
        ->toBe(['file-length', 'method-naming', 'step-count']);
});
