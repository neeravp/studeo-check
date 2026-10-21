<?php

namespace Studeo\Check\Rules;

use Studeo\Check\Engine\Routine;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\Helpers\ConditionalCounter;

/**
 * At most `max` if/elseif per method, closures included.
 */
final class MethodComplexity extends FileRule
{
    public const NAME = 'method-complexity';

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
    public static function options(int $max = 2, array $scope = Scope::APP_CODE): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope), RuleOptions::positive(self::NAME, 'max', $max));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $found = array_map(fn (Routine $routine): ?Violation => $this->violationOf($file, $routine), $file->routines());

        return array_values(array_filter($found));
    }

    private function violationOf(SourceFile $file, Routine $routine): ?Violation
    {
        $count = ConditionalCounter::in($routine->node->getStmts() ?? []);

        if ($count <= $this->max) {
            return null;
        }

        return $this->violation($file, $routine->line(), $routine->subject, $this->message($count));
    }

    private function message(int $count): string
    {
        return "{$count} if/elseif statements (max {$this->max}). Use an early return, match(), or extract a named helper.";
    }
}
