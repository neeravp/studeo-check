<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node\Stmt\Class_;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\SourceFile;

/**
 * A class in Actions/ that carries a system-action attribute, found by parsing and never by
 * loading it.
 */
final readonly class SystemActionClass
{
    public function __construct(
        public string $name,
        public SourceFile $file,
        public Class_ $class,
    ) {}

    /**
     * @param  list<SourceFile>  $files
     * @return list<self>
     */
    public static function in(array $files, string $attribute): array
    {
        $actions = array_filter($files, fn (SourceFile $file): bool => (new Scope(['Actions']))->matches($file->relativePath));

        return array_merge([], ...array_map(fn (SourceFile $file): array => self::declaredIn($file, $attribute), array_values($actions)));
    }

    /**
     * @return list<self>
     */
    private static function declaredIn(SourceFile $file, string $attribute): array
    {
        $marked = array_filter($file->concreteClasses(), fn (Class_ $class): bool => SystemAttribute::on($class, $attribute));

        return array_values(array_map(fn (Class_ $class): self => new self((string) $class->namespacedName, $file, $class), $marked));
    }
}
