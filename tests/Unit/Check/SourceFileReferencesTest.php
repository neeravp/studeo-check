<?php

use Studeo\Check\Engine\SourceFile;

beforeEach(function () {
    $this->file = fn (string $contents): SourceFile => checkSource(['Probe.php' => $contents])->files()[0];
    $this->references = fn (string $contents): array => ($this->file)($contents)->references();
});

it('lists a name used in code, a fully qualified name and a relative name at the line they appear on', function () {
    $contents = <<<'PHP'
<?php

namespace Acme\Blog\Actions;

use Acme\Billing\Plain;

final class Run
{
    public function go(Plain $plain): void
    {
        $a = new \Acme\Billing\Fq;
        $b = namespace\Rel::make();
    }
}
PHP;

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog\\Actions' => 3,
        'Acme\\Billing\\Plain' => 5,
        'Acme\\Billing\\Fq' => 11,
        'Acme\\Blog\\Actions\\Rel' => 12,
    ]);
});

it('lists an import that no code uses at its line, and an alias target', function () {
    $contents = "<?php\n\nnamespace Acme\\Blog;\n\nuse Acme\\Billing\\Unused;\nuse Acme\\Other as Alias;\n";

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing\\Unused' => 5,
        'Acme\\Other' => 6,
    ]);
});

it('lists the prefix, the unresolved fragments and then the full names of an unused group use', function () {
    $contents = "<?php\n\nnamespace Acme\\Blog;\n\nuse Acme\\Billing\\{Models\\Invoice, Data\\Line};\n";

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing' => 5,
        'Models\\Invoice' => 5,
        'Data\\Line' => 5,
        'Acme\\Billing\\Models\\Invoice' => 5,
        'Acme\\Billing\\Data\\Line' => 5,
    ]);
});

it('gives each item of a multi-line group use its own line', function () {
    $contents = "<?php\n\nnamespace Acme\\Blog;\n\nuse Acme\\Billing\\{\n    Models\\Early,\n    Data\\Fine\n};\n";

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing' => 5,
        'Models\\Early' => 6,
        'Data\\Fine' => 7,
        'Acme\\Billing\\Models\\Early' => 6,
        'Acme\\Billing\\Data\\Fine' => 7,
    ]);
});

it('lists function and const items of a group use', function () {
    $contents = "<?php\n\nnamespace Acme\\Blog;\n\nuse Acme\\Billing\\{function helper, const LIMIT};\n";

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing' => 5,
        'helper' => 5,
        'LIMIT' => 5,
        'Acme\\Billing\\helper' => 5,
        'Acme\\Billing\\LIMIT' => 5,
    ]);
});

it('keeps the line of a group item that is also used where the code uses it, in the order of the code names', function () {
    $contents = <<<'PHP'
<?php

namespace Acme\Blog;

use Acme\Billing\{Models\Early, Data\Fine};

final class Run
{
    public function go(): void
    {
        new Early;
    }
}
PHP;

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing' => 5,
        'Models\\Early' => 5,
        'Data\\Fine' => 5,
        'Acme\\Billing\\Models\\Early' => 11,
        'Acme\\Billing\\Data\\Fine' => 5,
    ]);
});

it('keeps the first line of a group item that appears in two groups', function () {
    $contents = "<?php\n\nnamespace Acme\\Blog;\n\nuse Acme\\Billing\\{Models\\Early};\nuse Acme\\Billing\\{Models\\Early as Other};\n";

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing' => 5,
        'Models\\Early' => 5,
        'Acme\\Billing\\Models\\Early' => 5,
    ]);
});

it('keeps the first line when a name appears several times in code', function () {
    $contents = <<<'PHP'
<?php

namespace Acme\Blog;

final class Run
{
    public function a(\Acme\Billing\Thing $x): void {}
    public function b(\Acme\Billing\Thing $x): void {}
}
PHP;

    expect(($this->references)($contents))->toBe(['Acme\\Blog' => 3, 'Acme\\Billing\\Thing' => 7]);
});

it('keeps the line of the import when an imported name is also used', function () {
    $contents = <<<'PHP'
<?php

namespace Acme\Blog;

use Acme\Billing\Thing;

final class Run
{
    public function a(Thing $x): void {}
}
PHP;

    expect(($this->references)($contents))->toBe(['Acme\\Blog' => 3, 'Acme\\Billing\\Thing' => 5]);
});

it('lists self, static and parent as names in the order they appear', function () {
    $contents = <<<'PHP'
<?php

namespace Acme\Blog;

final class Run extends \Acme\Billing\Base
{
    public function go()
    {
        self::a();
        static::b();
        parent::c();
    }
}
PHP;

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing\\Base' => 5,
        'self' => 9,
        'static' => 10,
        'parent' => 11,
    ]);
});

it('lists a bare function and constant name as it is written', function () {
    $contents = "<?php\n\nnamespace Acme\\Blog;\n\nstrlen('x');\necho PHP_EOL;\n";

    expect(($this->references)($contents))->toBe(['Acme\\Blog' => 3, 'strlen' => 5, 'PHP_EOL' => 6]);
});

it('lists the names of attributes, extends, implements, types, new, static calls, instanceof and catch in traversal order', function () {
    $contents = <<<'PHP'
<?php

namespace Acme\Blog;

#[\Acme\Billing\AttrA]
final class Run extends \Acme\Billing\ExtendsB implements \Acme\Billing\ImplementsC
{
    public function go(\Acme\Billing\ParamD $d): \Acme\Billing\ReturnE
    {
        new \Acme\Billing\NewF;
        \Acme\Billing\StaticG::make();
        $x instanceof \Acme\Billing\InstanceH;
        try {
        } catch (\Acme\Billing\CatchI $e) {
        }
    }
}
PHP;

    expect(($this->references)($contents))->toBe([
        'Acme\\Blog' => 3,
        'Acme\\Billing\\AttrA' => 5,
        'Acme\\Billing\\ExtendsB' => 6,
        'Acme\\Billing\\ImplementsC' => 6,
        'Acme\\Billing\\ParamD' => 8,
        'Acme\\Billing\\ReturnE' => 8,
        'Acme\\Billing\\NewF' => 10,
        'Acme\\Billing\\StaticG' => 11,
        'Acme\\Billing\\InstanceH' => 12,
        'Acme\\Billing\\CatchI' => 14,
    ]);
});
