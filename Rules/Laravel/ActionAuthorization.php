<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node\Stmt\Class_;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Rules\FileRule;

/**
 * An Action authorizes: a normal Action takes an Authenticatable actor and calls
 * Gate::forUser(...)->authorize(...) somewhere in the class; a system Action does neither.
 */
final class ActionAuthorization extends FileRule
{
    public const NAME = 'action-authorization';

    private readonly string $shortAttribute;

    /**
     * @param  list<string>  $scope
     */
    protected function __construct(
        array $scope,
        private readonly string $systemAttribute,
        private readonly string $attributionHint,
    ) {
        parent::__construct($scope);
        $parts = explode('\\', $this->systemAttribute);
        $this->shortAttribute = end($parts);
    }

    /**
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(
        string $systemAttribute,
        array $scope = ['Actions'],
        string $attributionHint = 'carry attribution in an Input field',
    ): self {
        return new self(
            RuleOptions::scope(self::NAME, $scope),
            RuleOptions::className(self::NAME, 'systemAttribute', $systemAttribute),
            RuleOptions::text(self::NAME, 'attributionHint', $attributionHint),
        );
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $found = array_map(fn (Class_ $class): array => [$class, $this->problemOf($class)], $file->concreteClasses());
        $broken = array_filter($found, fn (array $pair): bool => $pair[1] !== null);

        return array_values(array_map(fn (array $pair) => $this->violation($file, $pair[0]->getStartLine(), (string) $pair[0]->namespacedName, $pair[1]), $broken));
    }

    private function problemOf(Class_ $class): ?string
    {
        return SystemAttribute::on($class, $this->systemAttribute) ? $this->systemProblem($class) : $this->normalProblem($class);
    }

    private function normalProblem(Class_ $class): ?string
    {
        $missing = array_values(array_filter([
            AuthorizationCalls::takesAuthenticatable($class) ? null : 'an Authenticatable $actor parameter on __invoke()',
            AuthorizationCalls::callsGateForUserAuthorize($class) ? null : 'a Gate::forUser($actor)->authorize(...) call',
        ]));

        return $missing === [] ? null : 'an Action must authorize but is missing '.implode(' and ', $missing).'. Add them, or mark the Action #['.$this->shortAttribute.'] if it must run without an actor.';
    }

    private function systemProblem(Class_ $class): ?string
    {
        $present = array_values(array_filter([
            AuthorizationCalls::takesAuthenticatable($class) ? 'takes an Authenticatable parameter' : null,
            AuthorizationCalls::callsGate($class) ? 'calls Gate::' : null,
            AuthorizationCalls::callsAuthorize($class) ? 'calls ->authorize()' : null,
        ]));

        return $present === [] ? null : 'a system Action does no authorization of its own but '.implode(' and ', $present).'. Remove the authorization and '.$this->attributionHint.', or remove #['.$this->shortAttribute.'] to make it an authorizing Action.';
    }
}
