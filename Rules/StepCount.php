<?php

namespace Studeo\Check\Rules;

use PhpParser\Node\Stmt\Nop;
use Studeo\Check\Engine\Routine;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;

/**
 * At most `max` statements directly in a method or closure body. A nested block counts as one
 * statement; a closure body is its own subject; an arrow function is never counted.
 */
final class StepCount extends FileRule
{
    public const NAME = 'step-count';

    public const DEFAULT_MAX = 8;

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
    public static function options(int $max = self::DEFAULT_MAX, array $scope = Scope::APP_CODE): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope), RuleOptions::positive(self::NAME, 'max', $max));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $long = array_filter($file->subjects(), fn (Routine $subject): bool => $this->stepsIn($subject) > $this->max);

        return array_values(array_map(fn (Routine $subject) => $this->violation($file, $subject->line(), $subject->subject, $this->message($this->stepsIn($subject))), $long));
    }

    private function stepsIn(Routine $subject): int
    {
        return count(array_filter($subject->node->getStmts() ?? [], fn ($statement): bool => ! $statement instanceof Nop));
    }

    private function message(int $steps): string
    {
        return "{$steps} statements (max {$this->max}). Group steps into named private helpers so the method reads as a short pipeline.";
    }
}
