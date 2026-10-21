<?php

use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;

it('accepts a positive integer and returns it', function (int $value) {
    expect(RuleOptions::positive('r', 'o', $value))->toBe($value);
})->with([1, 2, PHP_INT_MAX]);

it('rejects a value below one with the positive integer message', function (int $value) {
    expect(fn () => RuleOptions::positive('r', 'o', $value))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must be a positive integer.');
})->with([0, -1, PHP_INT_MIN]);

it('rejects an empty list with the empty message', function () {
    expect(fn () => RuleOptions::names('r', 'o', []))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must not be empty.');
});

it('rejects every invalid kind of entry with one message, wherever it sits', function (array $values) {
    expect(fn () => RuleOptions::names('r', 'o', $values))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must hold non-empty strings only.');
})->with([
    'empty string' => [['']],
    'blank string' => [['   ']],
    'integer after a name' => [['a', 1]],
    'null before a name' => [[null, 'a']],
    'array in the middle' => [['a', ['b'], 'c']],
    'several invalid kinds' => [['a', '', 1, null]],
    'invalid first' => [[1, '']],
    'invalid last' => [['', 1]],
]);

it('returns the entries as a list with their order and trimmed', function (array $values, array $expected) {
    expect(RuleOptions::names('r', 'o', $values))->toBe($expected);
})->with([
    'plain list' => [['a', 'b'], ['a', 'b']],
    'string keys' => [['k' => 'a', 'z' => 'b'], ['a', 'b']],
    'sparse integer keys' => [[2 => 'x', 5 => 'y'], ['x', 'y']],
    'surrounding whitespace is removed' => [[' a ', "b\n"], ['a', 'b']],
    'a zero string is a name' => [['0'], ['0']],
]);

it('trims scope entries of spaces, tabs, newlines and slashes', function () {
    expect(RuleOptions::scope('r', [' a/ ', "\tb\n", '/c/', 'd/e']))->toBe(['a', 'b', 'c', 'd/e']);
});

it('keeps the dot of a whole-module scope and reindexes keyed input', function () {
    expect(RuleOptions::scope('r', ['.']))->toBe(['.'])
        ->and(RuleOptions::scope('r', ['x' => 'a', 'y' => 'b']))->toBe(['a', 'b']);
});

it('rejects a scope that is empty or that trims to nothing, naming the scope option', function (array $scope, string $message) {
    expect(fn () => RuleOptions::scope('r', $scope))
        ->toThrow(InvalidRuleConfigurationException::class, "Rule [r] option [scope] {$message}");
})->with([
    'empty list' => [[], 'must not be empty.'],
    'only a slash' => [['/'], 'must hold non-empty strings only.'],
    'only spaces' => [['  '], 'must hold non-empty strings only.'],
    'integer entry' => [[7], 'must hold non-empty strings only.'],
    'null entry' => [['a', null], 'must hold non-empty strings only.'],
]);

it('returns directory names as a list and trims them', function () {
    expect(RuleOptions::directories('r', 'o', ['k' => 'Actions', 'z' => ' Models ']))->toBe(['Actions', 'Models']);
});

it('rejects a path with the directory names message', function (string $path) {
    expect(fn () => RuleOptions::directories('r', 'o', ['Actions', $path]))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must hold directory names, not paths.');
})->with(['a/b', 'a\\b', '/a', 'a/']);

it('reports an invalid entry before a path and an empty list before both', function () {
    expect(fn () => RuleOptions::directories('r', 'o', ['a/b', '']))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must hold non-empty strings only.')
        ->and(fn () => RuleOptions::directories('r', 'o', []))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must not be empty.');
});

it('accepts a valid class name and returns it trimmed', function (string $value, string $expected) {
    expect(RuleOptions::className('r', 'o', $value))->toBe($expected);
})->with([
    'valid' => ['App\\Models\\User', 'App\\Models\\User'],
    'trimmed' => ['  App\\Models\\User  ', 'App\\Models\\User'],
]);

it('rejects an invalid class name with the class name message', function (string $value) {
    expect(fn () => RuleOptions::className('r', 'o', $value))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must be a fully qualified class name without a leading backslash.');
})->with([
    'leading backslash' => ['\\App\\Models\\User'],
    'trailing backslash' => ['App\\Models\\User\\'],
    'empty' => [''],
    'digit-first segment' => ['App\\1Foo'],
    'double backslash' => ['App\\\\User'],
]);

it('accepts non-empty text and returns it trimmed', function (string $value, string $expected) {
    expect(RuleOptions::text('r', 'o', $value))->toBe($expected);
})->with([
    'valid' => ['carry attribution', 'carry attribution'],
    'trimmed' => ['  carry attribution  ', 'carry attribution'],
]);

it('rejects empty or whitespace-only text with the empty message', function (string $value) {
    expect(fn () => RuleOptions::text('r', 'o', $value))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [r] option [o] must not be empty.');
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
]);
