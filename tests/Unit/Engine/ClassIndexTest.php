<?php

use Studeo\Check\Engine\ClassIndex;

it('determines descendsFrom through two levels', function () {
    $sources = checkSource([
        'GrandParent.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass GrandParent {}\n",
        'ParentClass.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass ParentClass extends GrandParent {}\n",
        'Child.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass Child extends ParentClass {}\n",
    ]);

    $index = ClassIndex::of($sources->files());

    expect($index->descendsFrom('Acme\\Pkg\\Child', 'Acme\\Pkg\\ParentClass'))->toBeTrue()
        ->and($index->descendsFrom('Acme\\Pkg\\Child', 'Acme\\Pkg\\GrandParent'))->toBeTrue()
        ->and($index->descendsFrom('Acme\\Pkg\\ParentClass', 'Acme\\Pkg\\GrandParent'))->toBeTrue()
        ->and($index->descendsFrom('Acme\\Pkg\\ParentClass', 'Acme\\Pkg\\Child'))->toBeFalse()
        ->and($index->descendsFrom('Acme\\Pkg\\GrandParent', 'Acme\\Pkg\\Child'))->toBeFalse();
});

it('terminates when class inheritance contains a cycle', function () {
    $sources = checkSource([
        'CycleA.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass CycleA extends CycleB {}\n",
        'CycleB.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass CycleB extends CycleA {}\n",
    ]);

    $index = ClassIndex::of($sources->files());

    expect($index->descendsFrom('Acme\\Pkg\\CycleA', 'Acme\\Pkg\\CycleB'))->toBeTrue()
        ->and($index->descendsFrom('Acme\\Pkg\\CycleB', 'Acme\\Pkg\\CycleA'))->toBeTrue()
        ->and($index->descendsFrom('Acme\\Pkg\\CycleA', 'Acme\\Pkg\\NonExistent'))->toBeFalse();
});

it('returns the nearest constructor (child before parent, parent when child has none)', function () {
    $sources = checkSource([
        'BaseWithCtor.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass BaseWithCtor {\n    public function __construct(public string \$baseParam) {}\n}\n",
        'ChildWithCtor.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass ChildWithCtor extends BaseWithCtor {\n    public function __construct(public int \$childParam) {}\n}\n",
        'ChildWithoutCtor.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass ChildWithoutCtor extends BaseWithCtor {}\n",
        'NoCtorAtAll.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass NoCtorAtAll {}\n",
    ]);

    $index = ClassIndex::of($sources->files());

    $childCtor = $index->constructorOf('Acme\\Pkg\\ChildWithCtor');
    expect($childCtor)->not->toBeNull()
        ->and((string) $childCtor->params[0]->var->name)->toBe('childParam');

    $parentCtor = $index->constructorOf('Acme\\Pkg\\ChildWithoutCtor');
    expect($parentCtor)->not->toBeNull()
        ->and((string) $parentCtor->params[0]->var->name)->toBe('baseParam');

    expect($index->constructorOf('Acme\\Pkg\\NoCtorAtAll'))->toBeNull();
});

it('treats a parent outside the set as a name only', function () {
    $sources = checkSource([
        'ChildOfExternal.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass ChildOfExternal extends \\External\\Vendor\\ParentService {}\n",
    ]);

    $index = ClassIndex::of($sources->files());

    expect($index->descendsFrom('Acme\\Pkg\\ChildOfExternal', 'External\\Vendor\\ParentService'))->toBeTrue()
        ->and($index->descendsFrom('Acme\\Pkg\\ChildOfExternal', 'Other\\Vendor\\Class'))->toBeFalse()
        ->and($index->constructorOf('Acme\\Pkg\\ChildOfExternal'))->toBeNull();
});

it('does not index an anonymous class', function () {
    $sources = checkSource([
        'Factory.php' => "<?php\n\nnamespace Acme\\Pkg;\n\nclass Factory {\n    public function make() {\n        return new class extends NamedBase {};\n    }\n}\n",
    ]);

    $index = ClassIndex::of($sources->files());

    expect($index->descendsFrom('Acme\\Pkg\\Factory', 'Something'))->toBeFalse()
        ->and($index->constructorOf('class@anonymous'))->toBeNull()
        ->and($index->descendsFrom('class@anonymous', 'Acme\\Pkg\\Factory'))->toBeFalse()
        ->and($index->descendsFrom('', 'NamedBase'))->toBeFalse();
});
