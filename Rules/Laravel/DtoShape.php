<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use Studeo\Check\Engine\ClassIndex;
use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\FileRule;

/**
 * Data objects are final, every property is readonly, and each is an Input or a Data object (DtoContract).
 */
final class DtoShape extends FileRule
{
    public const NAME = 'dto-shape';

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
        array $scope = ['Data'],
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
        $perClass = array_map(fn (Class_ $class): array => $this->violationsOfClass($file, $class, $sources->classIndex()), $file->concreteClasses());

        return array_merge([], ...$perClass);
    }

    /**
     * @return list<Violation>
     */
    private function violationsOfClass(SourceFile $file, Class_ $class, ClassIndex $index): array
    {
        $problems = $this->problemsOf($class, $index);

        return array_map(fn (string $problem): Violation => $this->violation($file, $class->getStartLine(), (string) $class->name, $problem), $problems);
    }

    /**
     * @return list<string>
     */
    private function problemsOf(Class_ $class, ClassIndex $index): array
    {
        $mutable = $this->mutablePropertyNames($class);

        return [
            ...array_values(array_filter([
                $class->isFinal() ? null : 'DTO classes must be final. Declare it `final readonly class`.',
                $mutable === [] ? null : 'DTO properties must be readonly; mutable: $'.implode(', $', $mutable).'. Declare the class readonly.',
            ])),
            ...DtoContract::problems($class, $index, $this->inputBase, $this->outputBase),
        ];
    }

    /**
     * @return list<string>
     */
    private function mutablePropertyNames(Class_ $class): array
    {
        return $class->isReadonly() ? [] : [...$this->mutableDeclared($class), ...$this->mutablePromoted($class)];
    }

    /**
     * @return list<string>
     */
    private function mutableDeclared(Class_ $class): array
    {
        $mutable = array_filter($class->getProperties(), $this->isMutableDeclared(...));
        $names = array_map(fn (Property $property): array => array_map(fn ($item): string => (string) $item->name, $property->props), $mutable);
        /** @var list<string> $flattened */
        $flattened = array_merge([], ...$names);

        return $flattened;
    }

    private function isMutableDeclared(Property $property): bool
    {
        return ! $property->isStatic() && ! $property->isReadonly();
    }

    /**
     * @return list<string>
     */
    private function mutablePromoted(Class_ $class): array
    {
        $params = $class->getMethod('__construct')->params ?? [];
        $mutable = array_filter($params, $this->isMutablePromoted(...));

        return array_values(array_map(function (Param $param): string {
            /** @var Variable $var */
            $var = $param->var;
            /** @var string $name */
            $name = $var->name;

            return $name;
        }, $mutable));
    }

    private function isMutablePromoted(Param $param): bool
    {
        return $param->isPromoted() && ! $param->isReadonly();
    }
}
