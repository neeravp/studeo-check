<?php

namespace Studeo\Check\Rules;

use Studeo\Check\Engine\RuleOptions;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;

/**
 * Over `hard` lines is an error, over `target` a warning.
 */
final class FileLength extends FileRule
{
    public const NAME = 'file-length';

    /**
     * @param  list<string>  $scope
     */
    private function __construct(array $scope, private readonly int $target, private readonly int $hard)
    {
        parent::__construct($scope);
    }

    /**
     * @param  array<int, string>  $scope  directories to check
     */
    public static function options(int $target = 230, int $hard = 300, array $scope = Scope::APP_CODE): self
    {
        $target = RuleOptions::positive(self::NAME, 'target', $target);
        $hard = RuleOptions::positive(self::NAME, 'hard', $hard);

        if ($target > $hard) {
            throw InvalidRuleConfigurationException::option(self::NAME, 'target', "must not be above hard ({$hard}).");
        }

        return new self(RuleOptions::scope(self::NAME, $scope), $target, $hard);
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        $lines = $file->lineCount();

        return match (true) {
            $lines > $this->hard => [$this->violation($file, 1, 'file', "{$lines} lines exceeds the hard cap of {$this->hard}. Split by responsibility.")],
            $lines > $this->target => [$this->violation($file, 1, 'file', "{$lines} lines exceeds the {$this->target}-line target. Extract a collaborator or helper class.", Severity::Warning)],
            default => [],
        };
    }
}
