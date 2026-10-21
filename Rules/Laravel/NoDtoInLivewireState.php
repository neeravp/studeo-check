<?php

namespace Studeo\Check\Rules\Laravel;

use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\FileRule;
use Studeo\Check\Rules\Helpers\ClassMembers;
use Studeo\Check\Rules\Helpers\TypedMember;

/**
 * A Livewire component or Form never holds a DTO in a public property: Livewire serializes
 * public state to the browser, so it holds plain snake_case values and the Input is built in toInput().
 * Blade files are not parsed, so a single-file component is not checked.
 */
final class NoDtoInLivewireState extends FileRule
{
    public const NAME = 'no-dto-in-livewire-state';

    /**
     * @param  list<string>  $scope
     */
    protected function __construct(
        array $scope,
        private readonly string $inputBase,
        private readonly string $outputBase,
    ) {
        parent::__construct($scope);
    }

    /**
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(
        string $inputBase,
        string $outputBase,
        array $scope = ['Livewire'],
    ): self {
        return new self(
            RuleOptions::scope(self::NAME, $scope),
            RuleOptions::className(self::NAME, 'inputBase', $inputBase),
            RuleOptions::className(self::NAME, 'outputBase', $outputBase),
        );
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $members = array_merge([], ...array_map(ClassMembers::publicState(...), $file->classes()));
        $held = array_filter($members, fn (TypedMember $member): bool => $this->holdsDto($member, $sources));

        return array_values(array_map(fn (TypedMember $member): Violation => $this->violation($file, $member->line, $member->subject, $this->message($member, $sources)), $held));
    }

    private function message(TypedMember $member, SourceSet $sources): string
    {
        $dtos = implode(', ', array_filter($member->types, fn (string $name): bool => $this->isDto($name, $sources)));

        return "public property \${$member->variable} is typed {$dtos}, a DTO. Hold plain snake_case state (strings, numbers, arrays) and build the Input in toInput().";
    }

    private function holdsDto(TypedMember $member, SourceSet $sources): bool
    {
        return array_filter($member->types, fn (string $name): bool => $this->isDto($name, $sources)) !== [];
    }

    private function isDto(string $name, SourceSet $sources): bool
    {
        return $sources->classIndex()->descendsFrom($name, $this->inputBase)
            || $sources->classIndex()->descendsFrom($name, $this->outputBase)
            || $this->isInDataNamespace($name, $sources);
    }

    private function isInDataNamespace(string $name, SourceSet $sources): bool
    {
        return $sources->directoryOf($name) === 'Data';
    }
}
