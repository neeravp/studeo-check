<?php

use PhpParser\Node\Stmt\Class_;
use Studeo\Check\Engine\SourceFile;

beforeEach(function () {
    $this->file = fn (string $contents): SourceFile => checkSource(['Probe.php' => $contents])->files()[0];
    $this->classes = fn (SourceFile $file): array => array_map(fn (Class_ $class): array => [$class->name->toString(), $class->getStartLine()], $file->concreteClasses());
});

it('lists the named non-abstract classes in document order with their start lines', function () {
    $contents = <<<'PHP'
<?php

namespace N;

abstract class Abs {}
interface I {}
trait T {}
enum E {}
final class Fin {}
readonly class Ro {}
final readonly class FRo {}
class Plain {}
$x = new class {};
PHP;

    $file = ($this->file)($contents);

    expect(($this->classes)($file))->toBe([['Fin', 9], ['Ro', 10], ['FRo', 11], ['Plain', 12]])
        ->and(array_is_list($file->concreteClasses()))->toBeTrue();
});

it('finds a class inside a function, a method, an if and an anonymous class method', function () {
    $contents = <<<'PHP'
<?php

namespace N;

function f() { class InFunc {} }
class Outer { function m() { class InMethod {} $o = new class { function n() { class InAnon {} } }; } }
if (true) { class Cond {} }
class A {} class B {}
PHP;

    expect(($this->classes)(($this->file)($contents)))->toBe([['InFunc', 5], ['Outer', 6], ['InMethod', 6], ['InAnon', 6], ['Cond', 7], ['A', 8], ['B', 8]]);
});

it('gives no classes for a file that does not parse, an empty file or a file with only other kinds', function (string $contents) {
    expect(($this->file)($contents)->concreteClasses())->toBe([]);
})->with([
    'does not parse' => ["<?php\n\nclass A { public \$x = ; }\n"],
    'only the open tag' => ["<?php\n"],
    'no content' => [''],
    'only abstract and interface' => ["<?php\n\nabstract class A {}\ninterface B {}\n"],
]);

it('does not read names from a docblock', function () {
    $contents = "<?php\n\n/** @var \\Acme\\Billing\\Doc \$x */\n\$x = 1;\n";

    expect(($this->file)($contents)->references())->toBe([]);
});

it('gives no references for a file that does not parse, an empty file or a file without names', function (string $contents) {
    expect(($this->file)($contents)->references())->toBe([]);
})->with([
    'does not parse' => ["<?php\n\n\$x = ;\n"],
    'only the open tag' => ["<?php\n"],
    'no content' => [''],
    'no names' => ["<?php\n\n\$a = 1;\n"],
]);
