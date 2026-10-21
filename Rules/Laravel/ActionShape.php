<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\FileRule;

/**
 * Actions are final classes named ...Action whose only public entry point is __invoke().
 */
final class ActionShape extends FileRule
{
    public const NAME = 'action-shape';

    /**
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(array $scope = ['Actions']): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $perClass = array_map(fn (Class_ $class): array => $this->violationsOfClass($file, $class), $file->concreteClasses());

        return array_merge([], ...$perClass);
    }

    /**
     * @return list<Violation>
     */
    private function violationsOfClass(SourceFile $file, Class_ $class): array
    {
        return array_map(fn (string $problem): Violation => $this->violation($file, $class->getStartLine(), (string) $class->name, $problem), $this->problemsOf($class));
    }

    /**
     * @return list<string>
     */
    private function problemsOf(Class_ $class): array
    {
        $problems = [
            $class->isFinal() ? null : 'Actions must be final classes. Declare it `final class`.',
            str_ends_with((string) $class->name, 'Action') ? null : "class name must end with 'Action'. Rename it, for example CreatePostAction.",
            $this->entryPointProblem($class),
            $this->usesAsInvokable($class) ? 'must not use AsInvokable; Actions are plain __invoke() classes. Remove the trait.' : null,
            $this->implementsShouldQueue($class) ? 'Actions must not implement ShouldQueue. Remove it and dispatch the Action from a Job.' : null,
        ];

        return array_values(array_filter($problems));
    }

    private function entryPointProblem(Class_ $class): ?string
    {
        $public = $this->publicMethodNames($class);

        return $public === ['__invoke'] ? null : 'Actions expose exactly one public method, __invoke(); found: '.($public === [] ? 'none' : implode(', ', $public)).'. Make the others private or move them to another Action.';
    }

    /**
     * @return list<string> every public method except the constructor
     */
    private function publicMethodNames(Class_ $class): array
    {
        $public = array_filter($class->getMethods(), fn (ClassMethod $method): bool => $method->isPublic());
        $names = array_map(fn (ClassMethod $method): string => $method->name->toString(), $public);

        return array_values(array_diff($names, ['__construct']));
    }

    private function usesAsInvokable(Class_ $class): bool
    {
        $traits = array_merge(...array_map(fn ($use): array => $use->traits, $class->getTraitUses()));

        return array_filter($traits, fn (Name $trait): bool => $trait->getLast() === 'AsInvokable') !== [];
    }

    private function implementsShouldQueue(Class_ $class): bool
    {
        return array_filter($class->implements, fn (Name $name): bool => $name->getLast() === 'ShouldQueue') !== [];
    }
}
