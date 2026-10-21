<?php

namespace Studeo\Check\Rules;

use Studeo\Check\Engine\Routine;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Rules\Helpers\BooleanOperators;

/**
 * `&&`, `||`, `and` and `or` appear only in a method whose whole body is one `return`: a
 * one-line named helper. Closures and arrow functions are not exempt.
 */
final class ConditionExtraction extends FileRule
{
    public const NAME = 'condition-extraction';

    /**
     * @param  list<string>  $scope
     */
    private function __construct(array $scope)
    {
        parent::__construct($scope);
    }

    /**
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(array $scope = Scope::APP_CODE): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $inline = array_filter($file->routines(), fn (Routine $routine): bool => $this->operatorsIn($routine) > 0);

        return array_values(array_map(fn (Routine $routine) => $this->violation($file, $routine->line(), $routine->subject, $this->message($this->operatorsIn($routine))), $inline));
    }

    private function operatorsIn(Routine $routine): int
    {
        $statements = $routine->node->getStmts() ?? [];

        return BooleanOperators::isSingleReturn($statements) ? 0 : BooleanOperators::in($statements);
    }

    private function message(int $count): string
    {
        $noun = $count === 1 ? 'operator' : 'operators';

        return "{$count} boolean {$noun} outside a one-line helper. Move the expression into a private method named for what it means, such as hasActiveSubscription(), whose body is return …;";
    }
}
