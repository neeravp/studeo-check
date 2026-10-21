<?php

namespace Studeo\Check\Engine;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;

/**
 * One PHP file of a module, parsed and never loaded: names are resolved to full class names, so
 * a short name used in code reads as the class it stands for.
 */
final class SourceFile
{
    /** @var list<Node\Stmt>|null */
    private ?array $ast = null;

    private ?Error $syntaxError = null;

    private ?string $contents = null;

    public function __construct(
        public readonly string $relativePath,
        public readonly string $displayPath,
        private readonly string $absolutePath,
        private readonly Parser $parser,
    ) {}

    public function lineCount(): int
    {
        $contents = $this->contents();

        return $contents === '' ? 0 : substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);
    }

    public function syntaxError(): ?Error
    {
        $this->ast();

        return $this->syntaxError;
    }

    /**
     * @return list<Node\Stmt> empty when the file does not parse
     */
    public function ast(): array
    {
        return $this->ast ??= $this->parse();
    }

    /**
     * Every method and top-level function in the order they appear. The methods of an anonymous
     * class are their own subjects.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        $nodes = (new NodeFinder)->find($this->ast(), $this->isRoutine(...));

        return array_values(array_map($this->routineOf(...), $nodes));
    }

    /**
     * The routines plus every closure body, each closure named after the routine it sits in
     * (`Class::method{closure}`, one `{closure}` per level). An arrow function is never a subject.
     *
     * @return list<Routine>
     */
    public function subjects(): array
    {
        $closures = (new NodeFinder)->findInstanceOf($this->ast(), Closure::class);

        return array_values([...$this->routines(), ...array_map($this->closureOf(...), $closures)]);
    }

    /**
     * @return list<Class_> named classes that are not abstract
     */
    public function concreteClasses(): array
    {
        /** @var list<Class_> $classes */
        $classes = (new NodeFinder)->find($this->ast(), $this->isConcreteClass(...));

        return $classes;
    }

    /**
     * @return list<Class_> every class, abstract and anonymous ones included
     */
    public function classes(): array
    {
        return array_values((new NodeFinder)->findInstanceOf($this->ast(), Class_::class));
    }

    /**
     * Every class name the file mentions, imports included, with the first line it appears on.
     *
     * @return array<string, int>
     */
    public function references(): array
    {
        return $this->nameReferences() + $this->groupUseReferences();
    }

    /**
     * @return array<string, int>
     */
    private function nameReferences(): array
    {
        $found = [];

        foreach ((new NodeFinder)->findInstanceOf($this->ast(), Name::class) as $name) {
            $found[$name->toString()] ??= $name->getStartLine();
        }

        return $found;
    }

    /**
     * @return array<string, int>
     */
    private function groupUseReferences(): array
    {
        $found = [];
        foreach ((new NodeFinder)->findInstanceOf($this->ast(), GroupUse::class) as $group) {
            $found += $this->usesOf($group);
        }

        return $found;
    }

    /**
     * @return array<string, int>
     */
    private function usesOf(GroupUse $group): array
    {
        $found = [];
        foreach ($group->uses as $use) {
            /** @var Name $name */
            $name = Name::concat($group->prefix, $use->name);
            $found[$name->toString()] ??= $use->getStartLine();
        }

        return $found;
    }

    private function routineOf(Node $node): Routine
    {
        /** @var ClassMethod|Function_ $node */
        return new Routine($this->subjectOf($node), $node->name->toString(), $node);
    }

    private function closureOf(Closure $closure): Routine
    {
        return new Routine($this->closureSubject($closure), '{closure}', $closure);
    }

    private function subjectOf(Node $routine): string
    {
        /** @var ClassMethod|Function_ $routine */
        $owner = $routine->getAttribute('parent');

        return $owner instanceof ClassLike ? $this->classLabel($owner).'::'.$routine->name->toString() : $routine->name->toString().'()';
    }

    private function closureSubject(Node $node, string $levels = '{closure}'): string
    {
        $parent = $node->getAttribute('parent');

        return match (true) {
            $parent === null => $levels,
            $this->isRoutine($parent) => $this->subjectOf($parent).$levels,
            default => $this->closureSubject($parent, $levels.($parent instanceof Closure ? '{closure}' : '')),
        };
    }

    private function isRoutine(Node $node): bool
    {
        return $node instanceof ClassMethod || $node instanceof Function_;
    }

    private function isConcreteClass(Node $node): bool
    {
        return $node instanceof Class_ && $node->name !== null && ! $node->isAbstract();
    }

    private function classLabel(ClassLike $classLike): string
    {
        return $classLike->name?->toString() ?? 'class@anonymous';
    }

    /**
     * @return list<Node\Stmt>
     */
    private function parse(): array
    {
        try {
            $statements = $this->parser->parse($this->contents()) ?? [];
        } catch (Error $error) {
            $this->syntaxError = $error;

            return [];
        }

        $traverser = new NodeTraverser(new NameResolver, new ParentConnectingVisitor);
        /** @var list<Node\Stmt> $nodes */
        $nodes = $traverser->traverse($statements);

        return $nodes;
    }

    private function contents(): string
    {
        return $this->contents ??= (string) file_get_contents($this->absolutePath);
    }
}
