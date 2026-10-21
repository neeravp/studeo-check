<?php

use Studeo\Check\Rules\Laravel\DtoShape;

beforeEach(function () {
    $uses = "use Acme\\Dto\\InputData;\nuse Acme\\Dto\\OutputData;\nuse Livewire\\Wireable;\n";

    $this->bodyLine = 6 + substr_count($uses, "\n");
    $this->dto = fn (string $body, string $name = 'PostData', string $directory = 'Data'): array => ["{$directory}/{$name}.php" => phpFile($body, 'Acme\\Blog\\Data', $uses)];
    $this->mutable = fn (string $names): string => "DTO properties must be readonly; mutable: {$names}. Declare the class readonly.";
    $this->found = fn (string $body, string $name = 'PostData') => runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), ($this->dto)($body, $name));
});

it('lists several mutable declared properties of one statement and of later statements in declaration order', function () {
    $body = 'final class PostData extends OutputData { public string $a, $b; protected ?int $c = null; private $d; var $e; public static int $s = 0; public readonly int $r; }';

    $found = ($this->found)($body);

    expect(array_column($found, 'message'))->toBe([($this->mutable)('$a, $b, $c, $d, $e')]);
});

it('does not call a static declared property mutable', function () {
    expect(($this->found)('final class PostData extends OutputData { public static int $count = 0; }'))->toBe([]);
});

it('does not call a readonly declared property mutable', function () {
    expect(($this->found)('final class PostData extends OutputData { public readonly string $title; }'))->toBe([]);
});

it('lists declared names before promoted names in the same message', function () {
    $body = 'final class PostData extends OutputData { public function __construct(public string $x, protected int $y) {} public string $a; }';

    expect(array_column(($this->found)($body), 'message'))->toBe([($this->mutable)('$a, $x, $y')]);
});

it('calls a promoted non-readonly parameter mutable whatever its visibility', function (string $modifier) {
    $found = ($this->found)("final class PostData extends OutputData { public function __construct({$modifier} string \$title) {} }");

    expect(array_column($found, 'message'))->toBe([($this->mutable)('$title')]);
})->with(['public', 'protected', 'private']);

it('does not call a plain constructor parameter mutable', function () {
    expect(($this->found)('final class PostData extends OutputData { public function __construct(string $title) {} }'))->toBe([]);
});

it('does not call a bare readonly promoted parameter mutable', function () {
    expect(($this->found)('final class PostData extends OutputData { public function __construct(readonly string $title) {} }'))->toBe([]);
});

it('reports not final, then mutable, then the contract problems of one class', function () {
    $found = ($this->found)('class PostDto extends OutputData { public string $title; }', 'PostDto');

    expect($found)->toHaveCount(3)
        ->and($found[0]->message)->toContain('DTO classes must be final')
        ->and($found[1]->message)->toBe(($this->mutable)('$title'))
        ->and($found[2]->message)->toContain('the name must end in Input or Data')
        ->and(array_unique(array_column($found, 'line')))->toBe([$this->bodyLine])
        ->and(array_unique(array_column($found, 'subject')))->toBe(['PostDto']);
});

it('reports two concrete classes of one file in document order with their own lines and names', function () {
    $body = "final class AData extends OutputData { public string \$x; }\nfinal class BData extends OutputData { public string \$y; }";

    $found = ($this->found)($body, 'AData');

    expect(array_column($found, 'subject'))->toBe(['AData', 'BData'])
        ->and(array_column($found, 'line'))->toBe([$this->bodyLine, $this->bodyLine + 1])
        ->and(array_column($found, 'message'))->toBe([($this->mutable)('$x'), ($this->mutable)('$y')]);
});

it('skips the abstract and the anonymous class between two concrete ones', function () {
    $body = "final class AData extends OutputData { public string \$x; }\nabstract class Base { public string \$z; }\n\$object = new class { public string \$w; };\nfinal class CData extends OutputData { public string \$y; }";

    $found = ($this->found)($body, 'AData');

    expect(array_column($found, 'subject'))->toBe(['AData', 'CData'])
        ->and(array_column($found, 'line'))->toBe([$this->bodyLine, $this->bodyLine + 3]);
});

it('reports a class at its first attribute line, not at its docblock', function () {
    $found = ($this->found)("/** A post. */\n#[Example]\nfinal class PostData extends OutputData { public string \$x; }");

    expect(array_column($found, 'line'))->toBe([$this->bodyLine + 1]);
});

it('reports two classes written on one line at the same line, in document order', function () {
    $found = ($this->found)('final class AData extends OutputData { public string $x; } final class BData extends OutputData { public string $y; }', 'AData');

    expect(array_column($found, 'subject'))->toBe(['AData', 'BData'])
        ->and(array_unique(array_column($found, 'line')))->toBe([$this->bodyLine]);
});

it('gives no finding for a file without a concrete class', function (string $body) {
    expect(($this->found)($body))->toBe([]);
})->with([
    'interface' => ['interface PostContract { public function title(): string; }'],
    'trait' => ['trait HasTitle { public string $title; }'],
    'enum' => ['enum PostState: string { case Draft = \'draft\'; }'],
    'function only' => ['function title(): string { return \'x\'; }'],
    'abstract only' => ['abstract class PostData { public string $title; }'],
]);

it('gives no finding for an empty php file', function () {
    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), ['Data/PostData.php' => "<?php\n"]))->toBe([]);
});

it('gives no finding for a file that does not parse', function () {
    expect(($this->found)('final class PostData extends OutputData { public string $title = ; }'))->toBe([]);
});

it('reports a class declared inside a function body', function () {
    $found = ($this->found)('function build() { final class InnerData extends OutputData { public string $title; } }');

    expect(array_column($found, 'subject'))->toBe(['InnerData'])
        ->and(array_column($found, 'message'))->toBe([($this->mutable)('$title')]);
});

it('leaves a sibling directory that only starts with Data out of the default scope', function () {
    $files = ($this->dto)('class PostData { public string $title; }', 'PostData', 'DataExtra');

    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $files))->toBe([]);
});
