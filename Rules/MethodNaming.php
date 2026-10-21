<?php

namespace Studeo\Check\Rules;

use Studeo\Check\Engine\Routine;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;

/**
 * Bans method names that say nothing about intent.
 */
final class MethodNaming extends FileRule
{
    public const NAME = 'method-naming';

    /**
     * @param  list<string>  $scope
     * @param  list<string>  $banned
     */
    private function __construct(array $scope, private readonly array $banned)
    {
        parent::__construct($scope);
    }

    /**
     * @param  array<int, string>  $banned  method names to refuse
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(array $banned = ['process', 'processData', 'handleData', 'doStuff', 'doWork'], array $scope = Scope::ALL): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope), RuleOptions::names(self::NAME, 'banned', $banned));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $vague = array_filter($file->routines(), fn (Routine $routine): bool => in_array($routine->name, $this->banned, true));

        return array_values(array_map(fn (Routine $routine) => $this->violation($file, $routine->line(), $routine->subject, $this->message($routine->name)), $vague));
    }

    private function message(string $name): string
    {
        return "method name '{$name}' says nothing about intent. Use context-rich phrasing such as sanitizeNameParameter() or verifyActiveSubscriptionStatus().";
    }
}
