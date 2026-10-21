<?php

namespace Studeo\Check\Rules;

use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;

/**
 * A rule that looks at each file in its scope on its own.
 */
abstract class FileRule implements Rule
{
    /**
     * @param  list<string>  $scope  directories relative to source roots, or "." for the whole root
     */
    protected function __construct(protected readonly array $scope) {}

    public function violations(SourceSet $sources): array
    {
        $perFile = array_map(
            fn (SourceFile $file): array => $this->inspect($file, $sources),
            $sources->files(new Scope($this->scope))
        );

        return array_merge(...$perFile);
    }

    /**
     * @return list<Violation>
     */
    abstract protected function inspect(SourceFile $file, SourceSet $sources): array;

    protected function violation(SourceFile $file, int $line, string $subject, string $message, Severity $severity = Severity::Error): Violation
    {
        return new Violation($this->name(), $file->displayPath, $line, $subject, $severity, $message);
    }
}
