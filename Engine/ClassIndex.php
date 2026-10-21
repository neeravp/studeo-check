<?php

namespace Studeo\Check\Engine;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * The named classes of a source set's parsed files by full name, so a rule can follow a class up
 * its parents through the source set. A parent outside the source set is a name and nothing more.
 */
final class ClassIndex
{
    /**
     * @param  array<string, Class_>  $classes
     */
    private function __construct(private readonly array $classes) {}

    /**
     * @param  list<SourceFile>  $files
     */
    public static function of(array $files): self
    {
        $perFile = array_map(fn (SourceFile $file): array => (new NodeFinder)->find($file->ast(), self::isNamed(...)), $files);
        /** @var list<Class_> $named */
        $named = array_merge([], ...$perFile);

        return new self(array_combine(array_map(fn (Class_ $class): string => (string) $class->namespacedName, $named), $named));
    }

    public function descendsFrom(string $name, string $base): bool
    {
        return in_array($base, $this->ancestorsOf($name), true);
    }

    /**
     * The nearest constructor of the class or of a parent defined in the source set.
     */
    public function constructorOf(string $name): ?ClassMethod
    {
        $constructors = array_map(fn (string $candidate): ?ClassMethod => ($this->classes[$candidate] ?? null)?->getMethod('__construct'), [$name, ...$this->ancestorsOf($name)]);

        return array_values(array_filter($constructors))[0] ?? null;
    }

    /**
     * @return list<string> parents, nearest first, stopping at a cycle
     */
    private function ancestorsOf(string $name): array
    {
        $ancestors = [];
        $current = $this->parentOf($name);

        while ($this->isUnvisited($current, $ancestors)) {
            $ancestors[] = $current;
            $current = $this->parentOf($current);
        }

        return $ancestors;
    }

    private function parentOf(string $name): ?string
    {
        return ($this->classes[$name] ?? null)?->extends?->toString();
    }

    /**
     * @param  list<string>  $visited
     *
     * @phpstan-assert-if-true string $name
     */
    private function isUnvisited(?string $name, array $visited): bool
    {
        return $name !== null && ! in_array($name, $visited, true);
    }

    /**
     * @phpstan-assert-if-true Class_ $node
     */
    private static function isNamed(mixed $node): bool
    {
        return $node instanceof Class_ && $node->name !== null;
    }
}
