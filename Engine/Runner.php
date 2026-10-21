<?php

namespace Studeo\Check\Engine;

use LogicException;
use PhpParser\Error;

final readonly class Runner
{
    public function __construct(
        private RuleSet $rules,
        private ?Baseline $baseline = null,
    ) {}

    public function run(SourceSet $sources): CheckResult
    {
        $all = $this->collectAll($sources);

        return $this->evaluate($all);
    }

    /**
     * @return list<Violation>
     */
    private function collectAll(SourceSet $sources): array
    {
        $violations = [...$this->syntaxViolations($sources), ...$this->ruleViolations($sources)];
        usort($violations, $this->compareViolations(...));

        return $violations;
    }

    /**
     * @param  list<Violation>  $violations
     */
    private function evaluate(array $violations): CheckResult
    {
        if ($this->baseline === null) {
            return new CheckResult($violations);
        }

        $applied = $this->baseline->apply($violations);

        return new CheckResult($applied['active'], $applied['stale'], true);
    }

    /**
     * @return list<Violation>
     */
    private function syntaxViolations(SourceSet $sources): array
    {
        $broken = array_filter($sources->files(), fn (SourceFile $file): bool => $file->syntaxError() !== null);

        return array_values(array_map($this->syntaxViolation(...), $broken));
    }

    private function syntaxViolation(SourceFile $file): Violation
    {
        $error = $file->syntaxError();
        if (! $error instanceof Error) {
            throw new LogicException("File {$file->displayPath} has no syntax error.");
        }

        return new Violation(
            'syntax',
            $file->displayPath,
            max(1, $error->getStartLine()),
            'file',
            Severity::Error,
            "does not parse: {$error->getRawMessage()}. Fix the syntax; no other rule can check this file until it parses.",
        );
    }

    /**
     * @return list<Violation>
     */
    private function ruleViolations(SourceSet $sources): array
    {
        $perRule = array_map(fn (Rule $rule): array => $rule->violations($sources), $this->rules->rules());

        return array_merge(...$perRule);
    }

    private function compareViolations(Violation $a, Violation $b): int
    {
        return [$a->file, $a->line, $a->rule, $a->subject] <=> [$b->file, $b->line, $b->rule, $b->subject];
    }
}
