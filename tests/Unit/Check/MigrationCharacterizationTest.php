<?php

use Studeo\Check\Engine\Violation;
use Studeo\Check\Rules\Laravel\Migration;

it('flags the plain ->enum() call', function () {
    $files = ['database/migrations/m.php' => phpFile('return new class { public function up($t) { $t->enum("a", []); } };', 'Studeo\\Blog')];

    $found = runRule(Migration::options(), $files);

    expect(summarise($found))->toBe(['modules/Blog/database/migrations/m.php:6 enum()'])
        ->and($found[0]->message)->toBe("uses ->enum(). Use ->string('column', 30)->index() with a backed enum cast.");
});

it('flags the enum call in any letter case, nullsafe or static', function (string $call) {
    $files = ['database/migrations/m.php' => phpFile("return new class { public function up(\$t) { {$call} } };", 'Studeo\\Blog')];

    expect(summarise(runRule(Migration::options(), $files)))->toBe(['modules/Blog/database/migrations/m.php:6 enum()']);
})->with([
    'upper case method name' => ['$t->ENUM("a", []);'],
    'mixed case method name' => ['$t->eNum("a", []);'],
    'nullsafe call' => ['$t?->enum("a", []);'],
    'static call' => ['Column::enum("a", []);'],
]);

it('pins current behaviour: a dynamic method name is not resolved, and these enum-like forms are not flagged', function (string $call) {
    $files = ['database/migrations/m.php' => phpFile("return new class { public function up(\$t) { {$call} } };", 'Studeo\\Blog')];

    expect(runRule(Migration::options(), $files))->toBe([]);
})->with([
    'dynamic name' => ['$t->{"enum"}("a", []);'],
    'property fetch' => ['$x = $t->enum;'],
    'function call' => ['enum("a", []);'],
]);

it('finds an enum call inside a closure', function () {
    $body = 'return new class { public function up() { Schema::create("posts", function ($t) { $t->enum("a", []); }); } };';

    expect(summarise(runRule(Migration::options(), ['database/migrations/m.php' => phpFile($body, 'Studeo\\Blog')])))->toBe(['modules/Blog/database/migrations/m.php:6 enum()']);
});

it('reports two calls on one line as two findings', function () {
    $body = 'return new class { public function up($t) { $t->enum("a", []); $t->enum("b", []); } };';

    expect(summarise(runRule(Migration::options(), ['database/migrations/m.php' => phpFile($body, 'Studeo\\Blog')])))->toBe([
        'modules/Blog/database/migrations/m.php:6 enum()',
        'modules/Blog/database/migrations/m.php:6 enum()',
    ]);
});

it('finds both calls of a chain, the outer call first', function () {
    $body = "return new class { public function up(\$t) {\n(\n\$t->enum('a', [])\n)->nullable()->enum('b', []); } };";

    expect(summarise(runRule(Migration::options(), ['database/migrations/m.php' => phpFile($body, 'Studeo\\Blog')])))->toBe([
        'modules/Blog/database/migrations/m.php:7 enum()',
        'modules/Blog/database/migrations/m.php:8 enum()',
    ]);
});

it('pins current behaviour: a multi-line chain is reported at the line of its first token', function () {
    $body = "return new class\n{\n    public function up(\$t)\n    {\n        \$t\n            ->string('a')\n            ->enum('b', []);\n    }\n};";

    expect(summarise(runRule(Migration::options(), ['database/migrations/m.php' => phpFile($body, 'Studeo\\Blog')])))->toBe(['modules/Blog/database/migrations/m.php:10 enum()']);
});

it('lists enum findings before domain references even when the reference comes first', function () {
    $body = 'return new class { public function up($t) { $t->enum("a", []); } };';
    $files = ['database/migrations/m.php' => phpFile($body, 'Studeo\\Blog', 'use Studeo\\Blog\\Models\\Post;')];

    $found = runRule(Migration::options(), $files);

    expect(array_map(fn (Violation $violation): string => $violation->line.' '.$violation->subject, $found))->toBe(['6 enum()', '5 Studeo\\Blog\\Models\\Post']);
});

it('reports the domain references of one file in order of first appearance', function () {
    $uses = "use Studeo\\Blog\\Models\\Post;\nuse Studeo\\Blog\\Actions\\Run;";
    $files = ['database/migrations/m.php' => phpFile('return new class { public function up() {} };', 'Studeo\\Blog', $uses)];

    expect(summarise(runRule(Migration::options(), $files)))->toBe([
        'modules/Blog/database/migrations/m.php:5 Studeo\\Blog\\Models\\Post',
        'modules/Blog/database/migrations/m.php:6 Studeo\\Blog\\Actions\\Run',
    ]);
});

it('accepts a forbidden list with string keys', function () {
    $files = ['database/migrations/m.php' => phpFile('return new class { public function up() {} };', 'Studeo\\Blog', 'use Studeo\\Blog\\Services\\Mailer;')];

    expect(runRule(Migration::options(forbidden: ['mail' => '\\Services\\']), $files))->toHaveCount(1);
});

it('matches a forbidden fragment that was given with surrounding spaces', function () {
    $files = ['database/migrations/m.php' => phpFile('return new class { public function up() {} };', 'Studeo\\Blog', 'use Studeo\\Blog\\Services\\Mailer;')];

    expect(runRule(Migration::options(forbidden: [' \\Services\\ ']), $files))->toHaveCount(1);
});
