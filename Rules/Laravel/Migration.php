<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\FileRule;

/**
 * Migrations are data declarations: no ->enum() columns, no domain classes. An enum column is a
 * method, nullsafe or static call named enum in any letter case; a dynamic name such as
 * ->{'enum'}() is not resolved and stays unflagged.
 */
final class Migration extends FileRule
{
    public const NAME = 'migration';

    /**
     * @param  list<string>  $scope
     * @param  list<string>  $forbidden
     */
    private function __construct(array $scope, private readonly array $forbidden)
    {
        parent::__construct($scope);
    }

    /**
     * @param  array<int, string>  $forbidden  namespace fragments no migration may reference
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(array $forbidden = ['\\Models\\', '\\Actions\\', '\\Data\\', '\\Enums\\'], array $scope = ['database/migrations']): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope), RuleOptions::names(self::NAME, 'forbidden', $forbidden));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        return [...$this->enumColumns($file), ...$this->domainReferences($file)];
    }

    /**
     * @return list<Violation>
     */
    private function enumColumns(SourceFile $file): array
    {
        /** @var list<CallLike> $calls */
        $calls = (new NodeFinder)->find($file->ast(), $this->isEnumCall(...));

        return array_values(array_map(fn (CallLike $call) => $this->violation($file, $call->getStartLine(), 'enum()', "uses ->enum(). Use ->string('column', 30)->index() with a backed enum cast."), $calls));
    }

    /**
     * @return list<Violation>
     */
    private function domainReferences(SourceFile $file): array
    {
        $domain = array_filter($file->references(), fn (int $line, string $name): bool => $this->isDomainClass($name), ARRAY_FILTER_USE_BOTH);

        return array_values(array_map(fn (int $line, string $name) => $this->violation($file, $line, $name, "references domain class {$name}. Migrations must not use models, actions, data objects or enums; write the values out."), $domain, array_keys($domain)));
    }

    /**
     * @phpstan-assert-if-true CallLike $node
     */
    private function isEnumCall(Node $node): bool
    {
        return $this->isNamedCall($node) && strtolower($node->name->toString()) === 'enum';
    }

    /**
     * @phpstan-assert-if-true (MethodCall|NullsafeMethodCall|StaticCall)&object{name: Identifier} $node
     */
    private function isNamedCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier;
    }

    private function isDomainClass(string $name): bool
    {
        return array_filter($this->forbidden, fn (string $fragment): bool => str_contains('\\'.$name, $fragment)) !== [];
    }
}
