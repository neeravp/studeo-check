<?php

use Studeo\Check\Console\CheckCommand;
use Studeo\Check\Profiles\ActionRules;
use Studeo\Check\Profiles\Cap1Rules;
use Studeo\Check\Profiles\LaravelRules;

const DOCS_PSEUDO_RULES = ['syntax', 'baseline'];
const DOCS_OTHER_TOOL_OPTIONS = ['--record', '--dev'];

function docsRead(string $file): string
{
    return (string) file_get_contents(dirname(__DIR__, 3)."/{$file}");
}

/**
 * @return list<object> every rule the three profiles build
 */
function docsRules(): array
{
    $sets = [
        Cap1Rules::make()->rules(),
        ActionRules::make('App\\Attributes\\System')->rules(),
        LaravelRules::make('App\\Data\\BaseInput', 'App\\Data\\BaseData')->rules(),
    ];

    return array_merge(...array_map(fn ($set): array => $set->rules(), $sets));
}

/**
 * @return list<string>
 */
function docsRuleIds(): array
{
    $ids = array_map(fn ($rule): string => $rule->name(), docsRules());
    sort($ids);

    return $ids;
}

/**
 * @return list<string>
 */
function docsMatches(string $pattern, string $text): array
{
    preg_match_all($pattern, $text, $found);

    return $found[1];
}

/**
 * @return list<string>
 */
function docsSorted(array $values): array
{
    $values = array_values(array_unique($values));
    sort($values);

    return $values;
}

/**
 * @return array<string, list<string>> rule id => option cell entries of the SPEC option table
 */
function docsSpecOptionRows(): array
{
    $rows = [];
    foreach (explode("\n", docsRead('SPEC.md')) as $line) {
        if (preg_match('/^\| `([a-z-]+)` \| \d \| /', $line, $match) === 1) {
            $cells = explode(' | ', trim($line, '| '));
            $rows[$match[1]] = array_map(fn (string $entry): string => trim($entry, '` '), explode('<br>', $cells[2]));
        }
    }

    return $rows;
}

function docsDeclaredOption(ReflectionParameter $parameter): string
{
    $default = match (true) {
        ! $parameter->isDefaultValueAvailable() => '',
        is_int($parameter->getDefaultValue()) => ' = '.$parameter->getDefaultValue(),
        is_string($parameter->getDefaultValue()) => " = '".$parameter->getDefaultValue()."'",
        default => ' = ...',
    };

    return $parameter->getType().' $'.$parameter->getName().$default;
}

function docsDocumentedOption(string $entry): string
{
    return preg_match('/^(\w+ \$\w+) = (\[|Scope::)/', $entry, $match) === 1 ? "{$match[1]} = ..." : $entry;
}

it('documents every rule id in the README table, the SPEC tables and the API reference', function () {
    $ids = docsRuleIds();

    expect(docsSorted(docsMatches('/^\| `([a-z-]+)` \| [A-Z]/m', docsRead('README.md'))))->toBe($ids)
        ->and(docsSorted(array_keys(docsSpecOptionRows())))->toBe($ids)
        ->and(docsSorted(docsMatches('/^### `([a-z-]+)`$/m', docsRead('SPEC.md'))))->toBe($ids)
        ->and(docsSorted(docsMatches('/^### `([a-z-]+)`$/m', docsRead('API.md'))))->toBe(docsSorted([...$ids, ...DOCS_PSEUDO_RULES]));
});

it('lists the options of each rule in the SPEC table as the options() factory declares them', function () {
    $mismatches = [];
    foreach (docsRules() as $rule) {
        $declared = array_map(docsDeclaredOption(...), (new ReflectionMethod($rule, 'options'))->getParameters());
        $documented = array_map(docsDocumentedOption(...), docsSpecOptionRows()[$rule->name()] ?? []);
        $mismatches[$rule->name()] = $declared === $documented ? null : ['code' => $declared, 'spec' => $documented];
    }

    expect(array_filter($mismatches))->toBe([]);
});

it('names only command options the check command defines', function () {
    $defined = array_map(fn ($option): string => '--'.$option->getName(), array_values((new CheckCommand)->getDefinition()->getOptions()));
    $mentioned = [];
    foreach (['README.md', 'API.md', 'SPEC.md'] as $file) {
        $mentioned = [...$mentioned, ...docsMatches('/(?<![\w-])(--[a-z][a-z-]*)/', docsRead($file))];
    }

    expect(array_diff(docsSorted($mentioned), $defined, DOCS_OTHER_TOOL_OPTIONS))->toBe([])
        ->and(array_diff(['--rules', '--baseline', '--update-baseline', '--format'], $defined))->toBe([]);
});

it('documents exactly the exit codes the check command returns', function () {
    $documented = docsSorted(docsMatches('/^- `(\d)`:/m', docsRead('SPEC.md')));

    expect($documented)->toBe(['0', '1', '2'])
        ->and([CheckCommand::SUCCESS, CheckCommand::VIOLATIONS, CheckCommand::UNUSABLE])->toBe([0, 1, 2]);
});

it('names only fixtures that exist, and names every fixture', function () {
    $root = dirname(__DIR__, 3);
    $named = docsSorted(docsMatches('#`spec/([a-z-]+/[a-z0-9-]+)`#', docsRead('SPEC.md')));
    $existing = array_map(fn (string $file): string => substr(dirname($file), strlen("{$root}/spec/")), glob("{$root}/spec/*/*/expected.json") ?: []);

    expect($named)->toBe(docsSorted($existing));
});
