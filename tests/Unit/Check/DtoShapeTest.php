<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\DtoShape;
use Studeo\Check\Tests\Support\DtoShapeFiles;

it('passes final classes whose properties are all readonly', function (string $declaration) {
    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file($declaration)))->toBe([]);
})->with([
    'readonly class' => ['final readonly class PostData extends OutputData { public function __construct(public string $title) {} }'],
    'promoted readonly' => ['final class PostData extends OutputData { public function __construct(public readonly string $title) {} }'],
    'declared readonly' => ['final class PostData extends OutputData { public readonly string $title; public static int $count = 0; }'],
    'abstract base' => ['abstract class PostData { public string $title; }'],
]);

it('flags each broken part of the shape', function (string $declaration, string $expected) {
    $found = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file($declaration));

    expect($found)->toHaveCount(1)
        ->and($found[0]->rule)->toBe('dto-shape')
        ->and($found[0]->subject)->toBe('PostData')
        ->and($found[0]->message)->toContain($expected);
})->with([
    'not final' => ['readonly class PostData extends OutputData { public function __construct(public string $title) {} }', 'final'],
    'mutable promoted' => ['final class PostData extends OutputData { public function __construct(public string $title) {} }', '$title'],
    'mutable declared' => ['final class PostData extends OutputData { public string $title; }', '$title'],
]);

it('reports a class that is neither final nor readonly once with both problems', function () {
    $found = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file('class PostData extends OutputData { public string $title; }'));

    expect(array_column($found, 'subject'))->toBe(['PostData', 'PostData']);
});

it('only looks at Data by default', function () {
    $bad = phpFile('class PostData { public string $title; }');

    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), ['Models/PostData.php' => $bad]))->toBe([])
        ->and(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), ['Data/Posts/PostData.php' => $bad]))->not->toBe([]);
});

it('takes its scope from options', function () {
    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE, scope: ['Models']), ['Models/PostData.php' => phpFile('class PostData {}')]))->not->toBe([]);
});

it('rejects an empty scope naming the rule and the option', function () {
    expect(fn () => DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE, scope: []))->toThrow(InvalidRuleConfigurationException::class, '[dto-shape] option [scope]');
});

it('rejects a non-class-name inputBase and outputBase naming rule and option', function (string $invalid) {
    expect(fn () => DtoShape::options(inputBase: $invalid, outputBase: DTO_OUTPUT_BASE))
        ->toThrow(InvalidRuleConfigurationException::class, '[dto-shape] option [inputBase]')
        ->and(fn () => DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: $invalid))
        ->toThrow(InvalidRuleConfigurationException::class, '[dto-shape] option [outputBase]');
})->with([
    'empty' => [''],
    'leading backslash' => ['\\Acme\\Dto\\InputData'],
    'trailing backslash' => ['Acme\\Dto\\InputData\\'],
    'double backslash' => ['Acme\\\\InputData'],
    'digit first' => ['Acme\\1InputData'],
]);
