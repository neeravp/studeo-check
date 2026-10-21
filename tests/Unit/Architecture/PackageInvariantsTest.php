<?php

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Studeo\Check\Engine\Rule;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

const INVARIANT_SOURCE_DIRECTORIES = ['Console', 'Engine', 'Exceptions', 'Profiles', 'Rules'];
const INVARIANT_IO_FUNCTIONS = [
    'file_get_contents', 'file_put_contents', 'file', 'fopen', 'fwrite', 'fread', 'readfile', 'is_file', 'is_dir', 'file_exists',
    'glob', 'scandir', 'mkdir', 'unlink', 'rename', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'getenv',
    'time', 'microtime', 'date', 'random_int', 'random_bytes', 'rand', 'mt_rand', 'uniqid',
];

/**
 * @return array<string, list<string>> directory => namespaces its files must not import
 */
function invariantForbiddenImports(): array
{
    return [
        'Engine' => ['Studeo\\Check\\Rules', 'Studeo\\Check\\Profiles', 'Studeo\\Check\\Console'],
        'Exceptions' => ['Studeo\\Check\\Rules', 'Studeo\\Check\\Profiles', 'Studeo\\Check\\Console'],
        'Rules' => ['Studeo\\Check\\Rules\\Laravel', 'Studeo\\Check\\Profiles', 'Studeo\\Check\\Console'],
        'Rules/Laravel' => ['Studeo\\Check\\Profiles', 'Studeo\\Check\\Console'],
        'Profiles' => ['Studeo\\Check\\Console'],
    ];
}

/**
 * @return array<string, list<Node>> package-relative path => syntax tree, for every source file
 */
function invariantSyntaxTrees(): array
{
    $root = dirname(__DIR__, 3);
    $parser = (new ParserFactory)->createForHostVersion();
    $trees = [];

    foreach ((new Finder)->files()->in(array_map(fn (string $directory): string => "{$root}/{$directory}", INVARIANT_SOURCE_DIRECTORIES))->name('*.php') as $file) {
        $trees[substr($file->getPathname(), strlen("{$root}/"))] = $parser->parse((string) file_get_contents($file->getPathname())) ?? [];
    }

    return $trees;
}

/**
 * @param  list<Node>  $tree
 * @return list<string>
 */
function invariantImports(array $tree): array
{
    $uses = (new NodeFinder)->findInstanceOf($tree, Use_::class);

    return array_merge(...array_map(fn (Use_ $use): array => array_map(fn ($item): string => $item->name->toString(), $use->uses), $uses));
}

function invariantIsNamedConstructor(ClassMethod $method): bool
{
    $type = $method->returnType instanceof Node\Identifier || $method->returnType instanceof Node\Name ? (string) $method->returnType : '';
    $returnsItself = in_array($type, ['self', 'static'], true) || str_contains((string) $method->getDocComment(), '@return list<self>');

    return $method->isStatic() && $method->isPublic() && $returnsItself;
}

/**
 * @param  list<Node>  $tree
 * @return list<ClassMethod> every static method that is not a named constructor, whatever its visibility
 */
function invariantStaticHelperMethods(array $tree): array
{
    $methods = (new NodeFinder)->findInstanceOf($tree, ClassMethod::class);

    return array_values(array_filter($methods, fn (ClassMethod $method): bool => $method->isStatic() && ! invariantIsNamedConstructor($method)));
}

function invariantIsReachable(ClassMethod $method): bool
{
    return ! $method->isPrivate();
}

function invariantIsFinalAndStateless(Class_ $class): bool
{
    $isVisitor = (string) $class->extends === 'NodeVisitorAbstract';
    $instanceMethods = array_filter($class->getMethods(), fn (ClassMethod $method): bool => ! $method->isStatic());

    return $class->isFinal() && ($isVisitor || ($class->getProperties() === [] && $instanceMethods === []));
}

it('declares no trait and uses none', function () {
    $offenders = array_keys(array_filter(
        invariantSyntaxTrees(),
        fn (array $tree): bool => (new NodeFinder)->find($tree, fn (Node $node): bool => $node instanceof Trait_ || $node instanceof TraitUse) !== [],
    ));

    expect($offenders)->toBe([]);
});

