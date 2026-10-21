<?php

namespace Studeo\Check\Console;

use InvalidArgumentException;
use RuntimeException;
use Studeo\Check\Console\Formatters\JsonFormatter;
use Studeo\Check\Console\Formatters\TextFormatter;
use Studeo\Check\Engine\Baseline;
use Studeo\Check\Engine\CheckResult;
use Studeo\Check\Engine\PathSourceSet;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Engine\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CheckCommand extends Command
{
    public const SUCCESS = Command::SUCCESS;

    public const VIOLATIONS = Command::FAILURE;

    public const UNUSABLE = Command::INVALID;

    public function __construct(
        private readonly TextFormatter $textFormatter = new TextFormatter,
        private readonly JsonFormatter $jsonFormatter = new JsonFormatter,
        private readonly RuleSetResolver $ruleSetResolver = new RuleSetResolver,
        private readonly ComposerNamespaceRoots $namespaceRoots = new ComposerNamespaceRoots,
    ) {
        parent::__construct('check');
    }

    protected function configure(): void
    {
        $this->setDescription('Run static analysis rules over PHP source files')
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Paths to check (files or directories)')
            ->addOption('rules', null, InputOption::VALUE_REQUIRED, 'Rule set class')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'Baseline file')
            ->addOption('update-baseline', null, InputOption::VALUE_NONE, 'Update the baseline file')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->runCheck($input, $output);
        } catch (Throwable $exception) {
            return $this->reportFailure($exception, $output);
        }
    }

    private function runCheck(InputInterface $input, OutputInterface $output): int
    {
        $this->assertValidFormat($input);
        $basePath = (string) getcwd();
        $sources = $this->buildSources($input, $basePath);
        $rules = $this->ruleSetResolver->resolve($input, $basePath);

        if ($input->getOption('update-baseline')) {
            return $this->updateBaseline($input, $sources, $rules);
        }

        return $this->executeAndReport($sources, $rules, $input, $output);
    }

    private function executeAndReport(PathSourceSet $sources, RuleSet $rules, InputInterface $input, OutputInterface $output): int
    {
        $baseline = $this->resolveBaseline($input);
        $result = (new Runner($rules, $baseline))->run($sources);
        $this->printResult($result, $input, $output);

        return $result->failed() ? self::VIOLATIONS : self::SUCCESS;
    }

    private function assertValidFormat(InputInterface $input): void
    {
        $format = (string) $input->getOption('format');
        if (! in_array($format, ['text', 'json'], true)) {
            throw new InvalidArgumentException("The --format option must be text or json, got [{$format}].");
        }
    }

    private function buildSources(InputInterface $input, string $basePath): PathSourceSet
    {
        $rawPaths = (array) $input->getArgument('paths');
        $roots = $rawPaths === [] ? $this->defaultRoots($basePath) : array_values(array_map(fn (string $path): string => $this->resolvePath($path, $basePath), $rawPaths));
        $scopeBase = $this->resolveScopeBase($basePath);

        return new PathSourceSet($basePath, $roots, $scopeBase, $this->namespaceRoots->read($basePath));
    }

    private function resolveScopeBase(string $basePath): ?string
    {
        $appDir = $basePath.DIRECTORY_SEPARATOR.'app';
        if (is_dir($appDir)) {
            return $appDir;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function defaultRoots(string $basePath): array
    {
        $scopeBase = $this->resolveScopeBase($basePath);
        if ($scopeBase !== null) {
            return [$scopeBase];
        }

        return [$basePath];
    }

    private function resolvePath(string $path, string $basePath): string
    {
        $absolute = $this->toAbsolutePath($path, $basePath);
        if (! file_exists($absolute)) {
            throw new RuntimeException("Path [{$path}] does not exist.");
        }

        return $absolute;
    }

    private function toAbsolutePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        $clean = $this->cleanRelativePath($path);
        if ($clean === '') {
            return $basePath;
        }

        return $basePath.DIRECTORY_SEPARATOR.$clean;
    }

    private function cleanRelativePath(string $path): string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        $filtered = array_values(array_filter($segments, fn (string $segment): bool => ! in_array($segment, ['.', ''], true)));

        return implode(DIRECTORY_SEPARATOR, $filtered);
    }

    private function updateBaseline(InputInterface $input, PathSourceSet $sources, RuleSet $rules): int
    {
        $baselinePath = $input->getOption('baseline');
        if ($this->isMissingPath($baselinePath)) {
            throw new InvalidArgumentException('--update-baseline requires --baseline=<file>.');
        }

        $result = (new Runner($rules))->run($sources);
        Baseline::fromViolations($result->violations)->save($baselinePath);

        return self::SUCCESS;
    }

    private function resolveBaseline(InputInterface $input): ?Baseline
    {
        $path = $input->getOption('baseline');
        if ($this->isMissingPath($path)) {
            return null;
        }

        return Baseline::fromFile((string) $path);
    }

    private function isMissingPath(mixed $path): bool
    {
        return ! is_string($path) || $path === '';
    }

    private function printResult(CheckResult $result, InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('format') === 'json') {
            $output->writeln($this->jsonFormatter->format($result));

            return;
        }

        $output->write($this->textFormatter->format($result));
    }

    private function reportFailure(Throwable $exception, OutputInterface $output): int
    {
        $target = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $target->writeln("<error>{$exception->getMessage()}</error>");

        return self::UNUSABLE;
    }
}
