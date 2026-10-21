<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\FileLength;
use Studeo\Check\Rules\FileRule;
use Studeo\Check\Rules\MethodComplexity;
use Studeo\Check\Rules\MethodNaming;

it('names the first class by first appearance when two classes are duplicated', function () {
    $rules = [MethodNaming::options(), FileLength::options(), FileLength::options(target: 100), MethodNaming::options(scope: ['Actions'])];

    expect(fn () => RuleSet::of(...$rules))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule class '.MethodNaming::class.' is listed twice in the rule set.');
});

it('names a class listed three times', function () {
    expect(fn () => RuleSet::of(FileLength::options(), FileLength::options(), FileLength::options()))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule class '.FileLength::class.' is listed twice in the rule set.');
});

it('names a class that is not the first one listed', function () {
    expect(fn () => RuleSet::of(FileLength::options(), MethodNaming::options(), MethodNaming::options()))
        ->toThrow(InvalidRuleConfigurationException::class, MethodNaming::class);
});

it('refuses the same instance listed twice', function () {
    $rule = FileLength::options();

    expect(fn () => RuleSet::of($rule, $rule))->toThrow(InvalidRuleConfigurationException::class, FileLength::class);
});

it('reports a non-rule before a duplicate wherever each sits', function () {
    $message = 'A rule set holds rules built with a rule class\'s options(); got stdClass.';

    expect(fn () => RuleSet::of(FileLength::options(), FileLength::options(), new stdClass))
        ->toThrow(InvalidRuleConfigurationException::class, $message)
        ->and(fn () => RuleSet::of(new stdClass, FileLength::options(), FileLength::options()))
        ->toThrow(InvalidRuleConfigurationException::class, $message);
});

it('describes each kind of non-rule by its type', function (mixed $entry, string $type) {
    expect(fn () => RuleSet::of(FileLength::options(), $entry))
        ->toThrow(InvalidRuleConfigurationException::class, "got {$type}.");
})->with([
    'integer' => [1, 'int'],
    'string' => ['x', 'string'],
    'null' => [null, 'null'],
    'array' => [[], 'array'],
]);

it('reports the first non-rule when there are several', function () {
    expect(fn () => RuleSet::of(1, 'x'))->toThrow(InvalidRuleConfigurationException::class, 'got int.');
});

it('holds the rules as a list when they come in as a named-argument spread', function () {
    $set = RuleSet::of(...['first' => FileLength::options(), 'second' => MethodComplexity::options()]);

    expect(array_keys($set->rules()))->toBe([0, 1])
        ->and(array_map(fn (Rule $rule): string => $rule->name(), $set->rules()))->toBe(['file-length', 'method-complexity']);
});

it('sees a duplicate behind named arguments', function () {
    expect(fn () => RuleSet::of(...['a' => FileLength::options(), 'b' => FileLength::options()]))
        ->toThrow(InvalidRuleConfigurationException::class, FileLength::class);
});

it('accepts an empty set', function () {
    expect(RuleSet::of()->rules())->toBe([]);
});

it('pins current behaviour: two subclasses of one rule are not duplicates', function () {
    $first = new class(['.']) extends FileRule
    {
        public function __construct(array $scope)
        {
            parent::__construct($scope);
        }

        public function name(): string
        {
            return 'first';
        }

        protected function inspect(SourceFile $file, SourceSet $sources): array
        {
            return [];
        }
    };
    $second = new class(['.']) extends FileRule
    {
        public function __construct(array $scope)
        {
            parent::__construct($scope);
        }

        public function name(): string
        {
            return 'second';
        }

        protected function inspect(SourceFile $file, SourceSet $sources): array
        {
            return [];
        }
    };

    expect($first::class)->not->toBe($second::class)
        ->and(RuleSet::of($first, $second)->rules())->toHaveCount(2);
});