it('keeps classes that offer static helpers final and stateless', function () {
    $offenders = [];

    foreach (invariantSyntaxTrees() as $path => $tree) {
        $isHelperClass = array_filter(invariantStaticHelperMethods($tree), invariantIsReachable(...)) !== [];
        $named = array_filter((new NodeFinder)->findInstanceOf($tree, Class_::class), fn (Class_ $class): bool => $class->name !== null);
        $classes = $isHelperClass ? $named : [];
        $offenders = [...$offenders, ...array_map(fn (Class_ $class): string => "{$path} is not final and stateless", array_filter($classes, fn (Class_ $class): bool => ! invariantIsFinalAndStateless($class)))];
    }

    expect($offenders)->toBe([]);
});

it('does no input or output in static helpers', function () {
    $offenders = [];

    foreach (invariantSyntaxTrees() as $path => $tree) {
        foreach (invariantStaticHelperMethods($tree) as $method) {
            $calls = (new NodeFinder)->findInstanceOf($method, FuncCall::class);
            $io = array_filter($calls, fn (FuncCall $call): bool => $call->name instanceof Node\Name && in_array($call->name->toString(), INVARIANT_IO_FUNCTIONS, true));
            $offenders = [...$offenders, ...array_map(fn (FuncCall $call): string => "{$path}::{$method->name} calls {$call->name}", $io)];
        }
    }

    expect($offenders)->toBe([]);
});

it('declares every concrete rule final', function () {
    $notFinal = [];

    foreach (array_keys(invariantSyntaxTrees()) as $path) {
        $class = 'Studeo\\Check\\'.str_replace('/', '\\', substr($path, 0, -4));
        $reflection = class_exists($class) ? new ReflectionClass($class) : null;
        $isConcreteRule = $reflection !== null && $reflection->implementsInterface(Rule::class) && ! $reflection->isAbstract();
        $notFinal[] = $isConcreteRule && ! $reflection->isFinal() ? $path : null;
    }

    expect(array_values(array_filter($notFinal)))->toBe([]);
});

it('keeps the layers apart: no import points from a lower layer to a higher one', function () {
    $violations = [];

    foreach (invariantSyntaxTrees() as $path => $tree) {
        foreach (invariantForbiddenImports() as $directory => $forbidden) {
            $isInLayer = $directory === 'Rules' ? dirname($path) === 'Rules' || dirname($path) === 'Rules/Helpers' : dirname($path) === $directory;
            $bad = $isInLayer ? array_filter(invariantImports($tree), fn (string $import): bool => array_filter($forbidden, fn (string $prefix): bool => str_starts_with($import, $prefix)) !== []) : [];
            $violations = [...$violations, ...array_map(fn (string $import): string => "{$path} imports {$import}", $bad)];
        }
    }

    expect($violations)->toBe([]);
});

it('imports nothing from a sibling package or from Illuminate', function () {
    $foreign = [];

    foreach (invariantSyntaxTrees() as $path => $tree) {
        $bad = array_filter(invariantImports($tree), fn (string $import): bool => str_starts_with($import, 'Illuminate\\') || str_starts_with($import, 'Studeo\\') && ! str_starts_with($import, 'Studeo\\Check\\'));
        $foreign = [...$foreign, ...array_map(fn (string $import): string => "{$path} imports {$import}", $bad)];
    }

    expect($foreign)->toBe([]);
});

it('keeps local paths and internal tool names out of tracked files', function () {
    $root = dirname(__DIR__, 3);
    $listing = new Process(['git', 'ls-files'], $root);
    $listing->mustRun();
    $banned = ['/Users/', '/home/', '.gemini', 'dev/agent', '.ai/', 'charter', 'reviewer', 'claude', 'agent'];
    $found = [];

    foreach (array_filter(explode("\n", $listing->getOutput())) as $file) {
        $contents = is_file("{$root}/{$file}") && $file !== 'tests/Unit/Architecture/PackageInvariantsTest.php' ? (string) file_get_contents("{$root}/{$file}") : '';
        $found[$file] = array_values(array_filter($banned, fn (string $word): bool => stripos($contents, $word) !== false));
    }

    expect(array_filter($found))->toBe([]);
});
