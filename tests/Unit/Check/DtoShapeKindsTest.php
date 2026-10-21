<?php

use Studeo\Check\Rules\Laravel\DtoShape;
use Studeo\Check\Tests\Support\DtoShapeFiles;

it('passes a well formed Input and Data, including one that extends a base in the module', function () {
    $files = [
        ...DtoShapeFiles::file(sprintf(DtoShapeFiles::INPUT_CLASS, 'CreatePostInput'), 'CreatePostInput'),
        ...DtoShapeFiles::file('final readonly class PostData extends OutputData { public function __construct(public string $title) {} }'),
        ...DtoShapeFiles::file('abstract readonly class BaseInput extends InputData {}', 'BaseInput'),
        ...DtoShapeFiles::file('final readonly class EditPostInput extends BaseInput { protected function __construct(public string $title) {} }', 'EditPostInput'),
    ];

    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $files))->toBe([]);
});

it('reports a name that is neither Input nor Data, naming both valid forms', function () {
    $found = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file('final readonly class PostDto extends OutputData {}', 'PostDto'));

    expect($found)->toHaveCount(1)
        ->and($found[0]->subject)->toBe('PostDto')
        ->and($found[0]->message)->toContain('*Input')->toContain('*Data');
});

it('reports an Input that extends OutputData and a Data that extends InputData as such', function (string $name, string $parent, string $expected) {
    $found = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file("final readonly class {$name} extends {$parent} { protected function __construct() {} }", $name));

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toContain($expected);
})->with([
    'Input extends OutputData' => ['PostInput', 'OutputData', 'extends OutputData'],
    'Data extends InputData' => ['PostData', 'InputData', 'extends InputData'],
]);

it('reports an Input or Data that extends nothing or an unrelated class', function (string $name, string $declaration, string $expected) {
    $found = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file($declaration, $name));

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toContain($expected);
})->with([
    'Input extends nothing' => ['PostInput', 'final readonly class PostInput { protected function __construct() {} }', DTO_INPUT_BASE],
    'Data extends nothing' => ['PostData', 'final readonly class PostData {}', DTO_OUTPUT_BASE],
    'Data extends another module class' => ['PostData', 'final readonly class PostData extends \\Acme\\Blog\\Models\\Post {}', DTO_OUTPUT_BASE],
]);

it('reports an Input whose constructor is public, private or missing', function (string $constructor, string $expected) {
    $found = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file("final readonly class PostInput extends InputData { {$constructor} }", 'PostInput'));

    expect($found)->toHaveCount(1)
        ->and($found[0]->subject)->toBe('PostInput')
        ->and($found[0]->message)->toContain($expected);
})->with([
    'public' => ['public function __construct(public string $title) {}', 'protected'],
    'private' => ['private function __construct(public string $title) {}', 'protected'],
    'missing' => ['public string $title;', 'protected'],
]);

it('lets an Input inherit a protected constructor from a base in the module', function () {
    $files = [...DtoShapeFiles::file('abstract readonly class BaseInput extends InputData { protected function __construct() {} }', 'BaseInput'), ...DtoShapeFiles::file('final readonly class PostInput extends BaseInput {}', 'PostInput')];

    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $files))->toBe([]);
});

it('does not ask for a protected constructor on Data', function () {
    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file('final readonly class PostData extends OutputData { public function __construct(public string $title) {} }')))->toBe([]);
});

it('reports a DTO that implements Wireable, by full name', function () {
    $input = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file('final readonly class PostInput extends InputData implements Wireable { protected function __construct() {} }', 'PostInput'));
    $data = runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), DtoShapeFiles::file('final readonly class PostData extends OutputData implements \\Livewire\\Wireable {}'));

    expect($input)->toHaveCount(1)->and($input[0]->message)->toContain('Wireable')
        ->and($data)->toHaveCount(1)->and($data[0]->message)->toContain('Wireable');
});

it('does not mistake another Wireable for Livewire\'s', function () {
    $file = ['Data/PostData.php' => phpFile('final readonly class PostData extends OutputData implements Wireable {}', 'Acme\\Blog\\Data', "use Acme\\Dto\\OutputData;\nuse Other\\Wireable;\n")];

    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $file))->toBe([]);
});

it('finds the classes of nested Data directories and ignores anonymous ones', function () {
    $nested = ['Data/Posts/PostDto.php' => phpFile('final readonly class PostDto extends OutputData {}', 'Acme\\Blog\\Data\\Posts', DtoShapeFiles::USES)];
    $anonymous = DtoShapeFiles::file('return new class {};', 'Anonymous');

    expect(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $nested))->toHaveCount(1)
        ->and(runRule(DtoShape::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $anonymous))->toBe([]);
});
