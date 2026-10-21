<?php

namespace Studeo\Check\Rules\Laravel;

use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;

/**
 * A system Action (a class in Actions/ marked with the system attribute) is never reachable from a
 * request: Controllers, Middleware, ApiResources and Livewire do not reference it. What it may
 * not do itself is ActionAuthorization's concern.
 */
final class SystemActionUsage implements Rule
{
    public const NAME = 'system-action-usage';

    /**
     * @param  list<string>  $scope
     * @param  list<string>  $forbiddenIn
     */
    private function __construct(
        private readonly array $scope,
        private readonly array $forbiddenIn,
        private readonly string $systemAttribute,
    ) {}

    /**
     * @param  array<int, string>  $forbiddenIn  directories that must not reference a system Action
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(
        string $systemAttribute,
        array $forbiddenIn = ['Controllers', 'Middleware', 'ApiResources', 'Livewire'],
        array $scope = Scope::ALL,
    ): self {
        return new self(
            RuleOptions::scope(self::NAME, $scope),
            RuleOptions::directories(self::NAME, 'forbiddenIn', $forbiddenIn),
            RuleOptions::className(self::NAME, 'systemAttribute', $systemAttribute),
        );
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function violations(SourceSet $sources): array
    {
        $actions = SystemActionClass::in($sources->files(new Scope($this->scope)), $this->systemAttribute);

        return $this->references($actions, $sources);
    }

    /**
     * @param  list<SystemActionClass>  $actions
     * @return list<Violation>
     */
    private function references(array $actions, SourceSet $sources): array
    {
        $forbiddenScope = new Scope($this->forbiddenIn);
        $forbidden = array_filter($sources->files(new Scope($this->scope)), fn (SourceFile $file): bool => $forbiddenScope->matches($file->relativePath));

        return array_merge([], ...array_map(fn (SourceFile $file): array => $this->referencesIn($file, $actions), array_values($forbidden)));
    }

    /**
     * @param  list<SystemActionClass>  $actions
     * @return list<Violation>
     */
    private function referencesIn(SourceFile $file, array $actions): array
    {
        $names = $file->references();
        $named = array_filter($actions, fn (SystemActionClass $action): bool => isset($names[$action->name]));

        return array_values(array_map(fn (SystemActionClass $action): Violation => $this->finding($file, $names[$action->name], $action->name, "references the system Action {$action->name}. System Actions are callable only from Actions, Listeners and Jobs. Call an authorizing Action instead, or move this call into a Job or Listener."), $named));
    }

    private function finding(SourceFile $file, int $line, string $subject, string $message): Violation
    {
        return new Violation(self::NAME, $file->displayPath, $line, $subject, Severity::Error, $message);
    }
}
