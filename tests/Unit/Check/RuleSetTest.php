<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\Violation;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\FileLength;
use Studeo\Check\Rules\MethodNaming;

it('holds the rules it is given, in order', function () {
    $set = RuleSet::of(FileLength::options(), MethodNaming::options());

    expect(array_map(fn (Rule $rule): string => $rule->name(), $set->rules()))->toBe(['file-length', 'method-naming']);
});

it('refuses a rule class listed twice and names it', function () {
    expect(fn () => RuleSet::of(FileLength::options(), FileLength::options(target: 100)))
        ->toThrow(InvalidRuleConfigurationException::class, FileLength::class);
});

it('refuses an entry that is not a rule and names it', function () {
    expect(fn () => RuleSet::of(FileLength::options(), new stdClass))
        ->toThrow(InvalidRuleConfigurationException::class, 'stdClass');
});

it('preserves rule instances in the rule set', function () {
    $rule1 = FileLength::options();
    $rule2 = MethodNaming::options();
    $set = RuleSet::of($rule1, $rule2);

    expect($set->rules())->toBe([$rule1, $rule2]);
});

it('is constructed by an object that returns its own rule set', function () {
    $app = new class
    {
        public function rules(): RuleSet
        {
            return RuleSet::of(FileLength::options(target: 100, hard: 200));
        }
    };

    expect($app->rules()->rules())->toHaveCount(1);
});

it('serialises a violation to the documented keys', function () {
    $violation = new Violation('file-length', 'modules/Blog/A.php', 1, 'file', Severity::Warning, 'msg');

    expect($violation->toArray())->toBe(['rule' => 'file-length', 'file' => 'modules/Blog/A.php', 'line' => 1, 'subject' => 'file', 'severity' => 'warning', 'message' => 'msg']);
});
