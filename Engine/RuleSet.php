<?php

namespace Studeo\Check\Engine;

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;

final readonly class RuleSet
{
    /**
     * @param  list<Rule>  $rules
     */
    private function __construct(private array $rules) {}

    /**
     * @param  Rule  ...$rules  each built with a rule class's options(); a class may appear once
     */
    public static function of(mixed ...$rules): self
    {
        foreach ($rules as $rule) {
            self::assertRule($rule);
        }

        self::assertEachClassOnce($rules);

        return new self(array_values($rules));
    }

    /**
     * @return list<Rule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Return a new rule set holding this set's rules followed by the rules of each other set.
     */
    public function merge(self ...$others): self
    {
        $all = $this->rules;

        foreach ($others as $other) {
            $all = [...$all, ...$other->rules];
        }

        return self::of(...$all);
    }

    private static function assertRule(mixed $rule): void
    {
        if (! $rule instanceof Rule) {
            throw InvalidRuleConfigurationException::notARule($rule);
        }
    }

    /**
     * @param  array<int|string, Rule>  $rules
     */
    private static function assertEachClassOnce(array $rules): void
    {
        $first = array_key_first(self::repeatedCounts($rules));

        if ($first !== null) {
            throw InvalidRuleConfigurationException::duplicate($first);
        }
    }

    /**
     * @param  array<int|string, Rule>  $rules
     * @return array<string, int> the classes listed more than once with their counts, in order of first appearance
     */
    private static function repeatedCounts(array $rules): array
    {
        $counts = array_count_values(array_map(fn (Rule $rule): string => $rule::class, $rules));

        return array_filter($counts, fn (int $count): bool => $count > 1);
    }
}
