<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;

it('formats an option exception message', function () {
    $e = InvalidRuleConfigurationException::option('method-complexity', 'max', 'must be a positive integer.');

    expect($e)->toBeInstanceOf(InvalidRuleConfigurationException::class)
        ->and($e)->toBeInstanceOf(RuntimeException::class)
        ->and($e->getMessage())->toBe('Rule [method-complexity] option [max] must be a positive integer.');
});

it('formats a duplicate rule class exception message', function () {
    $e = InvalidRuleConfigurationException::duplicate('App\\Rules\\MyRule');

    expect($e)->toBeInstanceOf(InvalidRuleConfigurationException::class)
        ->and($e->getMessage())->toBe('Rule class App\\Rules\\MyRule is listed twice in the rule set. List each rule class once and configure it through its options().');
});

it('formats a not-a-rule exception message with debug type', function (mixed $given, string $type) {
    $e = InvalidRuleConfigurationException::notARule($given);

    expect($e)->toBeInstanceOf(InvalidRuleConfigurationException::class)
        ->and($e->getMessage())->toBe("A rule set holds rules built with a rule class's options(); got {$type}.");
})->with([
    'string' => ['hello', 'string'],
    'object' => [new stdClass, 'stdClass'],
    'int' => [42, 'int'],
]);
