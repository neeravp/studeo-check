<?php

namespace Studeo\Check\Console;

use RuntimeException;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Profiles\Cap1Rules;
use Symfony\Component\Console\Input\InputInterface;

final readonly class RuleSetResolver
{
    public function resolve(InputInterface $input, string $basePath): RuleSet
    {
        $class = $this->resolveClass($input, $basePath);

        return $this->buildRuleSet($class);
    }

    private function resolveClass(InputInterface $input, string $basePath): string
    {
        $option = $input->getOption('rules');
        if ($this->isGivenOption($option)) {
            return (string) $option;
        }

        $rootConfig = $basePath.DIRECTORY_SEPARATOR.'check.php';
        if (is_file($rootConfig)) {
            return (string) (require $rootConfig);
        }

        return Cap1Rules::class;
    }

    private function isGivenOption(mixed $option): bool
    {
        return is_string($option) && $option !== '';
    }

    private function buildRuleSet(string $class): RuleSet
    {
        $provider = $this->instantiateProvider($class);
        $callback = [$provider, 'rules'];
        if (! is_callable($callback)) {
            throw new RuntimeException("Class [{$class}] is not a rule set: missing rules() method.");
        }
        $rules = $callback();

        if (! $rules instanceof RuleSet) {
            throw new RuntimeException("Class [{$class}] rules() must return a RuleSet.");
        }

        return $rules;
    }

    private function instantiateProvider(string $class): object
    {
        if (! class_exists($class)) {
            throw new RuntimeException("Rule set class [{$class}] does not exist.");
        }

        $provider = new $class;
        if (! method_exists($provider, 'rules')) {
            throw new RuntimeException("Class [{$class}] is not a rule set: missing rules() method.");
        }

        return $provider;
    }
}
