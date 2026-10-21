<?php

namespace Studeo\Check\Engine;

interface SourceSet
{
    public function basePath(): string;

    /**
     * @return list<string>
     */
    public function roots(): array;

    /**
     * @return list<SourceFile>
     */
    public function files(?Scope $scope = null): array;

    public function displayPath(string $absolute): string;

    /**
     * The named classes of the set's files by full name, built once.
     */
    public function classIndex(): ClassIndex;

    /**
     * The first namespace segment of a class name below the namespace root that owns it,
     * null when no root owns the name.
     */
    public function directoryOf(string $name): ?string;
}
