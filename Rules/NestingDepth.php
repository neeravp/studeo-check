<?php

namespace Studeo\Check\Rules;

use Studeo\Check\Engine\Routine;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Rules\Helpers\BlockDepth;

/**
 * Control blocks (if, loops, try, switch) nest at most `max` deep in a method or closure body.
 * Branches (else, elseif, catch, finally) are not levels; match is an expression; a closure or
 * arrow function starts again at depth 0.
 */
final class NestingDepth extends FileRule
{
    public const NAME = 'nesting-depth';

    /**
     * @param  list<string>  $scope
     */
    private function __construct(array $scope, private readonly int $max)
    {
        parent::__construct($scope);
    }

    /**
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(int $max = 1, array $scope = Scope::APP_CODE): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope), RuleOptions::positive(self::NAME, 'max', $max));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $deep = array_filter($file->subjects(), fn (Routine $subject): bool => $this->depthOf($subject) > $this->max);

        return array_values(array_map(fn (Routine $subject) => $this->violation($file, $subject->line(), $subject->subject, $this->message($this->depthOf($subject))), $deep));
    }

    private function depthOf(Routine $subject): int
    {
        return BlockDepth::in($subject->node->getStmts() ?? []);
    }

    private function message(int $depth): string
    {
        return "control blocks nest to depth {$depth} (max {$this->max}). Move the inner block into a named helper.";
    }
}
