<?php

use Studeo\Check\Rules\MethodComplexity;

it('flags a method only above the maximum', function (string $body, bool $flagged) {
    $files = ['Actions/Run.php' => phpFile("final class Run { public function go(\$a, \$b) { {$body} } }")];

    expect(runRule(MethodComplexity::options(max: 1), $files) !== [])->toBe($flagged);
})->with([
    'none' => ['return 0;', false],
    'equal to the maximum' => ['if ($a) { return 1; } return 0;', false],
    'one above the maximum' => ['if ($a) { return 1; } if ($b) { return 2; } return 0;', true],
    'an if and an elseif' => ['if ($a) { return 1; } elseif ($b) { return 2; } return 0;', true],
]);

it('counts each elseif and words the message with the count and the maximum', function () {
    $body = 'final class Run { public function go($a, $b, $c) { if ($a) { return 1; } elseif ($b) { return 2; } elseif ($c) { return 3; } return 0; } }';

    $found = runRule(MethodComplexity::options(), ['Actions/Run.php' => phpFile($body)]);

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toBe('3 if/elseif statements (max 2). Use an early return, match(), or extract a named helper.');
});

it('reports routines in the order they appear, functions included', function () {
    $two = 'if ($a) { return 1; } if ($a) { return 2; }';
    $body = "final class Run\n{\n    public function go(\$a)\n    {\n        {$two}\n    }\n\n    public function again(\$a)\n    {\n        {$two}\n    }\n}\n\nfunction top(\$a)\n{\n    {$two}\n}";

    expect(summarise(runRule(MethodComplexity::options(max: 1), ['Actions/Run.php' => phpFile($body)])))->toBe([
        'modules/Blog/Actions/Run.php:8 Run::go',
        'modules/Blog/Actions/Run.php:13 Run::again',
        'modules/Blog/Actions/Run.php:19 top()',
    ]);
});

it('never flags a method without a body', function () {
    $body = 'interface Reads { public function read($a); } abstract class Base { abstract public function run($a); }';

    expect(runRule(MethodComplexity::options(max: 1), ['Actions/Run.php' => phpFile($body)]))->toBe([]);
});

it('checks the methods of enums and traits', function () {
    $two = 'if ($a) { return 1; } if ($a) { return 2; }';
    $body = "enum Kind { public function label(\$a) { {$two} } } trait Helps { public function help(\$a) { {$two} } }";

    expect(summarise(runRule(MethodComplexity::options(max: 1), ['Actions/Kind.php' => phpFile($body)])))->toBe([
        'modules/Blog/Actions/Kind.php:6 Kind::label',
        'modules/Blog/Actions/Kind.php:6 Helps::help',
    ]);
});

it('reports a method with a doc comment and an attribute at the attribute line', function () {
    $two = 'if ($a) { return 1; } if ($a) { return 2; }';
    $body = "final class Run\n{\n    /** Goes. */\n    #[Mark]\n    public function go(\$a) { {$two} }\n}";

    expect(summarise(runRule(MethodComplexity::options(max: 1), ['Actions/Run.php' => phpFile($body)])))->toBe(['modules/Blog/Actions/Run.php:9 Run::go']);
});

it('pins current behaviour: a named function inside a method counts toward both', function () {
    $body = 'final class Run { public function go($a) { function inner($b) { if ($b) { return 1; } return 0; } if ($a) { return 2; } return 0; } }';

    $found = runRule(MethodComplexity::options(max: 1), ['Actions/Run.php' => phpFile($body)]);

    expect(summarise($found))->toBe(['modules/Blog/Actions/Run.php:6 Run::go'])
        ->and($found[0]->message)->toStartWith('2 if/elseif statements (max 1)');
});

it('does not count the conditionals of a class declared inside a method', function () {
    $body = 'final class Run { public function go($a) { if ($a) { return 1; } $x = new class { public function inner($b) { if ($b) { return 1; } return 0; } }; return $x; } }';

    expect(runRule(MethodComplexity::options(max: 1), ['Actions/Run.php' => phpFile($body)]))->toBe([]);
});
