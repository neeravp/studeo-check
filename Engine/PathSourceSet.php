<?php

namespace Studeo\Check\Engine;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

final class PathSourceSet implements SourceSet
{
    /** @var list<SourceFile>|null */
    private ?array $files = null;

    private ?ClassIndex $classIndex = null;

    private readonly Parser $parser;

    /**
     * @param  list<string>  $roots
     * @param  list<string>  $namespaceRoots  namespace prefixes with a trailing backslash
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $roots,
        private readonly ?string $scopeBase = null,
        private readonly array $namespaceRoots = [],
    ) {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return list<string>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * @return list<SourceFile>
     */
    public function files(?Scope $scope = null): array
    {
        $all = $this->files ??= $this->collect();

        if ($scope === null) {
            return $all;
        }

        return array_values(array_filter($all, fn (SourceFile $file): bool => $scope->matches($file->relativePath)));
    }

    public function displayPath(string $absolute): string
    {
        $base = rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($absolute, $base) ? str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($base))) : $absolute;
    }

    /**
     * The named classes of the set's files by full name, built once.
     */
    public function classIndex(): ClassIndex
    {
        return $this->classIndex ??= ClassIndex::of($this->files());
    }

    /**
     * The first namespace segment of a class name below the namespace root that owns it,
     * null when no root owns the name.
     */
    public function directoryOf(string $name): ?string
    {
        $remainder = $this->remainderOf($name);

        return $remainder === null ? null : explode('\\', $remainder)[0];
    }

    private function remainderOf(string $name): ?string
    {
        $matching = array_filter($this->namespaceRoots, fn (string $prefix): bool => str_starts_with($name, $prefix));
        usort($matching, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $matching === [] ? null : substr($name, strlen($matching[0]));
    }

    /**
     * @return list<SourceFile>
     */
    private function collect(): array
    {
        if ($this->roots === []) {
            return [];
        }

        $directories = array_values(array_filter($this->roots, is_dir(...)));
        $files = array_values(array_filter($this->roots, is_file(...)));

        $all = [...$this->collectDirectories($directories), ...$this->collectFiles($files)];
        usort($all, fn (SourceFile $a, SourceFile $b): int => $a->relativePath <=> $b->relativePath);

        return $all;
    }

    /**
     * @param  list<string>  $directories
     * @return list<SourceFile>
     */
    private function collectDirectories(array $directories): array
    {
        if ($directories === []) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($directories)
            ->name('*.php')
            ->notName('*.blade.php')
            ->exclude(['node_modules', 'vendor'])
            ->sortByName();

        return array_values(array_map($this->sourceFile(...), iterator_to_array($finder, false)));
    }

    /** @param  \Symfony\Component\Finder\SplFileInfo  $entry */
    private function sourceFile(SplFileInfo $entry): SourceFile
    {
        $pathname = $entry->getPathname();
        $relative = $this->resolveEntryRelativePath($entry);

        return new SourceFile($relative, $this->displayPath($pathname), $pathname, $this->parser);
    }

    /** @param  \Symfony\Component\Finder\SplFileInfo  $entry */
    private function resolveEntryRelativePath(SplFileInfo $entry): string
    {
        $scopeRelative = $this->scopeRelativePath($entry->getPathname());
        if ($scopeRelative !== null) {
            return $scopeRelative;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $entry->getRelativePathname());
    }

    /**
     * @param  list<string>  $files
     * @return list<SourceFile>
     */
    private function collectFiles(array $files): array
    {
        $phpFiles = array_filter($files, $this->isCheckableFile(...));

        return array_values(array_map($this->sourceFileFromPath(...), $phpFiles));
    }

    private function isCheckableFile(string $path): bool
    {
        return str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php');
    }

    private function sourceFileFromPath(string $pathname): SourceFile
    {
        $relative = $this->resolvePathRelativePath($pathname);

        return new SourceFile($relative, $this->displayPath($pathname), $pathname, $this->parser);
    }

    private function resolvePathRelativePath(string $pathname): string
    {
        $scopeRelative = $this->scopeRelativePath($pathname);
        if ($scopeRelative !== null) {
            return $scopeRelative;
        }

        return $this->displayPath($pathname);
    }

    private function scopeRelativePath(string $pathname): ?string
    {
        if ($this->scopeBase === null) {
            return null;
        }

        $base = rtrim($this->scopeBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($pathname, $base)) {
            return null;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($pathname, strlen($base)));
    }
}
